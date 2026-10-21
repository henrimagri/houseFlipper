from io import BytesIO
import json

import numpy as np
import pytest
from PIL import Image, ImageDraw
from fastapi.testclient import TestClient

from app.main import app
from app.vision import VisionPipeline, load_image, vision_pipeline


def png(image):
    output = BytesIO()
    image.save(output, format="PNG")
    return output.getvalue()


@pytest.fixture
def api(monkeypatch, tmp_path):
    monkeypatch.setenv("AI_WORKER_TOKEN", "test-secret")
    monkeypatch.setenv("AI_WORKER_ARTIFACT_ROOT", str(tmp_path / "artifacts"))
    def forbidden(*args, **kwargs):
        pytest.fail("Unexpected model invocation")
    for name in ("analyze", "depth", "_detect_locked", "_load_detection", "_load_depth", "_load_sam"):
        monkeypatch.setattr(vision_pipeline, name, forbidden)
    return TestClient(app), {"X-Worker-Token": "test-secret"}


def test_segment_endpoint_generates_sam_only_mask(api, monkeypatch, tmp_path):
    client, headers = api
    seen = []
    def segment(image, output, points, *, preserve_details=False):
        assert preserve_details is True
        seen.extend(points)
        Image.new("L", image.size, 255).save(output / "mask.png")
        image.save(output / "segmentation-overlay.png")
        return {"artifact": "mask.png", "overlay_artifact": "segmentation-overlay.png",
                "dimensions": {"width": image.width, "height": image.height},
                "prompt_points": [[10, 10], [20, 20]], "prompt_labels": [1, 0]}
    monkeypatch.setattr(vision_pipeline, "_segment_locked", segment)
    response = client.post("/v1/vision/segment", headers=headers,
        files={"file": ("room.png", png(Image.new("RGB", (64, 48), "gray")), "image/png")},
        data={"points": json.dumps([{"x": 10, "y": 10, "label": 1}, {"x": 20, "y": 20, "label": 0}])})
    assert response.status_code == 200
    body = response.json()
    assert seen == [{"x": 10, "y": 10, "label": 1}, {"x": 20, "y": 20, "label": 0}]
    assert body["segmentation"]["provenance"] == "sam2_prompted_mask"
    assert body["segmentation"]["model_used"] is True
    assert "depth" not in body and "detection" not in body
    assert body["status"] == "completed"
    assert client.get(f'/v1/jobs/{body["job_id"]}/artifacts/mask.png', headers=headers).status_code == 200


@pytest.mark.parametrize("points", [None, "{", "[]", "null", '[{"x":10,"y":10,"label":0}]',
    '[{"x":100,"y":10,"label":1}]', '[{"x":NaN,"y":10,"label":1}]',
    '[{"x":true,"y":10,"label":1}]', '[{"x":10,"y":10,"label":2}]'])
def test_segment_rejects_invalid_prompts_without_models(api, points):
    client, headers = api
    response = client.post("/v1/vision/segment", headers=headers,
        files={"file": ("room.png", png(Image.new("RGB", (64, 48), "gray")), "image/png")},
        data={} if points is None else {"points": points})
    assert response.status_code == 422


