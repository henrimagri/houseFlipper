from __future__ import annotations

import json
import hashlib
import hmac
import os
import shutil
import threading
import time
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path
from typing import Any

from .reconstruction import reconstruct
from .runtime_diagnostics import native_stage, record_event


_executor = ThreadPoolExecutor(max_workers=1, thread_name_prefix="reconstruction")
_write_lock = threading.Lock()
_terminal_states = {"completed", "failed", "cancelled"}


def _write_json(path: Path, payload: dict[str, Any]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    temporary = path.with_suffix(path.suffix + ".tmp")
    with _write_lock:
        temporary.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")
        temporary.replace(path)


def job_status_path(artifact_root: Path, job_id: str) -> Path:
    return artifact_root / job_id / "job-status.json"


def _read_json(path: Path) -> dict[str, Any] | None:
    try:
        payload = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, ValueError):
        return None
    return payload if isinstance(payload, dict) else None


def read_job(artifact_root: Path, job_id: str) -> dict[str, Any] | None:
    status_path = job_status_path(artifact_root, job_id)
    status = _read_json(status_path)
    result = _read_json(artifact_root / job_id / "result.json")
    if result is None:
        return status
    # The reconstruction pipeline writes its result independently. Ownership is
    # established before execution and must survive partial/unscoped results.
    if status_path.exists():
        result["scope_hash"] = (status or {}).get("scope_hash")
    return result


def scope_matches(job: dict[str, Any], scope: str) -> bool:
    expected = str(job.get("scope_hash") or "")
    return bool(expected) and hmac.compare_digest(hashlib.sha256(scope.encode("utf-8")).hexdigest(), expected)


def public_job(job: dict[str, Any]) -> dict[str, Any]:
    return {key: value for key, value in job.items() if key != "scope_hash"}


def submit_reconstruction(
    *, inputs: list[Path], artifact_root: Path, job_id: str, method: str = "auto", scope: str,
) -> dict[str, Any]:
    output = artifact_root / job_id
    existing = read_job(artifact_root, job_id)
    if existing is not None:
        if not scope_matches(existing, scope):
            raise PermissionError("O identificador idempotente pertence a outro escopo.")
        return public_job(existing)
    output.mkdir(parents=True, exist_ok=False)
    initial = {
        "job_id": job_id,
        "kind": "photogrammetry",
        "status": "queued",
        "stage": "queued",
        "percent": 0,
        "requested_method": method,
        "scope_hash": hashlib.sha256(scope.encode("utf-8")).hexdigest(),
        "updated_at": time.time(),
    }
    _write_json(job_status_path(artifact_root, job_id), initial)
    _executor.submit(_run, inputs, artifact_root, job_id, method)
    record_event("reconstruction_queued", job_id=job_id, input_count=len(inputs), method=method)
    return public_job(initial)


def cancel_job(artifact_root: Path, job_id: str, scope: str) -> dict[str, Any] | None:
    current = read_job(artifact_root, job_id)
    if current is None:
        return None
    if not scope_matches(current, scope):
        raise PermissionError("O job não pertence ao escopo informado.")
    if current.get("status") in {"completed", "failed", "cancelled"}:
        return public_job(current)
    marker = artifact_root / job_id / "cancel.requested"
    marker.write_text(str(time.time()), encoding="ascii")
    current.update({"status": "cancelling", "stage": "cancelling", "updated_at": time.time()})
    _write_json(job_status_path(artifact_root, job_id), current)
    return public_job(current)


