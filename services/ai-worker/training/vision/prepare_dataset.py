from __future__ import annotations

import argparse
import hashlib
import json
from pathlib import Path
from typing import Any

from PIL import Image

ALLOWED_ROOMS = {"living", "bedroom", "kitchen", "bathroom", "office", "hallway", "garage", "facade", "outdoor"}
ALLOWED_SURFACES = {"wall", "floor", "ceiling", "door", "window"}


def _inside(root: Path, value: str) -> Path:
    path = (root / value).resolve()
    if root.resolve() not in path.parents:
        raise ValueError(f"Caminho fora da raiz autorizada: {value}")
    return path


def validate_record(record: dict[str, Any], root: Path) -> dict[str, Any]:
    if record.get("training_authorized") is not True:
        raise ValueError("Imagem sem autorização específica para treinamento.")
    if not record.get("license") or not record.get("scene_id"):
        raise ValueError("Licença e scene_id são obrigatórios.")
    if record.get("room_label") not in ALLOWED_ROOMS:
        raise ValueError("Classe de ambiente inválida.")
    image_path = _inside(root, str(record.get("image", "")))
    if not image_path.is_file():
        raise ValueError(f"Imagem inexistente: {image_path.name}")
    with Image.open(image_path) as source:
        source_size = source.size
    masks = record.get("masks") or {}
    if not isinstance(masks, dict) or any(label not in ALLOWED_SURFACES for label in masks):
        raise ValueError("Máscaras contêm classes arquitetônicas inválidas.")
    for label, relative_path in masks.items():
        mask_path = _inside(root, str(relative_path))
        with Image.open(mask_path) as mask:
            if mask.size != source_size:
                raise ValueError(f"Máscara {label} não corresponde às dimensões da imagem.")
    normalized = dict(record)
    normalized["image"] = image_path.relative_to(root.resolve()).as_posix()
    normalized["masks"] = {label: _inside(root, str(path)).relative_to(root.resolve()).as_posix() for label, path in masks.items()}
    return normalized


def split_for_scene(scene_id: str) -> str:
    bucket = int(hashlib.sha256(scene_id.encode("utf-8")).hexdigest()[:8], 16) % 100
    return "train" if bucket < 70 else "validation" if bucket < 85 else "test"


def prepare(manifest: Path, root: Path, output: Path) -> dict[str, int]:
    records = [validate_record(json.loads(line), root) for line in manifest.read_text(encoding="utf-8").splitlines() if line.strip()]
    output.mkdir(parents=True, exist_ok=True)
    counts = {"train": 0, "validation": 0, "test": 0}
    handles = {name: (output / f"{name}.jsonl").open("w", encoding="utf-8") for name in counts}
    try:
        for record in records:
            split = split_for_scene(str(record["scene_id"]))
            handles[split].write(json.dumps(record, ensure_ascii=False) + "\n")
            counts[split] += 1
    finally:
        for handle in handles.values():
            handle.close()
    (output / "dataset-card.json").write_text(json.dumps({
        "counts": counts,
        "split_unit": "scene_id",
        "training_authorization_required": True,
        "room_classes": sorted(ALLOWED_ROOMS),
        "surface_classes": sorted(ALLOWED_SURFACES),
    }, ensure_ascii=False, indent=2), encoding="utf-8")
    return counts


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--manifest", type=Path, required=True)
    parser.add_argument("--root", type=Path, required=True)
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()
    print(json.dumps(prepare(args.manifest, args.root.resolve(), args.output), ensure_ascii=False))
