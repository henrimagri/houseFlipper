import numpy as np

from app.surface_stitching import stitch_nearby_surfaces


def test_stitching_welds_parallel_observations_but_preserves_perpendicular_surface() -> None:
    vertices = np.asarray([
        [0, 0, 0], [1, 0, 0], [0, 1, 0],
        [0.004, 0, 0], [1.004, 0, 0], [0.004, 1, 0],
        [0, 0, 0.003], [0, 1, 0.003], [0, 0, 1.003],
    ], dtype=float)
    faces = np.asarray([[0, 1, 2], [3, 4, 5], [6, 7, 8]])
    colors = np.full((len(vertices), 3), 128, dtype=np.uint8)
    stitched, _, _, stitched_faces, report = stitch_nearby_surfaces(
        vertices, faces, colors, voxel_size=0.01,
    )
    assert len(stitched) == 6
    assert len(stitched_faces) == 2
    assert report["normal_constraint"] == "dominant_absolute_axis"
