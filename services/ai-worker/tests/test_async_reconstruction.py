from __future__ import annotations

import json
from pathlib import Path

from app import async_reconstruction


class ImmediateExecutor:
    def submit(self, callback, *args):
        callback(*args)


def test_submission_persists_progress_and_final_result(tmp_path: Path, monkeypatch) -> None:
    staging = tmp_path / ".staging" / "00000000-0000-4000-8000-000000000001"
    staging.mkdir(parents=True)
    source = staging / "0000.jpg"
    source.write_bytes(b"photo")

    def fake_reconstruct(inputs, artifact_root, *, job_id, method, cancel, progress):
        assert inputs == [source]
        assert method == "tsdf"
        assert cancel() is False
        progress("registering_cameras", 38)
        result = {"job_id": job_id, "status": "completed", "artifacts": {}}
        (artifact_root / job_id / "result.json").write_text(json.dumps(result), encoding="utf-8")
        return result

    monkeypatch.setattr(async_reconstruction, "_executor", ImmediateExecutor())
    monkeypatch.setattr(async_reconstruction, "reconstruct", fake_reconstruct)
    job_id = "00000000-0000-4000-8000-000000000001"

    accepted = async_reconstruction.submit_reconstruction(
        inputs=[source], artifact_root=tmp_path, job_id=job_id, method="tsdf", scope="a" * 64,
    )

    assert accepted["status"] == "queued"
    completed = async_reconstruction.read_job(tmp_path, job_id)
    assert completed["status"] == "completed"
    assert async_reconstruction.scope_matches(completed, "a" * 64)
    assert not staging.exists()


def test_cancel_marker_is_visible_to_running_job(tmp_path: Path) -> None:
    job_id = "00000000-0000-4000-8000-000000000003"
    output = tmp_path / job_id
    output.mkdir()
    scope = "b" * 64
    (output / "job-status.json").write_text(json.dumps({
        "job_id": job_id, "status": "processing",
        "scope_hash": __import__("hashlib").sha256(scope.encode()).hexdigest(),
    }), encoding="utf-8")

    result = async_reconstruction.cancel_job(tmp_path, job_id, scope)

    assert result is not None
    assert result["status"] == "cancelling"
    assert (output / "cancel.requested").is_file()


def test_failed_job_is_queryable_without_native_traceback(tmp_path: Path, monkeypatch) -> None:
    staging = tmp_path / ".staging" / "00000000-0000-4000-8000-000000000002"
    staging.mkdir(parents=True)
    source = staging / "0000.jpg"
    source.write_bytes(b"photo")

    def fail(*args, **kwargs):
        raise RuntimeError("camera registration failed")

    monkeypatch.setattr(async_reconstruction, "_executor", ImmediateExecutor())
    monkeypatch.setattr(async_reconstruction, "reconstruct", fail)
    job_id = "00000000-0000-4000-8000-000000000002"

    async_reconstruction.submit_reconstruction(inputs=[source], artifact_root=tmp_path, job_id=job_id, scope="c" * 64)
    result = async_reconstruction.read_job(tmp_path, job_id)

    assert result["status"] == "failed"
    assert result["error_type"] == "RuntimeError"
    assert "camera registration" in result["error"]
    assert not staging.exists()


def test_idempotent_submission_returns_existing_job_only_inside_same_scope(tmp_path: Path, monkeypatch) -> None:
    job_id = "00000000-0000-4000-8000-000000000004"
    staging = tmp_path / ".staging" / job_id
    staging.mkdir(parents=True)
    source = staging / "0000.jpg"
    source.write_bytes(b"photo")
    monkeypatch.setattr(async_reconstruction, "_executor", ImmediateExecutor())
    monkeypatch.setattr(async_reconstruction, "reconstruct", lambda *args, **kwargs: None)
    scope = "d" * 64

    async_reconstruction.submit_reconstruction(inputs=[source], artifact_root=tmp_path, job_id=job_id, scope=scope)
    existing = async_reconstruction.submit_reconstruction(inputs=[], artifact_root=tmp_path, job_id=job_id, scope=scope)

    assert existing["job_id"] == job_id
    assert "scope_hash" not in existing
    try:
        async_reconstruction.submit_reconstruction(inputs=[], artifact_root=tmp_path, job_id=job_id, scope="e" * 64)
        assert False, "cross-scope reuse should fail"
    except PermissionError:
        pass


def test_restart_recovery_and_retention_cleanup_are_persisted(tmp_path: Path, monkeypatch) -> None:
    job_id = "00000000-0000-4000-8000-000000000005"
    output = tmp_path / job_id
    output.mkdir()
    (output / "job-status.json").write_text(json.dumps({
        "job_id": job_id, "status": "processing", "updated_at": 1, "scope_hash": "hash",
    }), encoding="utf-8")

    assert async_reconstruction.recover_interrupted_jobs(tmp_path) == 1
    recovered = async_reconstruction.read_job(tmp_path, job_id)
    assert recovered["status"] == "failed"
    assert recovered["error_type"] == "WorkerRestarted"
    monkeypatch.setenv("AI_WORKER_RETENTION_HOURS", "1")
    assert async_reconstruction.cleanup_expired_jobs(tmp_path, now=__import__("time").time() + 7200) == 1
    assert not output.exists()
