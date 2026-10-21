from __future__ import annotations

import argparse
import json
import math
import sys
from pathlib import Path

import numpy as np
from PIL import Image

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from app.calibrated_depth_fusion import read_colmap_text
from app.calibrated_stereo import StereoConfig, select_stereo_pairs
from app.multiview_stereo import PlaneSweepConfig, plane_sweep_depth


def main() -> None:
    parser = argparse.ArgumentParser(description="Experimento CPU limitado de plane sweep em três câmeras reais.")
    parser.add_argument("--images", type=Path, required=True)
    parser.add_argument("--model", type=Path, required=True)
    parser.add_argument("--development-report", type=Path, required=True)
    parser.add_argument("--output", type=Path, required=True)
    parser.add_argument("--width", type=int, default=320)
    parser.add_argument("--planes", type=int, default=48)
    args = parser.parse_args()

    cameras, views, sparse_points = read_colmap_text(args.model.resolve())
    previous = json.loads(args.development_report.read_text(encoding="utf-8"))
    allowed = set(previous["split_isolation"]["development_views"])
    development = [view for view in views if view.name in allowed and (args.images / view.name).is_file()]
    pairs, selection = select_stereo_pairs(
        development, cameras, args.images.resolve(),
        StereoConfig(maximum_pairs=24, maximum_pairs_per_view=4, selection_strategy="coverage", minimum_angle_degrees=0.0),
    )
    adjacency: dict[str, list[tuple[int, str]]] = {}
    for pair in pairs:
        adjacency.setdefault(pair.left_name, []).append((pair.shared_points, pair.right_name))
        adjacency.setdefault(pair.right_name, []).append((pair.shared_points, pair.left_name))
    eligible = [(sum(value for value, _ in neighbours), name, neighbours) for name, neighbours in adjacency.items() if len(neighbours) >= 2]
    if not eligible:
        raise RuntimeError("Nenhuma câmera de referência possui duas câmeras de suporte elegíveis.")
    _, reference_name, neighbours = max(eligible)
    support_names = [name for _, name in sorted(neighbours, reverse=True)[:3]]
    by_name = {view.name: view for view in development}
    reference = by_name[reference_name]
    supports = [by_name[name] for name in support_names]

    # Even point IDs define the bounded search range; odd IDs remain independent validation anchors.
    calibration_depths = []
    for _, _, point_id in reference.observations:
        if point_id % 2 or point_id not in sparse_points:
            continue
        z = float((reference.rotation @ sparse_points[point_id] + reference.translation)[2])
        if math.isfinite(z) and z > 0.05:
            calibration_depths.append(z)
    if len(calibration_depths) < 16:
        raise RuntimeError("Âncoras SfM insuficientes para delimitar o plane sweep.")
    minimum_depth = max(0.08, float(np.percentile(calibration_depths, 2)) * 0.90)
    maximum_depth = float(np.percentile(calibration_depths, 98)) * 1.10
    image_arrays = {
        view.name: np.asarray(Image.open(args.images / view.name).convert("RGB"), dtype=np.uint8)
        for view in [reference, *supports]
    }
    result = plane_sweep_depth(
        reference, supports, cameras, image_arrays,
        minimum_depth=minimum_depth,
        maximum_depth=maximum_depth,
        config=PlaneSweepConfig(
            width=max(160, min(args.width, 640)),
            depth_planes=max(16, min(args.planes, 96)),
            maximum_support_views=3,
            minimum_support_views=2,
            maximum_seconds=20.0,
        ),
    )
    height, width = result.depth.shape
    camera = cameras[reference.camera_id]
    relative_errors = []
    for x, y, point_id in reference.observations:
        if point_id % 2 == 0 or point_id not in sparse_points:
            continue
        expected = float((reference.rotation @ sparse_points[point_id] + reference.translation)[2])
        px = min(width - 1, max(0, int(round(x * width / camera.width))))
        py = min(height - 1, max(0, int(round(y * height / camera.height))))
        measured = float(result.depth[py, px])
        if expected > 0.05 and math.isfinite(measured):
            relative_errors.append(abs(measured - expected) / expected)
    result.report["validation"] = {
        "anchors_used_for_range": len(calibration_depths),
        "reserved_anchor_samples": len(relative_errors),
        "median_relative_error": float(np.median(relative_errors)) if relative_errors else None,
        "p90_relative_error": float(np.percentile(relative_errors, 90)) if relative_errors else None,
        "within_10_percent": float(np.mean(np.asarray(relative_errors) <= 0.10)) if relative_errors else None,
        "reference_mesh_read": False,
    }
    result.report["selection_candidates"] = selection["candidate_pairs"]
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(result.report, ensure_ascii=False, indent=2), encoding="utf-8")
    np.savez_compressed(args.output.with_suffix(".npz"), depth=result.depth, confidence=result.confidence)
    print(json.dumps(result.report, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
