from __future__ import annotations

from typing import Any

import numpy as np


def vertex_normals(vertices: np.ndarray, faces: np.ndarray) -> np.ndarray:
    triangles = vertices[faces]
    face_normals = np.cross(triangles[:, 1] - triangles[:, 0], triangles[:, 2] - triangles[:, 0])
    result = np.zeros_like(vertices, dtype=np.float64)
    for corner in range(3):
        np.add.at(result, faces[:, corner], face_normals)
    lengths = np.linalg.norm(result, axis=1)
    valid = lengths > 1e-12
    result[valid] /= lengths[valid, None]
    return result.astype(np.float32)


def stitch_nearby_surfaces(
    vertices: np.ndarray,
    faces: np.ndarray,
    colors: np.ndarray,
    *,
    voxel_size: float,
) -> tuple[np.ndarray, np.ndarray, np.ndarray, np.ndarray, dict[str, Any]]:
    """Weld nearby observations only when their dominant surface plane agrees.

    The normal family in the voxel key prevents a floor and wall at a corner from
    collapsing into one vertex. Opposite windings share the same family because
    partially reconstructed inputs do not have reliable winding.
    """
    normals = vertex_normals(vertices, faces)
    family = np.argmax(np.abs(normals), axis=1)[:, None]
    spatial = np.floor(vertices / voxel_size).astype(np.int64)
    keys = np.column_stack((spatial, family))
    _, inverse = np.unique(keys, axis=0, return_inverse=True)
    counts = np.bincount(inverse).astype(np.float64)
    stitched_vertices = np.column_stack([
        np.bincount(inverse, weights=vertices[:, axis]) / counts for axis in range(3)
    ]).astype(np.float32)
    stitched_colors = np.column_stack([
        np.bincount(inverse, weights=colors[:, axis]) / counts for axis in range(3)
    ])
    mapped = inverse[faces]
    nondegenerate = (
        (mapped[:, 0] != mapped[:, 1])
        & (mapped[:, 1] != mapped[:, 2])
        & (mapped[:, 0] != mapped[:, 2])
    )
    mapped = mapped[nondegenerate]
    canonical = np.sort(mapped, axis=1)
    _, unique = np.unique(canonical, axis=0, return_index=True)
    mapped = mapped[np.sort(unique)]
    stitched_normals = vertex_normals(stitched_vertices, mapped)
    report = {
        "method": "normal_family_voxel_stitching",
        "voxel_size_m": voxel_size,
        "input_vertices": int(len(vertices)),
        "output_vertices": int(len(stitched_vertices)),
        "input_faces": int(len(faces)),
        "output_faces": int(len(mapped)),
        "removed_degenerate_faces": int(len(faces) - int(nondegenerate.sum())),
        "removed_duplicate_faces": int(int(nondegenerate.sum()) - len(mapped)),
        "preserves_distant_surfaces": True,
        "normal_constraint": "dominant_absolute_axis",
    }
    return (
        stitched_vertices,
        stitched_normals,
        np.clip(stitched_colors, 0, 255).astype(np.uint8),
        mapped.astype(np.int64),
        report,
    )
