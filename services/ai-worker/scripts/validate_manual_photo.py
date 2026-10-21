"""Validate saved manual photo edits without regenerating or modifying images."""
from __future__ import annotations

import argparse
import hashlib
import json
from pathlib import Path

import numpy as np
from PIL import Image, ImageOps


def validate(source: Path, directory: Path) -> dict:
    with Image.open(source) as image:
        original = np.asarray(ImageOps.exif_transpose(image).convert("RGB"))
    with Image.open(directory / "original.png") as image:
        saved = np.asarray(image.convert("RGB"))
    with Image.open(directory / "simulation.png") as image:
        edited = np.asarray(image.convert("RGB"))
    with Image.open(directory / "mask.png") as image:
        mask = np.asarray(image.convert("L")) > 0
    if original.shape != edited.shape or original.shape != saved.shape or mask.shape != original.shape[:2]:
        raise ValueError("Source, mask and result dimensions differ.")
    changed = np.any(original != edited, axis=2)
    result = json.loads((directory / "result.json").read_text(encoding="utf-8"))
    checks = {
        "original_pixels_preserved": bool(np.array_equal(original, saved)),
        "outside_mask_pixels_preserved": not bool(np.any(changed & ~mask)),
        "selected_pixels_changed": bool(np.any(changed & mask)),
        "manual_provenance": result["segmentation"].get("provenance") == "manual_user_mask",
        "no_segmentation_model": result["segmentation"].get("model_used") is False,
    }
    artifacts = [directory / name for name in ("original.png", "mask.png", "simulation.png", "comparison.png", "result.json")]
    return {
        "checks": checks, "passed": all(checks.values()),
        "source_name": source.name, "source_sha256": hashlib.sha256(source.read_bytes()).hexdigest(),
        "image_size": [int(original.shape[1]), int(original.shape[0])],
        "selected_pixels": int(mask.sum()), "changed_pixels": int(changed.sum()),
        "changed_pixels_outside_mask": int((changed & ~mask).sum()),
        "coverage": float(mask.mean()),
        "artifacts": {path.name: {"bytes": path.stat().st_size, "sha256": hashlib.sha256(path.read_bytes()).hexdigest()} for path in artifacts},
        "limitations": "Verifies localized 2D editing, not surface classification, physical paint fidelity or 3D reconstruction.",
    }


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--source", type=Path, required=True)
    parser.add_argument("--result-dir", type=Path, required=True)
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()
    report = validate(args.source, args.result_dir)
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(report, indent=2) + "\n", encoding="utf-8")
    print(json.dumps({key: report[key] for key in ("passed", "checks", "selected_pixels", "changed_pixels", "changed_pixels_outside_mask", "coverage")}))
    raise SystemExit(0 if report["passed"] else 1)