def _run(inputs: list[Path], artifact_root: Path, job_id: str, method: str = "auto") -> None:
    status_path = job_status_path(artifact_root, job_id)
    cancel_marker = artifact_root / job_id / "cancel.requested"
    persisted = read_job(artifact_root, job_id) or {}
    scope_hash = persisted.get("scope_hash")

    def cancelled() -> bool:
        return cancel_marker.is_file()

    def progress(stage: str, percent: int) -> None:
        _write_json(status_path, {
            "job_id": job_id,
            "kind": "photogrammetry",
            "status": "processing",
            "stage": stage,
            "percent": percent,
            "requested_method": method,
            "scope_hash": scope_hash,
            "updated_at": time.time(),
        })
        record_event("reconstruction_job_progress", job_id=job_id, stage=stage, percent=percent)

    try:
        with native_stage("photogrammetry_job", job_id=job_id, input_count=len(inputs)):
            reconstruct(inputs, artifact_root, job_id=job_id, method=method, cancel=cancelled, progress=progress)
        completed_path = artifact_root / job_id / "result.json"
        completed = json.loads(completed_path.read_text(encoding="utf-8"))
        completed["scope_hash"] = scope_hash
        completed["updated_at"] = time.time()
        _write_json(completed_path, completed)
        _write_json(status_path, completed)
    except InterruptedError as exception:
        cancelled_result = {
            "job_id": job_id,
            "kind": "photogrammetry",
            "status": "cancelled",
            "stage": "cancelled",
            "percent": 100,
            "requested_method": method,
            "scope_hash": scope_hash,
            "message": str(exception),
            "updated_at": time.time(),
        }
        _write_json(artifact_root / job_id / "result.json", cancelled_result)
        _write_json(status_path, cancelled_result)
        record_event("reconstruction_job_cancelled", job_id=job_id)
    except Exception as exception:
        failed = {
            "job_id": job_id,
            "kind": "photogrammetry",
            "status": "failed",
            "stage": "failed",
            "percent": 100,
            "error": str(exception)[:2000],
            "error_type": type(exception).__name__,
            "scope_hash": scope_hash,
            "updated_at": time.time(),
        }
        _write_json(artifact_root / job_id / "result.json", failed)
        _write_json(status_path, failed)
        record_event("reconstruction_job_failed", job_id=job_id, error_type=type(exception).__name__)
    finally:
        staging = artifact_root / ".staging" / job_id
        shutil.rmtree(staging, ignore_errors=True)


def recover_interrupted_jobs(artifact_root: Path) -> int:
    """Persist an honest terminal state for work lost during a process restart."""
    recovered = 0
    if not artifact_root.is_dir():
        return recovered
    for status_path in artifact_root.glob("*/job-status.json"):
        job = _read_json(status_path)
        if job is None:
            continue
        result = read_job(artifact_root, status_path.parent.name)
        if result and result.get("status") in _terminal_states:
            # A crash may happen after result.json is complete but before the
            # final progress snapshot. Never overwrite that terminal result.
            _write_json(status_path, result)
            continue
        if job.get("status") not in {"queued", "processing", "cancelling"}:
            continue
        job.update({
            "status": "failed", "stage": "worker_restarted", "percent": 100,
            "error": "O worker reiniciou durante o processamento. Reenvie o job com uma nova chave idempotente.",
            "error_type": "WorkerRestarted", "updated_at": time.time(),
        })
        _write_json(status_path.parent / "result.json", job)
        _write_json(status_path, job)
        recovered += 1
    return recovered


def cleanup_expired_jobs(artifact_root: Path, *, now: float | None = None) -> int:
    try:
        configured_hours = int(os.environ.get("AI_WORKER_RETENTION_HOURS", "72"))
    except ValueError:
        configured_hours = 72
    retention_hours = max(1, min(configured_hours, 24 * 30))
    cutoff = (now or time.time()) - retention_hours * 3600
    removed = 0
    if not artifact_root.is_dir():
        return removed
    for directory in artifact_root.iterdir():
        if not directory.is_dir() or directory.name == ".staging":
            continue
        job = read_job(artifact_root, directory.name)
        if not job or job.get("status") not in {"completed", "failed", "cancelled"}:
            continue
        if float(job.get("updated_at") or directory.stat().st_mtime) >= cutoff:
            continue
        shutil.rmtree(directory, ignore_errors=True)
        removed += 1
    return removed
