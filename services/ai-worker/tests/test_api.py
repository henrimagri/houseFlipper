import os
from io import BytesIO

from PIL import Image
import numpy as np

from fastapi.testclient import TestClient

from app.main import app


os.environ["AI_WORKER_TOKEN"] = "test-secret"
client = TestClient(app)


def test_worker_requires_service_credential() -> None:
    assert client.get("/health").status_code == 401
    assert client.get("/health", headers={"X-Worker-Token": "test-secret"}).status_code == 200


def test_reconstruction_rejects_inadequate_capture() -> None:
    response = client.post(
        "/v1/3d/reconstruct",
        headers={"X-Worker-Token": "test-secret"},
        files=[("files", ("one.jpg", b"x", "image/jpeg")), ("files", ("two.jpg", b"x", "image/jpeg"))],
        data={"overlap_acknowledged": "false"},
    )
    assert response.status_code == 422
    assert "sobrepostas" in response.json()["detail"]


def test_vision_returns_model_artifacts_from_neural_pipeline(monkeypatch) -> None:
    buffer = BytesIO()
    Image.new("RGB", (32, 32), "white").save(buffer, format="JPEG")
    monkeypatch.setattr(
        "app.main.vision_pipeline.analyze",
        lambda path, points: {
            "job_id": "00000000-0000-0000-0000-000000000001",
            "status": "completed",
            "depth": {"artifact": "depth.png", "model": "Depth Anything V2 Small"},
            "segmentation": {"artifact": "mask.png", "model": "SAM 2.1 Hiera Tiny"},
            "detection": {"model": "DETR ResNet-50", "objects": [{"label": "couch", "confidence": 0.98}]},
        },
    )
    response = client.post(
        "/v1/vision/analyze",
        headers={"X-Worker-Token": "test-secret"},
        files={"file": ("room.jpg", buffer.getvalue(), "image/jpeg")},
    )
    assert response.status_code == 200
    assert response.json()["depth"]["artifact"] == "depth.png"
    assert response.json()["segmentation"]["artifact"] == "mask.png"
    assert response.json()["detection"]["objects"][0]["label"] == "couch"


def test_photo_simulation_preserves_unmasked_pixels_and_source_luminance() -> None:
    from app.vision import VisionPipeline

    source = Image.new("RGB", (8, 4), (80, 80, 80))
    mask = Image.new("L", source.size, 0)
    for x in range(4):
        for y in range(4):
            mask.putpixel((x, y), 255)

    output = VisionPipeline.compose_paint(source, mask, "#d8b48a", opacity=1.0)

    assert output.getpixel((7, 2)) == source.getpixel((7, 2))
    painted = output.getpixel((1, 2))
    assert painted[0] > painted[2]
    assert 65 <= sum(painted) / 3 <= 100


def test_refine_surface_mask_keeps_only_component_selected_by_positive_point() -> None:
    from app.vision import VisionPipeline

    mask = np.zeros((40, 60), dtype=np.uint8)
    mask[5:30, 5:30] = 255
    mask[4:12, 48:56] = 255

    refined = VisionPipeline.refine_surface_mask(mask, [[15, 15], [52, 8]], [1, 0])

    assert refined[15, 15] == 255
    assert refined[8, 52] == 0


def test_detected_furniture_becomes_negative_prompt_unless_user_selected_it() -> None:
    from app.vision import VisionPipeline

    objects = [
        {"label": "couch", "confidence": 0.99, "box_xyxy": [10, 20, 90, 80]},
        {"label": "potted plant", "confidence": 0.96, "box_xyxy": [120, 20, 160, 100]},
    ]

    exclusions = VisionPipeline.automatic_exclusion_points(objects, [{"x": 30, "y": 40, "label": 1}])

    assert len(exclusions) == 1
    assert exclusions[0]["source"] == "detr:potted plant"
    assert exclusions[0]["label"] == 0


def test_simulation_endpoint_validates_color() -> None:
    buffer = BytesIO()
    Image.new("RGB", (16, 16), "white").save(buffer, format="JPEG")
    response = client.post(
        "/v1/vision/simulate",
        headers={"X-Worker-Token": "test-secret"},
        files={"file": ("room.jpg", buffer.getvalue(), "image/jpeg")},
        data={"color": "red"},
    )
    assert response.status_code == 422
