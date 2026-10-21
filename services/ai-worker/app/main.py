from __future__ import annotations

import json
import os
import tempfile
import uuid
from contextlib import asynccontextmanager
from pathlib import Path
from typing import AsyncIterator

from .runtime_diagnostics import enable_native_diagnostics, record_event, runtime_snapshot

enable_native_diagnostics()

from fastapi import Depends, FastAPI, File, Form, Header, HTTPException, UploadFile
from fastapi.responses import FileResponse, JSONResponse

from .async_reconstruction import (
    cancel_job, cleanup_expired_jobs, public_job, read_job, recover_interrupted_jobs,
    scope_matches, submit_reconstruction,
)
from .reconstruction import (
    colmap_executable,
    dense_reconstruction_capability,
    inspect_colmap_runtime,
    reconstruct as reconstruct_photogrammetry,
)
from .vision import vision_pipeline


@asynccontextmanager
async def lifespan(_: FastAPI) -> AsyncIterator[None]:
    artifact_root = Path(os.environ.get("AI_WORKER_ARTIFACT_ROOT", Path(__file__).resolve().parents[1] / "artifacts"))
    recovered = recover_interrupted_jobs(artifact_root)
    removed = cleanup_expired_jobs(artifact_root)
    record_event("reconstruction_jobs_reconciled", recovered=recovered, removed=removed)
    record_event("fastapi_started")
    try:
        yield
    finally:
        vision_pipeline.unload_models()
        record_event("fastapi_stopped")


app = FastAPI(
    title="FlipControl AI Worker",
    version="0.1.0",
    docs_url=None,
    redoc_url=None,
    lifespan=lifespan,
)


def authorize(x_worker_token: str | None = Header(default=None)) -> None:
    expected = os.environ.get("AI_WORKER_TOKEN")
    if not expected or not x_worker_token or x_worker_token != expected:
        raise HTTPException(status_code=401, detail="Credencial interna inválida.")


def torch_status() -> dict:
    try:
        import torch

        return {
            "installed": True,
            "version": torch.__version__,
            "cuda_available": torch.cuda.is_available(),
            "cuda_runtime": torch.version.cuda,
            "device": torch.cuda.get_device_name(0) if torch.cuda.is_available() else None,
        }
    except Exception as exception:
        return {"installed": False, "error": type(exception).__name__}


@app.get("/health", dependencies=[Depends(authorize)])
def health() -> dict:
    return {"status": "ok", "torch": torch_status(), "runtime": runtime_snapshot()}


@app.get("/v1/models", dependencies=[Depends(authorize)])
def models() -> dict:
    adapter = Path(__file__).resolve().parents[1] / "models" / "real-estate-lora"
    colmap = colmap_executable()
    runtime_probe = inspect_colmap_runtime(colmap) if colmap is not None else None
    return {
        "torch": torch_status(),
        "lora_adapter": {"available": (adapter / "adapter_model.safetensors").is_file(), "path": str(adapter)},
        "vision": vision_pipeline.model_status(),
        "reconstruction": {
            "colmap": colmap is not None,
            "version": "4.2.1",
            "dense": dense_reconstruction_capability(executable=colmap, runtime_probe=runtime_probe),
        },
    }


async def validate_image(file: UploadFile) -> bytes:
    if file.content_type not in {"image/jpeg", "image/png", "image/webp"}:
        raise HTTPException(status_code=422, detail="Formato de imagem não permitido.")
    data = await file.read(10 * 1024 * 1024 + 1)
    if not data or len(data) > 10 * 1024 * 1024:
        raise HTTPException(status_code=422, detail="Imagem vazia ou acima de 10 MB.")
    return data


async def run_vision(file: UploadFile, points_json: str | None = None) -> dict:
    data = await validate_image(file)
    points = None
    if points_json:
        try:
            decoded = json.loads(points_json)
            if not isinstance(decoded, list) or any(
                not (
                    isinstance(point, list) and len(point) in {2, 3}
                    or isinstance(point, dict) and {"x", "y"}.issubset(point)
                )
                for point in decoded
            ):
                raise ValueError
            points = decoded
        except (json.JSONDecodeError, ValueError):
            raise HTTPException(status_code=422, detail="Pontos de segmentação inválidos.") from None
    suffix = Path(file.filename or "image.jpg").suffix.lower()
    if suffix not in {".jpg", ".jpeg", ".png", ".webp"}:
        raise HTTPException(status_code=422, detail="Extensão de imagem não permitida.")
    with tempfile.TemporaryDirectory(prefix="flipcontrol-vision-") as temporary:
        source = Path(temporary) / f"source{suffix}"
        source.write_bytes(data)
        try:
            return vision_pipeline.analyze(source, points)
        except (ValueError, OSError) as exception:
            raise HTTPException(status_code=422, detail=str(exception)) from exception