@pytest.mark.parametrize("finish", ["matte", "satin", "semigloss"])
def test_refined_mask_keeps_base_adds_and_erases_without_models(api, tmp_path, finish):
    client, headers = api
    pixels = np.tile(np.arange(64, dtype=np.uint8)[None, :, None] * 3 + 40, (48, 1, 3))
    base = Image.new("L", (64, 48), 0)
    ImageDraw.Draw(base).rectangle((5, 5, 30, 42), fill=255)
    strokes = [
        {"mode": "paint", "radius": 0.06, "points": [{"x": 0.8, "y": 0.5}]},
        {"mode": "erase", "radius": 0.06, "points": [{"x": 0.2, "y": 0.5}]},
    ]
    response = client.post("/v1/vision/simulate", headers=headers, files={
        "file": ("room.png", png(Image.fromarray(pixels)), "image/png"),
        "base_mask": ("mask.png", png(base), "image/png"),
    }, data={"color": "#5588bb", "mask_mode": "refined", "mask_strokes": json.dumps(strokes), "finish": finish})
    assert response.status_code == 200, response.text
    body = response.json()
    output = tmp_path / "artifacts" / body["job_id"]
    mask = np.asarray(Image.open(output / "mask.png"))
    painted = np.asarray(Image.open(output / "simulation.png"))
    assert mask[10, 10] == 255  # Original neural area retained.
    assert mask[24, 50] == 255  # Brush adds new area.
    assert mask[24, 13] == 0  # Eraser removes an interior area.
    assert np.array_equal(painted[mask == 0], pixels[mask == 0])
    assert body["segmentation"]["provenance"] == "provided_mask_with_manual_refinement"
    assert body["segmentation"]["model_used"] is False
    assert body["composition"]["finish"] == finish
    assert body["composition"]["physical_finish_simulation"] is False


def test_finish_is_conservative_and_preserves_outside_pixels():
    row = np.linspace(40, 230, 96).astype(np.uint8)
    image = Image.fromarray(np.tile(row[None, :, None], (48, 1, 3)))
    mask = Image.new("L", image.size, 0)
    ImageDraw.Draw(mask).rectangle((8, 8, 88, 40), fill=255)
    results = [np.asarray(VisionPipeline.compose_paint(image, mask, "#2266bb", finish=finish, refine_mask=False))
               for finish in ("matte", "satin", "semigloss")]
    outside = np.asarray(mask) == 0
    for result in results:
        assert np.array_equal(result[outside], np.asarray(image)[outside])
    assert np.any(results[0] != results[1]) and np.any(results[1] != results[2])
    assert np.max(np.abs(results[2].astype(int) - results[0].astype(int))) <= 35


@pytest.mark.parametrize("base_data, mime", [(b"not PNG", "image/png"),
    (png(Image.new("L", (64, 48), 0)), "image/png"),
    (png(Image.new("RGB", (64, 48), "white")), "image/png"),
    (png(Image.new("L", (32, 48), 255)), "image/png"),
    (png(Image.new("L", (64, 48), 255)), "image/jpeg"),
    (b"x" * (10 * 1024 * 1024 + 1), "image/png")], ids=["invalid-bytes", "empty-mask", "rgb", "dimensions", "mime", "size-limit"])
def test_refined_mask_rejects_invalid_upload(api, base_data, mime):
    client, headers = api
    response = client.post("/v1/vision/simulate", headers=headers, files={
        "file": ("room.png", png(Image.new("RGB", (64, 48), "gray")), "image/png"),
        "base_mask": ("mask.png", base_data, mime),
    }, data={"color": "#5588bb", "mask_mode": "refined"})
    assert response.status_code == 422


def test_refined_mode_matches_exif_orientation_and_preserves_input_files(tmp_path, monkeypatch):
    monkeypatch.setenv("AI_WORKER_ARTIFACT_ROOT", str(tmp_path / "artifacts"))
    photo = tmp_path / "rotated.jpg"
    image = Image.new("RGB", (80, 40), (90, 100, 110))
    exif = Image.Exif()
    exif[274] = 6
    image.save(photo, exif=exif)
    mask_path = tmp_path / "base.png"
    Image.new("L", (40, 80), 255).save(mask_path)
    before = (photo.read_bytes(), mask_path.read_bytes())
    result = VisionPipeline().simulate(photo, "#5588bb", mask_mode="refined", base_mask_path=mask_path, mask_strokes=[])
    assert result["segmentation"]["dimensions"] == {"width": 40, "height": 80}
    assert (photo.read_bytes(), mask_path.read_bytes()) == before
    mask = Image.open(tmp_path / "artifacts" / result["job_id"] / "mask.png")
    assert np.array_equal(np.asarray(mask), np.asarray(Image.open(mask_path)))


