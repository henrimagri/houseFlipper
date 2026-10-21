from __future__ import annotations

import numpy as np
import trimesh

from app.sparse_tsdf import SparseTsdfVolume, TsdfConfig


def _camera(center: np.ndarray, target: np.ndarray) -> tuple[np.ndarray, np.ndarray]:
    forward = target - center
    forward /= np.linalg.norm(forward)
    up = np.asarray([0.0, 0.0, 1.0])
    if abs(float(np.dot(forward, up))) > 0.95:
        up = np.asarray([0.0, 1.0, 0.0])
    right = np.cross(forward, up)
    right /= np.linalg.norm(right)
    down = np.cross(forward, right)
    rotation = np.stack((right, down, forward))
    return rotation, -rotation @ center


def _rays(
    center: np.ndarray,
    target: np.ndarray,
    size: int = 41,
    focal: float = 38.0,
) -> tuple[np.ndarray, np.ndarray, np.ndarray, np.ndarray, float]:
    rotation, translation = _camera(center, target)
    yy, xx = np.indices((size, size))
    principal = (size - 1) / 2
    camera_directions = np.stack((
        (xx - principal) / focal,
        (yy - principal) / focal,
        np.ones((size, size)),
    ), axis=-1)
    world_directions = camera_directions @ rotation
    return rotation, translation, world_directions, camera_directions, principal


def _topology(vertices: np.ndarray, faces: np.ndarray) -> dict[str, int | bool]:
    edges = np.sort(np.vstack((faces[:, [0, 1]], faces[:, [1, 2]], faces[:, [2, 0]])), axis=1)
    _, counts = np.unique(edges, axis=0, return_counts=True)
    mesh = trimesh.Trimesh(vertices=vertices, faces=faces, process=False)
    components = mesh.split(only_watertight=False)
    significant_limit = max(12, int(len(faces) * 0.01))
    return {
        "components": len(components),
        "significant_components": sum(len(component.faces) >= significant_limit for component in components),
        "boundary": int(np.sum(counts == 1)),
        "nonmanifold": int(np.sum(counts > 2)),
        "winding": bool(mesh.is_winding_consistent),
    }


def _volume(voxel_size: float = 0.08, block_resolution: int = 4) -> SparseTsdfVolume:
    return SparseTsdfVolume(TsdfConfig(
        voxel_size=voxel_size,
        truncation=voxel_size * 3,
        block_resolution=block_resolution,
        max_blocks=4_000,
        pixel_stride=1,
        minimum_mesh_weight=1.25,
    ))


def _integrate_depth(
    volume: SparseTsdfVolume,
    depth: np.ndarray,
    rotation: np.ndarray,
    translation: np.ndarray,
    principal: float,
    focal: float = 38.0,
) -> None:
    color = np.full((*depth.shape, 3), (60, 130, 210), dtype=np.uint8)
    for _ in range(2):
        volume.integrate(
            depth=depth.astype(np.float32),
            color=color,
            focal_x=focal,
            focal_y=focal,
            cx=principal,
            cy=principal,
            rotation=rotation,
            translation=translation,
            confidence=0.9,
        )


def _plane_depth(
    center: np.ndarray,
    target: np.ndarray,
    normal: np.ndarray,
    offset: float,
) -> tuple[np.ndarray, np.ndarray, np.ndarray, float]:
    rotation, translation, world_directions, _, principal = _rays(center, target)
    denominator = world_directions @ normal
    depth = -(float(np.dot(center, normal)) + offset) / denominator
    depth[(depth <= 0) | (np.abs(denominator) < 1e-8)] = np.nan
    return depth, rotation, translation, principal


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


def test_sparse_tsdf_reconstructs_inclined_plane_with_consistent_topology() -> None:
    volume = _volume()
    normal = np.asarray([0.22, -0.15, 1.0])
    normal /= np.linalg.norm(normal)
    for center in (np.asarray([-0.12, 0.0, 0.0]), np.asarray([0.12, 0.0, 0.0])):
        depth, rotation, translation, principal = _plane_depth(
            center, np.asarray([0.0, 0.0, 2.0]), normal, -2.0,
        )
        _integrate_depth(volume, depth, rotation, translation, principal)

    vertices, faces, _, _ = volume.extract_mesh()
    distances = np.abs(vertices @ normal - 2.0)
    topology = _topology(vertices, faces)

    assert float(np.quantile(distances, 0.9)) <= volume.config.voxel_size * 1.25
    assert topology["significant_components"] == 1
    assert topology["nonmanifold"] == 0
    assert topology["winding"] is True


def test_sparse_tsdf_reconstructs_two_wall_corner() -> None:
    volume = _volume(voxel_size=0.1)
    for center in (
        np.asarray([1.0, -0.12, 0.1]),
        np.asarray([1.0, 0.0, 0.1]),
        np.asarray([1.0, 0.12, 0.1]),
    ):
        rotation, translation, directions, _, principal = _rays(center, np.asarray([0.0, 0.0, 2.0]))
        depth_back = (2.0 - center[2]) / directions[..., 2]
        depth_side = np.divide(-center[0], directions[..., 0], out=np.full_like(directions[..., 0], np.nan), where=np.abs(directions[..., 0]) > 1e-9)
        candidates = np.stack((depth_back, depth_side))
        candidates[(candidates <= 0) | ~np.isfinite(candidates)] = np.nan
        depth = np.nanmin(candidates, axis=0)
        _integrate_depth(volume, depth, rotation, translation, principal)

    vertices, faces, _, _ = volume.extract_mesh()
    topology = _topology(vertices, faces)

    assert topology["significant_components"] == 1
    assert topology["nonmanifold"] == 0


