import json
from io import BytesIO

import numpy as np
import pytest
from fastapi.testclient import TestClient
from PIL import Image

from app.main import app
from app.vision import VisionPipeline, vision_pipeline


def stroke(mode="paint", radius=0.1, points=None):
    return {"mode": mode, "radius": radius, "points": points or [{"x": 0.2, "y": 0.5}, {"x": 0.8, "y": 0.5}]}


def test_manual_pipeline_preserves_original_and_unmasked_pixels_without_models(tmp_path, monkeypatch):
    monkeypatch.setenv("AI_WORKER_ARTIFACT_ROOT", str(tmp_path / "artifacts"))
    source = tmp_path / "input.png"
    pixels = np.random.default_rng(42).integers(30, 210, size=(96, 128, 3), dtype=np.uint8)
    Image.fromarray(pixels).save(source)
    original_bytes = source.read_bytes()
    pipeline = VisionPipeline()

    def forbidden(*args, **kwargs):
        pytest.fail("Manual painting must not call or load a neural model")

    for name in ("_detect_locked", "_segment_locked", "depth", "_load_detection", "_load_sam", "_load_depth"):
        monkeypatch.setattr(pipeline, name, forbidden, raising=False)
    result = pipeline.simulate(source, "#3366cc", opacity=1, mask_mode="manual", mask_strokes=[
        stroke(), stroke("erase", 0.06, [{"x": 0.5, "y": 0.5}]),
    ])
    output = tmp_path / "artifacts" / result["job_id"]
    mask = np.asarray(Image.open(output / "mask.png"))
    edited = np.asarray(Image.open(output / "simulation.png"))
    assert mask[48, 64] == 0  # Eraser stroke cuts a real hole.
    assert mask[48, 32] == 255
    assert np.array_equal(edited[mask == 0], pixels[mask == 0])
    assert np.any(edited[mask > 0] != pixels[mask > 0])
    assert np.array_equal(np.asarray(Image.open(output / result["original_artifact"])), pixels)
    assert source.read_bytes() == original_bytes
    assert result["segmentation"]["provenance"] == "manual_user_mask"
    assert result["segmentation"]["model_used"] is False
    assert result["detection"]["model_used"] is False
    assert (output / "segmentation-overlay.png").is_file()
    comparison = np.asarray(Image.open(output / "comparison.png"))
    assert np.array_equal(comparison[:, :128], pixels)
    assert np.array_equal(comparison[:, 128:], edited)
    assert json.loads((output / "result.json").read_text())["composition"]["mask_mode"] == "manual"


@pytest.mark.parametrize("strokes", [
    [], [stroke("other")], [stroke([])], [stroke(radius=10**500)], [stroke(radius=0)], [stroke(radius=0.151)],
    [stroke(radius=float("nan"))], [stroke(radius=True)],
    [stroke(points=[{"x": -0.01, "y": 0.5}])], [stroke(points=[{"x": 0, "y": float("inf")}])],
    [{"mode": "paint", "radius": 0.1, "points": []}],
    [stroke(points=[{"x": 0.5, "y": 0.5}] * 257)], [stroke()] * 65,
    [stroke("erase")], [stroke(), stroke("erase")],
])
def test_manual_mask_rejects_invalid_or_empty_regions(strokes):
    with pytest.raises(ValueError):
        VisionPipeline.rasterize_manual_mask((128, 96), strokes)


def test_small_manual_brush_remains_visible_and_coordinates_include_edges():
    image = Image.new("RGB", (100, 100), (100, 100, 100))
    mask = VisionPipeline.rasterize_manual_mask(image.size, [stroke(radius=0.002, points=[{"x": 1, "y": 0}])])
    assert mask.getpixel((99, 0)) == 255
    edited = VisionPipeline.compose_paint(image, mask, "#3366cc", refine_mask=False)
    assert edited.getpixel((99, 0)) != image.getpixel((99, 0))
    assert edited.getpixel((0, 99)) == image.getpixel((0, 99))


def test_default_sam_path_keeps_detection_and_segmentation(tmp_path, monkeypatch):
    monkeypatch.setenv("AI_WORKER_ARTIFACT_ROOT", str(tmp_path / "artifacts"))
    source = tmp_path / "image.png"
    Image.new("RGB", (32, 32), "gray").save(source)
    pipeline = VisionPipeline()
    calls = []
    def detect(image):
        calls.append("detr")
        return {"model": "DETR", "objects": []}
    def segment(image, output, points):
        calls.append("sam")
        Image.new("L", image.size, 255).save(output / "mask.png")
        return {"artifact": "mask.png", "model": "SAM 2"}
    monkeypatch.setattr(pipeline, "_detect_locked", detect)
    monkeypatch.setattr(pipeline, "_segment_locked", segment)
    result = pipeline.simulate(source, "#3366cc")
    assert calls == ["detr", "sam"]
    assert result["composition"]["mask_mode"] == "sam"
    assert result["segmentation"]["model"] == "SAM 2"
    assert result["original_artifact"] == "original.jpg"


def test_manual_api_accepts_normalized_strokes_and_defaults_to_sam(tmp_path, monkeypatch):
    monkeypatch.setenv("AI_WORKER_TOKEN", "test-secret")
    monkeypatch.setenv("AI_WORKER_ARTIFACT_ROOT", str(tmp_path / "artifacts"))
    data = BytesIO()
    Image.new("RGB", (64, 64), (80, 90, 100)).save(data, format="PNG")
    client = TestClient(app)
    response = client.post("/v1/vision/simulate", headers={"X-Worker-Token": "test-secret"},
        files={"file": ("room.png", data.getvalue(), "image/png")},
        data={"color": "#3366cc", "mask_mode": "manual", "mask_strokes": json.dumps([stroke()])})
    assert response.status_code == 200
    assert response.json()["segmentation"]["provenance"] == "manual_user_mask"
    original = response.json()["original_artifact"]
    assert client.get(f'/v1/jobs/{response.json()["job_id"]}/artifacts/{original}',
        headers={"X-Worker-Token": "test-secret"}).status_code == 200

    captured = {}
    def legacy_simulate(*args, **kwargs):
        captured.update(kwargs)
        return {"status": "completed"}
    monkeypatch.setattr(vision_pipeline, "simulate", legacy_simulate)
    legacy = client.post("/v1/vision/simulate", headers={"X-Worker-Token": "test-secret"},
        files={"file": ("room.png", data.getvalue(), "image/png")}, data={"color": "#3366cc"})
    assert legacy.status_code == 200
    assert captured.get("mask_mode", "sam") == "sam"


@pytest.mark.parametrize("form", [
    {"mask_mode": "manual"}, {"mask_mode": "invented"},
    {"mask_mode": "manual", "mask_strokes": "{"},
    {"mask_mode": "manual", "mask_strokes": "[]"},
])
def test_manual_api_rejects_bad_contract_before_models(form, monkeypatch):
    monkeypatch.setenv("AI_WORKER_TOKEN", "test-secret")
    data = BytesIO()
    Image.new("RGB", (16, 16), "gray").save(data, format="PNG")
    response = TestClient(app).post("/v1/vision/simulate", headers={"X-Worker-Token": "test-secret"},
        files={"file": ("room.png", data.getvalue(), "image/png")}, data={"color": "#3366cc", **form})
    assert response.status_code == 422
