from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

import trimesh

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT))

from app.calibrated_depth_fusion import read_colmap_text  # noqa: E402
from app.photo_texturing import TextureCamera, texture_mesh_from_photos  # noqa: E402


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--mesh", type=Path, required=True)
    parser.add_argument("--images", type=Path, required=True)
    parser.add_argument("--colmap-model", type=Path, required=True)
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()
    args.output.mkdir(parents=True, exist_ok=True)
    camera_models, views, _ = read_colmap_text(args.colmap_model)
    fit_report_path = args.mesh.parent / "fit-report.json"
    accepted_names = None
    if fit_report_path.is_file():
        fit_report = json.loads(fit_report_path.read_text(encoding="utf-8"))
        accepted_names = {entry["image"] for entry in fit_report if entry.get("accepted")}
    cameras = []
    for view in views:
        if accepted_names is not None and view.name not in accepted_names:
            continue
        model = camera_models[view.camera_id]
        cameras.append(TextureCamera(
            name=view.name, width=model.width, height=model.height, focal=model.focal,
            cx=model.cx, cy=model.cy, rotation=view.rotation, translation=view.translation,
        ))
    mesh = trimesh.load(args.mesh, force="mesh", process=False)
    result = texture_mesh_from_photos(
        mesh=mesh,
        images=args.images,
        cameras=cameras,
        output_glb=args.output / "depth-fusion-textured.glb",
        atlas_path=args.output / "texture-atlas.jpg",
        report_path=args.output / "texture-report.json",
        regions_path=args.output / "surface-regions.json",
    )
    print(json.dumps(result, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