def test_sparse_tsdf_reconstructs_closed_box_from_known_cameras() -> None:
    volume = _volume(voxel_size=0.1)
    half = 0.5
    axis_centers = (
        np.asarray([1.8, 0.0, 0.0]), np.asarray([-1.8, 0.0, 0.0]),
        np.asarray([0.0, 1.8, 0.0]), np.asarray([0.0, -1.8, 0.0]),
        np.asarray([0.0, 0.0, 1.8]), np.asarray([0.0, 0.0, -1.8]),
    )
    diagonal_centers = tuple(
        np.asarray([x, y, z], dtype=np.float64) * 1.45
        for x in (-1, 1) for y in (-1, 1) for z in (-1, 1)
    )
    for center in axis_centers + diagonal_centers:
        rotation, translation, directions, _, principal = _rays(center, np.zeros(3))
        safe = np.where(np.abs(directions) < 1e-9, np.nan, directions)
        first = (-half - center) / safe
        second = (half - center) / safe
        near = np.nanmax(np.minimum(first, second), axis=-1)
        far = np.nanmin(np.maximum(first, second), axis=-1)
        depth = np.where((far >= near) & (far > 0), np.maximum(near, 0), np.nan)
        _integrate_depth(volume, depth, rotation, translation, principal)

    vertices, faces, _, _ = volume.extract_mesh()
    topology = _topology(vertices, faces)

    assert topology["components"] == 1
    assert topology["nonmanifold"] == 0
    assert topology["winding"] is True
    assert float(np.quantile(np.maximum(np.abs(vertices) - half, 0.0), 0.95)) <= volume.config.voxel_size * 1.5


def test_sparse_tsdf_reconstructs_curved_sphere() -> None:
    volume = _volume(voxel_size=0.1)
    radius = 0.55
    axis_centers = (
        np.asarray([1.8, 0.0, 0.0]), np.asarray([-1.8, 0.0, 0.0]),
        np.asarray([0.0, 1.8, 0.0]), np.asarray([0.0, -1.8, 0.0]),
        np.asarray([0.0, 0.0, 1.8]), np.asarray([0.0, 0.0, -1.8]),
    )
    diagonal_centers = tuple(
        np.asarray([x, y, z], dtype=np.float64) * 1.45
        for x in (-1, 1) for y in (-1, 1) for z in (-1, 1)
    )
    for center in axis_centers + diagonal_centers:
        rotation, translation, directions, _, principal = _rays(center, np.zeros(3))
        a = np.sum(directions * directions, axis=-1)
        b = 2 * np.sum(center * directions, axis=-1)
        c = float(np.dot(center, center) - radius * radius)
        discriminant = b * b - 4 * a * c
        depth = np.where(discriminant >= 0, (-b - np.sqrt(np.maximum(discriminant, 0))) / (2 * a), np.nan)
        depth[depth <= 0] = np.nan
        _integrate_depth(volume, depth, rotation, translation, principal)

    vertices, faces, _, _ = volume.extract_mesh()
    radial_error = np.abs(np.linalg.norm(vertices, axis=1) - radius)
    topology = _topology(vertices, faces)

    assert float(np.quantile(radial_error, 0.9)) <= volume.config.voxel_size * 1.5
    assert topology["components"] == 1
    assert topology["nonmanifold"] == 0


def test_sparse_tsdf_surface_crosses_block_boundary_without_a_seam() -> None:
    volume = _volume(voxel_size=0.05, block_resolution=4)
    for x in (-0.08, 0.0, 0.08):
        depth, rotation, translation, principal = _plane_depth(
            np.asarray([x, 0.0, 0.0]),
            np.asarray([x, 0.0, 1.975]),
            np.asarray([0.0, 0.0, 1.0]),
            -1.975,
        )
        _integrate_depth(volume, depth, rotation, translation, principal)

    vertices, faces, _, _ = volume.extract_mesh()
    topology = _topology(vertices, faces)

    assert topology["components"] == 1
    assert topology["nonmanifold"] == 0
    assert topology["winding"] is True
    assert float(np.median(np.abs(vertices[:, 2] - 1.975))) <= volume.config.voxel_size / 2 + 1e-6


def test_sparse_tsdf_uses_z_depth_and_colmap_world_to_camera_transform() -> None:
    volume = _volume(voxel_size=0.05)
    angle = np.deg2rad(18)
    rotation = np.asarray([
        [np.cos(angle), 0.0, np.sin(angle)],
        [0.0, 1.0, 0.0],
        [-np.sin(angle), 0.0, np.cos(angle)],
    ])
    center = np.asarray([0.25, -0.1, 0.05])
    translation = -rotation @ center
    size = 40
    focal = 38.0
    principal = (size - 1) / 2
    yy, xx = np.indices((size, size))
    camera_directions = np.stack((
        (xx - principal) / focal,
        (yy - principal) / focal,
        np.ones((size, size)),
    ), axis=-1)
    world_directions = camera_directions @ rotation
    world_normal = np.asarray([0.0, 0.0, 1.0])
    depth = (2.0 - center[2]) / (world_directions @ world_normal)
    _integrate_depth(volume, depth, rotation, translation, principal, focal)

    vertices, _, _, _ = volume.extract_mesh()
    assert float(np.quantile(np.abs(vertices[:, 2] - 2.0), 0.9)) <= volume.config.voxel_size * 1.25


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
