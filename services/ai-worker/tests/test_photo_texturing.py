from pathlib import Path

import numpy as np
import trimesh
from PIL import Image

from app.photo_texturing import TextureCamera, classify_surface_regions, texture_mesh_from_photos


def test_photo_atlas_exports_uv_glb_and_reports_unobserved_faces(tmp_path: Path) -> None:
    images = tmp_path / "images"
    images.mkdir()
    Image.new("RGB", (128, 128), (180, 90, 40)).save(images / "view.jpg")
    mesh = trimesh.Trimesh(
        vertices=np.asarray([[-1, -1, 2], [1, -1, 2], [1, 1, 2], [-1, 1, 2]], dtype=float),
        faces=np.asarray([[0, 1, 2], [0, 2, 3]]),
        process=False,
    )
    camera = TextureCamera("view.jpg", 128, 128, 80, 64, 64, np.eye(3), np.zeros(3))
    report = texture_mesh_from_photos(
        mesh=mesh, images=images, cameras=[camera],
        output_glb=tmp_path / "model.glb", atlas_path=tmp_path / "atlas.jpg",
        report_path=tmp_path / "report.json", regions_path=tmp_path / "regions.json",
        atlas_size=512,
    )
    scene = trimesh.load(tmp_path / "model.glb", process=False)
    loaded = trimesh.util.concatenate(tuple(scene.geometry.values())) if isinstance(scene, trimesh.Scene) else scene
    assert (tmp_path / "atlas.jpg").is_file()
    assert (tmp_path / "regions.json").is_file()
    assert len(loaded.faces) == 2
    assert report["method"] == "calibrated_multiview_photo_atlas"
    assert report["textured_face_ratio"] == 1.0
    raw = (tmp_path / "model.glb").read_bytes()
    import json
    import struct
    json_length = struct.unpack_from("<I", raw, 12)[0]
    gltf = json.loads(raw[20:20 + json_length])
    material_names = {material.get("name", "") for material in gltf.get("materials", [])}
    assert any(name.startswith("geometry-") for name in material_names)


def test_surface_classes_are_candidates_and_manually_correctable() -> None:
    mesh = trimesh.creation.box(extents=(2, 2, 2))
    result = classify_surface_regions(mesh)
    assert result["regions"]
    assert all(region["manual_correction_allowed"] for region in result["regions"])
    assert any(region["kind"] == "wall_candidate" for region in result["regions"])