@app.post("/v1/vision/analyze", dependencies=[Depends(authorize)])
async def vision_analyze(file: UploadFile = File(...), points: str | None = Form(default=None)) -> dict:
    return await run_vision(file, points)


@app.post("/v1/vision/depth", dependencies=[Depends(authorize)])
async def vision_depth(file: UploadFile) -> dict:
    return await run_vision(file)


@app.post("/v1/vision/segment", dependencies=[Depends(authorize)])
async def vision_segment(file: UploadFile) -> dict:
    return await run_vision(file)


@app.post("/v1/vision/simulate", dependencies=[Depends(authorize)])
async def vision_simulate(
    file: UploadFile = File(...),
    color: str = Form(...),
    points: str | None = Form(default=None),
    opacity: float = Form(default=0.72),
    material: str = Form(default="paint"),
) -> dict:
    if not color.startswith("#") or len(color) != 7:
        raise HTTPException(status_code=422, detail="Cor hexadecimal inválida.")
    if material not in {"paint", "wood"} or not 0.1 <= opacity <= 1.0:
        raise HTTPException(status_code=422, detail="Parâmetros de simulação inválidos.")
    data = await validate_image(file)
    decoded_points = None
    if points:
        try:
            decoded_points = json.loads(points)
            if not isinstance(decoded_points, list) or len(decoded_points) > 16:
                raise ValueError
        except (json.JSONDecodeError, ValueError):
            raise HTTPException(status_code=422, detail="Pontos de segmentação inválidos.") from None
    suffix = Path(file.filename or "image.jpg").suffix.lower()
    with tempfile.TemporaryDirectory(prefix="flipcontrol-simulation-") as temporary:
        source = Path(temporary) / f"source{suffix}"
        source.write_bytes(data)
        try:
            return vision_pipeline.simulate(source, color, decoded_points, opacity, material)
        except (ValueError, OSError) as exception:
            raise HTTPException(status_code=422, detail=str(exception)) from exception


@app.post("/v1/3d/reconstruct", dependencies=[Depends(authorize)])
async def reconstruct(
    files: list[UploadFile] = File(...),
    overlap_acknowledged: bool = Form(default=False),
    method: str = Form(default="auto"),
    idempotency_key: str = Form(default=""),
    job_scope: str = Form(default=""),
) -> dict:
    if len(files) < 8 or not overlap_acknowledged:
        raise HTTPException(status_code=422, detail="Reconstrução fiel exige ao menos 8 fotos sobrepostas do mesmo ambiente.")
    if len(files) > 120:
        raise HTTPException(status_code=422, detail="O limite por processamento é de 120 imagens.")
    if method not in {"auto", "calibrated", "tsdf", "dense_colmap"}:
        raise HTTPException(status_code=422, detail="Método de reconstrução inválido.")
    if colmap_executable() is None:
        raise HTTPException(status_code=503, detail="COLMAP não está instalado neste worker.")
    artifact_root = Path(os.environ.get("AI_WORKER_ARTIFACT_ROOT", Path(__file__).resolve().parents[1] / "artifacts"))
    try:
        job_id = str(uuid.UUID(idempotency_key))
    except ValueError:
        raise HTTPException(status_code=422, detail="Chave idempotente inválida.") from None
    if not 32 <= len(job_scope) <= 128 or not all(character in "0123456789abcdef" for character in job_scope.lower()):
        raise HTTPException(status_code=422, detail="Escopo interno inválido.")
    staging = artifact_root / ".staging" / job_id
    existing = read_job(artifact_root, job_id)
    if existing is not None:
        if not scope_matches(existing, job_scope):
            raise HTTPException(status_code=404, detail="Job não encontrado.")
        return public_job(existing)
    staging.mkdir(parents=True, exist_ok=False)
    inputs = []
    total_bytes = 0
    try:
        configured_upload_mb = int(os.environ.get("AI_WORKER_MAX_UPLOAD_MB", "512"))
    except ValueError:
        configured_upload_mb = 512
    max_upload_bytes = max(80, min(configured_upload_mb, 1200)) * 1024 * 1024
    try:
        for index, file in enumerate(files):
            data = await validate_image(file)
            total_bytes += len(data)
            if total_bytes > max_upload_bytes:
                raise HTTPException(status_code=422, detail="O conjunto excede o limite total do worker.")
            suffix = Path(file.filename or "image.jpg").suffix.lower()
            target = staging / f"{index:04d}{suffix}"
            target.write_bytes(data)
            inputs.append(target)
        return submit_reconstruction(inputs=inputs, artifact_root=artifact_root, job_id=job_id, method=method, scope=job_scope)
    except PermissionError:
        raise HTTPException(status_code=404, detail="Job não encontrado.") from None
    except Exception:
        import shutil

        shutil.rmtree(staging, ignore_errors=True)
        raise


