from __future__ import annotations

import numpy as np

from app.sparse_tsdf import SparseTsdfVolume, TsdfConfig


def test_sparse_tsdf_fuses_real_depth_rays_and_extracts_surface() -> None:
    config = TsdfConfig(
        voxel_size=0.08,
        truncation=0.16,
        block_resolution=4,
        max_blocks=500,
        pixel_stride=2,
        minimum_mesh_weight=1.25,
    )
    volume = SparseTsdfVolume(config)
    depth = np.full((32, 40), 2.0, dtype=np.float32)
    color = np.full((32, 40, 3), (40, 120, 210), dtype=np.uint8)
    for shift in (-0.02, 0.0, 0.02):
        volume.integrate(
            depth=depth + shift,
            color=color,
            focal_x=35,
            focal_y=35,
            cx=20,
            cy=16,
            rotation=np.eye(3),
            translation=np.zeros(3),
            confidence=0.9,
        )

    vertices, faces, colors, report = volume.extract_mesh()

    assert len(vertices) > 20
    assert len(faces) > 20
    assert colors.shape == vertices.shape
    np.testing.assert_allclose(np.quantile(vertices[:, 2], 0.5), 2.0, atol=0.2)
    assert report["integrated_views"] == 3
    assert report["unknown_space_filled"] is False


def test_sparse_tsdf_enforces_block_budget_and_cancellation() -> None:
    volume = SparseTsdfVolume(TsdfConfig(
        voxel_size=0.05,
        truncation=0.1,
        block_resolution=4,
        max_blocks=64,
        pixel_stride=1,
    ))
    depth = np.linspace(0.5, 6.0, 48 * 48, dtype=np.float32).reshape((48, 48))
    color = np.zeros((48, 48, 3), dtype=np.uint8)
    volume.integrate(
        depth=depth,
        color=color,
        focal_x=40,
        focal_y=40,
        cx=24,
        cy=24,
        rotation=np.eye(3),
        translation=np.zeros(3),
        confidence=0.8,
    )

    assert len(volume.blocks) == 64
    assert volume.report()["dropped_block_updates"] > 0

    try:
        volume.integrate(
            depth=depth,
            color=color,
            focal_x=40,
            focal_y=40,
            cx=24,
            cy=24,
            rotation=np.eye(3),
            translation=np.zeros(3),
            confidence=0.8,
            cancel=lambda: True,
        )
    except InterruptedError:
        pass
    else:
        raise AssertionError("O cancelamento deveria interromper a integração.")
