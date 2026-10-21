from __future__ import annotations

import faulthandler
import json
import os
import platform
import sys
import threading
import time
from contextlib import contextmanager
from pathlib import Path
from typing import Any, Iterator


_lock = threading.Lock()
_fault_file: Any | None = None


def _log_root() -> Path:
    root = Path(os.environ.get("AI_WORKER_LOG_ROOT", Path(__file__).resolve().parents[1] / "logs"))
    root.mkdir(parents=True, exist_ok=True)
    return root


def enable_native_diagnostics() -> None:
    """Enable crash evidence before importing torch/transformers native modules."""
    global _fault_file
    if _fault_file is not None:
        return
    fault_path = _log_root() / f"faulthandler-{os.getpid()}.log"
    _fault_file = fault_path.open("a", encoding="utf-8", buffering=1)
    faulthandler.enable(file=_fault_file, all_threads=True)
    record_event(
        "runtime_started",
        executable=sys.executable,
        python=sys.version,
        platform=platform.platform(),
        pid=os.getpid(),
    )


def record_event(event: str, **fields: Any) -> None:
    payload = {
        "timestamp": time.time(),
        "event": event,
        "pid": os.getpid(),
        "thread": threading.current_thread().name,
        **fields,
    }
    with _lock:
        with (_log_root() / "runtime.jsonl").open("a", encoding="utf-8") as stream:
            stream.write(json.dumps(payload, ensure_ascii=False, default=str) + "\n")


@contextmanager
def native_stage(stage: str, **fields: Any) -> Iterator[None]:
    started = time.perf_counter()
    record_event("stage_started", stage=stage, **fields)
    try:
        yield
    except BaseException as exception:
        record_event(
            "stage_failed",
            stage=stage,
            duration_ms=round((time.perf_counter() - started) * 1000, 2),
            exception_type=type(exception).__name__,
            message=str(exception)[:1000],
            **fields,
        )
        raise
    else:
        record_event(
            "stage_completed",
            stage=stage,
            duration_ms=round((time.perf_counter() - started) * 1000, 2),
            **fields,
        )


def runtime_snapshot() -> dict[str, Any]:
    snapshot: dict[str, Any] = {
        "pid": os.getpid(),
        "python": sys.version,
        "executable": sys.executable,
        "platform": platform.platform(),
        "threads": threading.active_count(),
    }
    try:
        import torch

        snapshot["torch"] = {
            "version": torch.__version__,
            "cuda_runtime": torch.version.cuda,
            "cuda_available": torch.cuda.is_available(),
            "allocated_vram_mb": round(torch.cuda.memory_allocated() / 1024 / 1024, 2) if torch.cuda.is_available() else 0,
            "reserved_vram_mb": round(torch.cuda.memory_reserved() / 1024 / 1024, 2) if torch.cuda.is_available() else 0,
        }
    except Exception as exception:
        snapshot["torch"] = {"available": False, "error": type(exception).__name__}
    return snapshot