def test_interactive_mask_refinement_preserves_holes_and_thin_edges():
    mask = np.zeros((48, 64), dtype=np.uint8)
    mask[8:40, 8:30] = 255
    mask[20:22, 18:20] = 0  # Small excluded architectural detail.
    mask[10, 30:50] = 255  # One-pixel connected edge must survive.
    mask[42:44, 55:57] = 255  # Unselected disconnected island is removed.
    refined = VisionPipeline.refine_surface_mask(mask, [[12, 12], [19, 21]], [1, 0], preserve_details=True)
    assert refined[21, 19] == 0
    assert refined[10, 45] == 255
    assert refined[43, 56] == 0
    assert not np.any((refined > 0) & (mask == 0))


@pytest.mark.parametrize("strokes", [None, "[]"])
def test_refined_endpoint_preserves_exact_base_without_strokes(api, tmp_path, strokes):
    client, headers = api
    base = Image.new("L", (64, 48), 0)
    ImageDraw.Draw(base).rectangle((5, 5, 25, 40), fill=255)
    body = {"color": "#5588bb", "mask_mode": "refined"}
    if strokes is not None:
        body["mask_strokes"] = strokes
    response = client.post("/v1/vision/simulate", headers=headers, files={
        "file": ("room.png", png(Image.new("RGB", base.size, "gray")), "image/png"),
        "base_mask": ("mask.png", png(base), "image/png"),
    }, data=body)
    assert response.status_code == 200
    mask = Image.open(tmp_path / "artifacts" / response.json()["job_id"] / "mask.png")
    assert np.array_equal(np.asarray(mask), np.asarray(base))


@pytest.mark.parametrize("data, send_base", [
    ({"mask_mode": "refined"}, False),
    ({"mask_mode": "sam"}, True),
    ({"mask_mode": "refined", "finish": "invented"}, True),
    ({"mask_mode": "refined", "points": '[{"x":10,"y":10,"label":1}]'}, True),
    ({"mask_mode": "refined", "mask_strokes": "null"}, True),
])
def test_refined_contract_rejects_ambiguous_or_invalid_input(api, data, send_base):
    client, headers = api
    files = {"file": ("room.png", png(Image.new("RGB", (64, 48), "gray")), "image/png")}
    if send_base:
        files["base_mask"] = ("mask.png", png(Image.new("L", (64, 48), 255)), "image/png")
    response = client.post("/v1/vision/simulate", headers=headers, files=files, data={"color": "#5588bb", **data})
    assert response.status_code == 422


def test_refined_erasing_all_base_rejects_without_creating_result(api, tmp_path):
    client, headers = api
    base = Image.new("L", (64, 48), 0)
    ImageDraw.Draw(base).rectangle((8, 8, 12, 12), fill=255)
    response = client.post("/v1/vision/simulate", headers=headers, files={
        "file": ("room.png", png(Image.new("RGB", base.size, "gray")), "image/png"),
        "base_mask": ("mask.png", png(base), "image/png"),
    }, data={"color": "#5588bb", "mask_mode": "refined", "mask_strokes": json.dumps([
        {"mode": "erase", "radius": 0.15, "points": [{"x": 10 / 63, "y": 10 / 47}]},
    ])})
    assert response.status_code == 422
    assert not list(tmp_path.rglob("result.json"))


def test_segment_checks_pixel_limit_before_decode_or_model(api, monkeypatch):
    from app import vision
    monkeypatch.setattr(vision, "MAX_PIXELS", 100)
    client, headers = api
    response = client.post("/v1/vision/segment", headers=headers,
        files={"file": ("room.png", png(Image.new("RGB", (16, 16), "gray")), "image/png")},
        data={"points": '[{"x":5,"y":5,"label":1}]'})
    assert response.status_code == 422


