from __future__ import annotations

import argparse
import hashlib
import json
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from app.calibrated_depth_fusion import read_colmap_text
from app.calibrated_stereo import StereoConfig, build_stereo_evidence


def _aggregate_funnel(report: dict) -> dict[str, int | float]:
    keys = (
        "total_pixels",
        "rectified_left_roi_pixels",
        "geometric_overlap_pixels",
        "disparity_range_eligible_pixels",
        "valid_disparity_pixels",
        "left_right_consistent_pixels",
        "rejected_as_occlusion_pixels",
        "rejected_photometric_pixels",
        "rejected_confidence_pixels",
        "rejected_invalid_depth_pixels",
        "valid_rectified_pixels",
        "valid_original_grid_pixels",
        "pixels_effectively_used_for_correction_or_fusion",
    )
    totals = {
        key: sum(int(pair["acceptance_funnel"].get(key, 0)) for pair in report["pair_reports"])
        for key in keys
    }
    totals["whole_image_coverage"] = totals["valid_rectified_pixels"] / max(1, totals["total_pixels"])
    totals["eligible_region_coverage"] = totals["valid_rectified_pixels"] / max(1, totals["geometric_overlap_pixels"])
    return totals


def main() -> None:
    parser = argparse.ArgumentParser(description="Benchmark CPU do estéreo calibrado sem consultar a malha de referência.")
    parser.add_argument("--images", type=Path, required=True)
    parser.add_argument("--model", type=Path, required=True)
    parser.add_argument("--output", type=Path, required=True)
    parser.add_argument("--maximum-views", type=int, default=42)
    parser.add_argument("--width", type=int, default=640)
    parser.add_argument("--expanded-pairs", type=int, default=24)
    parser.add_argument("--development-report", type=Path)
    parser.add_argument("--parameter-prefix", action="append", default=["44_"])
    parser.add_argument("--final-prefix", action="append", default=["45_"])
    args = parser.parse_args()

    cameras, all_views, sparse_points = read_colmap_text(args.model.resolve())
    available = [view for view in all_views if (args.images / view.name).is_file()]
    step = max(1, len(available) // max(8, args.maximum_views))
    selected = available[::step][: max(8, args.maximum_views)]
    excluded_prefixes = tuple(args.parameter_prefix + args.final_prefix)
    development = [view for view in selected if not view.name.startswith(excluded_prefixes)]
    if args.development_report:
        previous = json.loads(args.development_report.read_text(encoding="utf-8"))
        allowed = set(previous["split_isolation"]["development_views"])
        development = [view for view in development if view.name in allowed]
    excluded = {view.name for view in selected if view.name.startswith(excluded_prefixes)}
    target_shapes = {}
    for view in development:
        camera = cameras[view.camera_id]
        width = min(args.width, camera.width)
        target_shapes[view.name] = (max(120, round(width * camera.height / camera.width)), width)

    configurations = {
        "A_score_12": ("score", 12, 2),
        "B_diversity": ("diversity", args.expanded_pairs, 3),
        "C_incremental_coverage": ("coverage", args.expanded_pairs, 3),
    }
    result = {
        "method": "opencv_calibrated_stereo_phase12_coverage_benchmark",
        "reference_mesh_read": False,
        "input": {
            "available_views": len(available),
            "selected_views": len(selected),
            "development_views": len(development),
            "excluded_parameter_or_final_views": sorted(excluded),
        },
        "configurations": {},
    }
    for label, (strategy, maximum_pairs, per_view) in configurations.items():
        started = time.perf_counter()
        config = StereoConfig(
            width=max(320, min(args.width, 1280)),
            maximum_pairs=maximum_pairs,
            maximum_pairs_per_view=per_view,
            selection_strategy=strategy,
            minimum_angle_degrees=0.0,
            number_of_disparities=128,
            minimum_confidence=0.32,
            adaptive_disparity=True,
        )
        _, _, report, _ = build_stereo_evidence(
            development,
            cameras,
            args.images.resolve(),
            target_shapes,
            config,
            sparse_points=sparse_points,
            excluded_names=excluded,
        )
        result["configurations"][label] = {
            "elapsed_seconds": round(time.perf_counter() - started, 3),
            "summary": report["summary"],
            "selection": report["selection"],
            "acceptance_funnel": _aggregate_funnel(report),
            "pair_reports": report["pair_reports"],
        }

    args.output.parent.mkdir(parents=True, exist_ok=True)
    encoded = json.dumps(result, ensure_ascii=False, indent=2).encode("utf-8")
    args.output.write_bytes(encoded)
    digest = hashlib.sha256(encoded).hexdigest()
    print(json.dumps({"output": str(args.output), "sha256": digest, **result}, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
