from __future__ import annotations

from pathlib import Path

import numpy as np

from app.calibrated_depth_fusion import Camera, View, _fit_metric_depth, _fuse_surface_mesh, _topology_metrics, _voxel_fuse, write_binary_ply


def test_metric_depth_fit_uses_sparse_geometry_without_reference_mesh() -> None:
    camera = Camera(width=100, height=80, focal=60, cx=50, cy=40)
    relative = np.zeros((80, 100), dtype=np.float32)
    points = {}
    observations = []
    slope, intercept = 0.08, 0.04
    point_id = 1
    for y in range(8, 72, 8):
        for x in range(10, 90, 10):
            depth = 1.2 + x / 120 + y / 160
            relative[y, x] = (1 / depth - intercept) / slope
            world = np.asarray([(x - 50) / 60 * depth, (y - 40) / 60 * depth, depth])
            points[point_id] = world
            observations.append((float(x), float(y), point_id))
            point_id += 1
    view = View(1, 1, "view.jpg", np.eye(3), np.zeros(3), observations)

    fitted = _fit_metric_depth(relative, view, camera, points)

    assert fitted is not None
    assert fitted["samples"] == len(observations)
    assert fitted["median_relative_error"] < 0.001
    assert abs(fitted["slope"] - slope) < 0.001


def test_voxel_fusion_and_binary_ply_preserve_color_and_normals(tmp_path: Path) -> None:
    points = np.asarray([[0, 0, 0], [0.005, 0, 0], [1, 0, 0]], dtype=np.float32)
    normals = np.asarray([[0, 0, 1], [0, 0, 1], [0, 1, 0]], dtype=np.float32)
    colors = np.asarray([[100, 120, 140], [120, 140, 160], [200, 20, 10]], dtype=np.uint8)

    fused_points, fused_normals, fused_colors = _voxel_fuse(points, normals, colors, 0.01)
    target = tmp_path / "fused.ply"
    write_binary_ply(target, fused_points, fused_normals, fused_colors)

    assert len(fused_points) == 2
    assert np.allclose(np.linalg.norm(fused_normals, axis=1), 1)
    assert fused_colors.tolist()[0] == [110, 130, 150]
    assert target.read_bytes().startswith(b"ply\nformat binary_little_endian 1.0")


def test_multiview_surface_fusion_keeps_faces_and_aligns_opposite_normals() -> None:
    quad = np.asarray([[0, 0, 1], [1, 0, 1], [0, 1, 1], [1, 1, 1]], dtype=np.float32)
    points = np.concatenate((quad, quad + 0.002))
    normals = np.concatenate((
        np.tile([0, 0, -1], (4, 1)),
        np.tile([0, 0, 1], (4, 1)),
    )).astype(np.float32)
    colors = np.concatenate((
        np.tile([100, 120, 140], (4, 1)),
        np.tile([140, 160, 180], (4, 1)),
    )).astype(np.uint8)
    faces = np.asarray([
        [0, 2, 1], [1, 2, 3],
        [4, 6, 5], [5, 6, 7],
    ], dtype=np.int64)

    fused_points, fused_normals, fused_colors, fused_faces, metrics = _fuse_surface_mesh(
        points, normals, colors, faces, voxel_size=0.01,
    )

    assert len(fused_points) == 4
    assert len(fused_faces) == 2
    assert np.allclose(np.linalg.norm(fused_normals, axis=1), 1)
    assert fused_colors.tolist()[0] == [120, 140, 160]
    assert metrics == {
        "raw_faces": 4,
        "valid_faces": 4,
        "fused_faces": 2,
        "removed_degenerate_faces": 0,
        "removed_duplicate_faces": 2,
    }


def test_topology_metrics_distinguish_open_and_nonmanifold_edges() -> None:
    import trimesh

    mesh = trimesh.Trimesh(
        vertices=np.asarray([[0, 0, 0], [1, 0, 0], [0, 1, 0], [0, -1, 0], [0, 0, 1]], dtype=float),
        faces=np.asarray([[0, 1, 2], [1, 0, 3], [0, 1, 4]], dtype=np.int64),
        process=False,
    )

    report = _topology_metrics(mesh)

    assert report["boundary_edges"] == 6
    assert report["nonmanifold_edges"] == 1
    assert report["watertight"] is False