@pytest.mark.parametrize("color, expected", [("#204060", (32, 64, 96)), ("#000000", (0, 0, 0)), ("#ffffff", (255, 255, 255))])
def test_refined_uniform_bright_wall_uses_chosen_color(tmp_path, monkeypatch, color, expected):
    monkeypatch.setenv("AI_WORKER_ARTIFACT_ROOT", str(tmp_path / "artifacts"))
    photo, base = tmp_path / "room.png", tmp_path / "mask.png"
    Image.new("RGB", (48, 48), (230, 230, 230)).save(photo)
    Image.new("L", (48, 48), 255).save(base)
    result = VisionPipeline().simulate(photo, color, opacity=1, mask_mode="refined", base_mask_path=base)
    painted = Image.open(tmp_path / "artifacts" / result["job_id"] / "simulation.png")
    assert np.max(np.abs(np.asarray(painted.getpixel((24, 24))) - expected)) <= 1
    assert result["composition"]["color_method"] == "selected_surface_median_illumination"


def test_surface_normalization_preserves_shadow_gradient_and_texture():
    row = np.linspace(80, 200, 121).astype(np.uint8)
    image = Image.fromarray(np.tile(row[None, :, None], (32, 1, 3)))
    mask = Image.new("L", image.size, 255)
    output = np.asarray(VisionPipeline.compose_paint(image, mask, "#406080", opacity=1,
        normalize_surface=True, refine_mask=False))
    assert np.all(np.diff(output[16, :, 1].astype(int)) >= 0)
    assert output[16, 0, 1] < output[16, 60, 1] < output[16, 120, 1]
    assert abs(int(output[16, 60, 1]) - 96) <= 1
    assert abs(float(output[16, 0, 1]) / output[16, 60, 1] - 80 / 140) < 0.02


def test_normalization_uses_only_selected_pixels_and_keeps_exterior_exact():
    mask = Image.new("L", (96, 64), 0)
    ImageDraw.Draw(mask).rectangle((36, 16, 59, 47), fill=255)
    outcomes = []
    for exterior in (0, 255):
        image = Image.new("RGB", mask.size, (exterior,) * 3)
        ImageDraw.Draw(image).rectangle((36, 16, 59, 47), fill=(220, 220, 220))
        painted = np.asarray(VisionPipeline.compose_paint(image, mask, "#204060", opacity=1,
            normalize_surface=True, refine_mask=False))
        outside = np.asarray(mask) == 0
        assert np.array_equal(painted[outside], np.asarray(image)[outside])
        outcomes.append(painted[np.asarray(mask) > 0])
    assert np.array_equal(outcomes[0], outcomes[1])


def test_normalized_finishes_only_change_observed_highlights():
    row = np.linspace(60, 230, 96).astype(np.uint8)
    image = Image.fromarray(np.tile(row[None, :, None], (32, 1, 3)))
    mask = Image.new("L", image.size, 255)
    outputs = [np.asarray(VisionPipeline.compose_paint(image, mask, "#305080", opacity=1,
        normalize_surface=True, refine_mask=False, finish=finish)) for finish in ("matte", "satin", "semigloss")]
    assert np.array_equal(outputs[0][:, :48], outputs[2][:, :48])
    assert np.any(outputs[0][:, 65:] != outputs[1][:, 65:])
    assert np.any(outputs[1][:, 65:] != outputs[2][:, 65:])
    assert np.max(np.abs(outputs[2].astype(int) - outputs[0].astype(int))) <= 31


def test_normalized_black_scene_does_not_invent_illumination():
    image = Image.new("RGB", (32, 32), "black")
    mask = Image.new("L", image.size, 255)
    output = VisionPipeline.compose_paint(image, mask, "#ffffff", opacity=1,
        normalize_surface=True, refine_mask=False, finish="semigloss")
    assert np.max(np.asarray(output)) == 0
