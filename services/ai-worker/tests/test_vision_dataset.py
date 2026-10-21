import json
from pathlib import Path

import pytest
from PIL import Image

from training.vision.prepare_dataset import prepare, split_for_scene, validate_record


def fixture_files(root: Path) -> None:
    Image.new("RGB", (16, 12), "white").save(root / "room.png")
    Image.new("L", (16, 12), 255).save(root / "wall.png")


def test_dataset_requires_explicit_training_authorization(tmp_path) -> None:
    fixture_files(tmp_path)
    record = {"scene_id": "property-1-room-1", "image": "room.png", "room_label": "living", "license": "owned", "training_authorized": False}
    with pytest.raises(ValueError, match="autorização"):
        validate_record(record, tmp_path)


def test_split_is_stable_and_keeps_same_scene_together() -> None:
    assert split_for_scene("property-42-living") == split_for_scene("property-42-living")


def test_prepare_validates_masks_and_writes_auditable_card(tmp_path) -> None:
    fixture_files(tmp_path)
    record = {
        "scene_id": "property-1-room-1", "image": "room.png", "room_label": "living",
        "license": "project-owned", "training_authorized": True, "masks": {"wall": "wall.png"},
    }
    manifest = tmp_path / "manifest.jsonl"
    manifest.write_text(json.dumps(record) + "\n", encoding="utf-8")
    output = tmp_path / "prepared"
    counts = prepare(manifest, tmp_path, output)
    card = json.loads((output / "dataset-card.json").read_text(encoding="utf-8"))
    assert sum(counts.values()) == 1
    assert card["split_unit"] == "scene_id"
    assert card["training_authorization_required"] is True
    assert "wall" in card["surface_classes"]
