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

    def fake_reconstruct(inputs, artifact_root, *, job_id, progress):
        assert inputs == [source]
        progress("registering_cameras", 38)
        result = {"job_id": job_id, "status": "completed", "artifacts": {}}
        (artifact_root / job_id / "result.json").write_text(json.dumps(result), encoding="utf-8")
        return result

    monkeypatch.setattr(async_reconstruction, "_executor", ImmediateExecutor())
    monkeypatch.setattr(async_reconstruction, "reconstruct", fake_reconstruct)
    job_id = "00000000-0000-4000-8000-000000000001"

    accepted = async_reconstruction.submit_reconstruction(
        inputs=[source], artifact_root=tmp_path, job_id=job_id,
    )

    assert accepted["status"] == "queued"
    assert async_reconstruction.read_job(tmp_path, job_id)["status"] == "completed"
    assert not staging.exists()


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

    async_reconstruction.submit_reconstruction(inputs=[source], artifact_root=tmp_path, job_id=job_id)
    result = async_reconstruction.read_job(tmp_path, job_id)

    assert result["status"] == "failed"
    assert result["error_type"] == "RuntimeError"
    assert "camera registration" in result["error"]
    assert not staging.exists()
