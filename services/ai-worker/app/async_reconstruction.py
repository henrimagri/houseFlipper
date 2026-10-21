from __future__ import annotations

import json
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


def _write_json(path: Path, payload: dict[str, Any]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    temporary = path.with_suffix(path.suffix + ".tmp")
    with _write_lock:
        temporary.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")
        temporary.replace(path)


def job_status_path(artifact_root: Path, job_id: str) -> Path:
    return artifact_root / job_id / "job-status.json"


def read_job(artifact_root: Path, job_id: str) -> dict[str, Any] | None:
    result = artifact_root / job_id / "result.json"
    status = job_status_path(artifact_root, job_id)
    target = result if result.is_file() else status
    if not target.is_file():
        return None
    return json.loads(target.read_text(encoding="utf-8"))


def submit_reconstruction(*, inputs: list[Path], artifact_root: Path, job_id: str) -> dict[str, Any]:
    output = artifact_root / job_id
    output.mkdir(parents=True, exist_ok=False)
    initial = {
        "job_id": job_id,
        "kind": "photogrammetry",
        "status": "queued",
        "stage": "queued",
        "percent": 0,
        "updated_at": time.time(),
    }
    _write_json(job_status_path(artifact_root, job_id), initial)
    _executor.submit(_run, inputs, artifact_root, job_id)
    record_event("reconstruction_queued", job_id=job_id, input_count=len(inputs))
    return initial


def _run(inputs: list[Path], artifact_root: Path, job_id: str) -> None:
    status_path = job_status_path(artifact_root, job_id)

    def progress(stage: str, percent: int) -> None:
        _write_json(status_path, {
            "job_id": job_id,
            "kind": "photogrammetry",
            "status": "processing",
            "stage": stage,
            "percent": percent,
            "updated_at": time.time(),
        })
        record_event("reconstruction_job_progress", job_id=job_id, stage=stage, percent=percent)

    try:
        with native_stage("photogrammetry_job", job_id=job_id, input_count=len(inputs)):
            reconstruct(inputs, artifact_root, job_id=job_id, progress=progress)
    except Exception as exception:
        failed = {
            "job_id": job_id,
            "kind": "photogrammetry",
            "status": "failed",
            "stage": "failed",
            "percent": 100,
            "error": str(exception)[:2000],
            "error_type": type(exception).__name__,
            "updated_at": time.time(),
        }
        _write_json(artifact_root / job_id / "result.json", failed)
        record_event("reconstruction_job_failed", job_id=job_id, error_type=type(exception).__name__)
    finally:
        staging = artifact_root / ".staging" / job_id
        shutil.rmtree(staging, ignore_errors=True)
