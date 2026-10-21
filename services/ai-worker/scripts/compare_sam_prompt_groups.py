"""Offline comparison of legacy per-point objects versus one joint SAM 2 prompt.

No weights are downloaded. This is an inference diagnostic, not a quality score.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import math
import os
from pathlib import Path
import sys
import time

os.environ["HF_HUB_OFFLINE"] = "1"
os.environ["TRANSFORMERS_OFFLINE"] = "1"
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

import numpy as np
from PIL import Image, ImageDraw
import torch
import transformers
from transformers import Sam2Model, Sam2Processor

from app.vision import SAM_MODEL_ID, VisionPipeline, load_image


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--image", required=True, type=Path)
    parser.add_argument("--points", required=True, type=Path, help="JSON list of pixel x/y and label 0 or 1")
    parser.add_argument("--output", required=True, type=Path, help="New local directory; existing paths are not overwritten")
    parser.add_argument("--device", choices=["cpu", "cuda"], default="cpu")
    parser.add_argument("--threads", type=int, default=4, choices=range(1, 17))
    args = parser.parse_args()
    image = load_image(args.image)
    raw_points = json.loads(args.points.read_text(encoding="utf-8"))
    if not isinstance(raw_points, list) or not 1 <= len(raw_points) <= 8:
        parser.error("Supply 1..8 points for this bounded diagnostic.")
    points, labels = [], []
    for point in raw_points:
        if not isinstance(point, dict) or point.get("label") not in (0, 1):
            parser.error("Each point needs x, y and label 0 or 1.")
        x, y = point.get("x"), point.get("y")
        if (not isinstance(x, (int, float)) or not isinstance(y, (int, float))
                or not math.isfinite(x) or not math.isfinite(y)
                or not 0 <= x <= image.width - 1 or not 0 <= y <= image.height - 1):
            parser.error("Points must lie inside the EXIF-oriented image, in pixel coordinates.")
        points.append([float(x), float(y)])
        labels.append(int(point["label"]))
    if 1 not in labels:
        parser.error("At least one positive point is required.")
    if args.device == "cuda" and not torch.cuda.is_available():
        parser.error("CUDA requested but unavailable; no CPU fallback is hidden.")
    args.output.mkdir(parents=True, exist_ok=False)
    torch.set_num_threads(args.threads)
    source_hash = hashlib.sha256(args.image.read_bytes()).hexdigest()
    report = {
        "model": SAM_MODEL_ID, "transformers_version": transformers.__version__,
        "offline_only": True, "source_sha256": source_hash,
        "dimensions": {"width": image.width, "height": image.height},
        "points": raw_points, "device": args.device, "threads": args.threads,
        "order": ["legacy", "grouped"], "cases": {}, "visual_quality_validated": False,
        "limitations": ["The first inference is cold; timing order is not a controlled performance benchmark.",
                        "Point inclusion/exclusion and mask area do not prove semantic segmentation quality.",
                        "VRAM counters are this process's PyTorch allocations/reservations, not total host GPU usage.",
                        "Only SAM 2 is loaded; no DETR-generated exclusions are added."],
    }
    exit_code = 0
    try:
        started = time.perf_counter()
        processor = Sam2Processor.from_pretrained(SAM_MODEL_ID, local_files_only=True)
        model = Sam2Model.from_pretrained(
            SAM_MODEL_ID, local_files_only=True,
            dtype=torch.float16 if args.device == "cuda" else torch.float32,
        ).to(args.device).eval()
        report["load_seconds"] = round(time.perf_counter() - started, 4)
        for name in ("legacy", "grouped"):
            point_input = [[[point] for point in points]] if name == "legacy" else [[points]]
            label_input = [[[label] for label in labels]] if name == "legacy" else [[labels]]
            if args.device == "cuda":
                torch.cuda.synchronize()
                torch.cuda.reset_peak_memory_stats()
            started = time.perf_counter()
            inputs = processor(images=image, input_points=point_input, input_labels=label_input, return_tensors="pt").to(args.device)
            if args.device == "cuda":
                inputs["pixel_values"] = inputs["pixel_values"].half()
            with torch.inference_mode():
                predicted = model(**inputs, multimask_output=False).pred_masks
            masks = processor.post_process_masks(predicted.float().cpu(), inputs["original_sizes"])[0]
            raw = masks.squeeze(1).any(dim=0).numpy().astype(np.uint8) * 255
            refined = VisionPipeline.refine_surface_mask(raw, points, labels)
            if args.device == "cuda":
                torch.cuda.synchronize()
            case = {
                "input_points_shape": list(inputs["input_points"].shape),
                "input_labels_shape": list(inputs["input_labels"].shape),
                "output_masks_shape": list(masks.shape),
                "seconds": round(time.perf_counter() - started, 4),
                "torch_peak_allocated_vram_mb": round(torch.cuda.max_memory_allocated() / 2**20, 3) if args.device == "cuda" else 0,
                "torch_peak_reserved_vram_mb": round(torch.cuda.max_memory_reserved() / 2**20, 3) if args.device == "cuda" else 0,
                "raw_coverage": float(np.count_nonzero(raw)) / raw.size,
                "refined_coverage": float(np.count_nonzero(refined)) / refined.size,
                "point_checks": [{"x": x, "y": y, "label": label,
                                  "raw_selected": bool(raw[round(y), round(x)]),
                                  "refined_selected": bool(refined[round(y), round(x)])}
                                 for (x, y), label in zip(points, labels)],
            }
            Image.fromarray(raw).save(args.output / f"{name}-raw-mask.png")
            Image.fromarray(refined).save(args.output / f"{name}-mask.png")
            overlay = image.convert("RGBA")
            tint = Image.new("RGBA", image.size, (24, 201, 216, 0))
            tint.putalpha(Image.fromarray((refined * 0.42).astype(np.uint8)))
            overlay.alpha_composite(tint)
            draw = ImageDraw.Draw(overlay)
            for (x, y), label in zip(points, labels):
                draw.ellipse((x - 6, y - 6, x + 6, y + 6), outline="lime" if label else "red", width=3)
            overlay.convert("RGB").save(args.output / f"{name}-overlay.png")
            report["cases"][name] = case
            del inputs, predicted, masks
        report["status"] = "processed_not_visually_validated"
    except Exception as exception:
        report["status"] = "failed"
        report["error_type"] = type(exception).__name__
        report["error"] = str(exception)[:1000]
        exit_code = 1
    report["original_unchanged"] = hashlib.sha256(args.image.read_bytes()).hexdigest() == source_hash
    report["artifact_hashes"] = {path.name: hashlib.sha256(path.read_bytes()).hexdigest()
                                 for path in args.output.glob("*.png")}
    (args.output / "report.json").write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding="utf-8")
    print(json.dumps({"status": report["status"], "cases_completed": list(report["cases"]), "original_unchanged": report["original_unchanged"]}))
    return exit_code


if __name__ == "__main__":
    raise SystemExit(main())
