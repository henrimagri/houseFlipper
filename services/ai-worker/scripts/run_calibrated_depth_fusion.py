from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from app.calibrated_depth_fusion import reconstruct_calibrated_depth


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--images", type=Path, required=True)
    parser.add_argument("--model", type=Path, required=True)
    parser.add_argument("--output", type=Path, required=True)
    parser.add_argument("--colmap", type=Path, required=True)
    parser.add_argument("--maximum-views", type=int, default=42)
    parser.add_argument("--stride", type=int, default=7)
    parser.add_argument("--voxel-size", type=float, default=0.025)
    parser.add_argument("--method", choices=("calibrated", "tsdf"), default="calibrated")
    args = parser.parse_args()
    result = reconstruct_calibrated_depth(
        images=args.images.resolve(),
        colmap_text_model=args.model.resolve(),
        output=args.output.resolve(),
        colmap_executable=args.colmap.resolve(),
        maximum_views=max(8, min(args.maximum_views, 126)),
        stride=max(3, min(args.stride, 24)),
        voxel_size=max(0.005, min(args.voxel_size, 0.2)),
        method=args.method,
    )
    print(json.dumps(result, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