@app.get("/v1/jobs/{job_id}", dependencies=[Depends(authorize)])
def job(job_id: str, x_worker_scope: str | None = Header(default=None)) -> dict:
    try:
        safe_id = str(uuid.UUID(job_id))
    except ValueError:
        raise HTTPException(status_code=404, detail="Job não encontrado.") from None
    artifact_root = Path(os.environ.get("AI_WORKER_ARTIFACT_ROOT", Path(__file__).resolve().parents[1] / "artifacts"))
    result = read_job(artifact_root, safe_id)
    if result is None:
        raise HTTPException(status_code=404, detail="Job não encontrado.")
    if not x_worker_scope or not scope_matches(result, x_worker_scope):
        raise HTTPException(status_code=404, detail="Job não encontrado.")
    return public_job(result)


@app.delete("/v1/jobs/{job_id}", dependencies=[Depends(authorize)])
def cancel_reconstruction_job(job_id: str, x_worker_scope: str | None = Header(default=None)) -> dict:
    try:
        safe_id = str(uuid.UUID(job_id))
    except ValueError:
        raise HTTPException(status_code=404, detail="Job não encontrado.") from None
    artifact_root = Path(os.environ.get("AI_WORKER_ARTIFACT_ROOT", Path(__file__).resolve().parents[1] / "artifacts"))
    try:
        result = cancel_job(artifact_root, safe_id, x_worker_scope or "")
    except PermissionError:
        raise HTTPException(status_code=404, detail="Job não encontrado.") from None
    if result is None:
        raise HTTPException(status_code=404, detail="Job não encontrado.")
    return result


@app.get("/v1/jobs/{job_id}/artifacts/{name}", dependencies=[Depends(authorize)])
def artifact(job_id: str, name: str, x_worker_scope: str | None = Header(default=None)):
    try:
        safe_id = str(uuid.UUID(job_id))
    except ValueError:
        raise HTTPException(status_code=404, detail="Artefato não encontrado.") from None
    if Path(name).name != name or name not in {"original.jpg", "depth.png", "mask.png", "segmentation-overlay.png", "simulation.png", "comparison.png", "result.json", "sparse.ply", "dense.ply", "mesh.ply", "dense-mesh.ply", "dense-model.glb", "model.glb"}:
        raise HTTPException(status_code=404, detail="Artefato não encontrado.")
    target = Path(os.environ.get("AI_WORKER_ARTIFACT_ROOT", Path(__file__).resolve().parents[1] / "artifacts")) / safe_id / name
    job_result = read_job(target.parents[1], safe_id)
    if job_result is None:
        raise HTTPException(status_code=404, detail="Artefato não encontrado.")
    # Legacy synchronous vision jobs retain their internal-token contract.
    # Reconstruction ownership cannot disappear while result.json is written.
    legacy_vision = (
        job_result.get("kind") in {"vision", "photo_simulation"}
        and not (target.parent / "job-status.json").exists()
        and not job_result.get("scope_hash")
    )
    if not legacy_vision and (not x_worker_scope or not scope_matches(job_result, x_worker_scope)):
        raise HTTPException(status_code=404, detail="Artefato não encontrado.")
    if not target.is_file():
        raise HTTPException(status_code=404, detail="Artefato não encontrado.")
    if name == "result.json":
        return JSONResponse(public_job(job_result))
    return FileResponse(target, filename=name)
