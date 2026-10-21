from __future__ import annotations

from dataclasses import dataclass

import cv2
import numpy as np
import pytest

from app.multiview_stereo import PlaneSweepConfig, plane_sweep_depth


@dataclass(frozen=True)
class Camera:
    width: int = 192
    height: int = 128
    focal: float = 140.0
    cx: float = 96.0
    cy: float = 64.0


@dataclass
class View:
    name: str
    camera_id: int
    rotation: np.ndarray
    translation: np.ndarray
    observations: list[tuple[float, float, int]]


def _scene(depth: float = 2.0, *, flat: bool = False) -> tuple[View, list[View], dict[int, Camera], dict[str, np.ndarray]]:
    camera = Camera()
    rng = np.random.default_rng(97)
    gray = np.full((camera.height, camera.width), 127, np.uint8) if flat else cv2.GaussianBlur(
        rng.integers(0, 256, (camera.height, camera.width), dtype=np.uint8), (3, 3), 0.5,
    )
    reference = View("reference", 1, np.eye(3), np.zeros(3), [])
    supports = []
    images = {"reference": cv2.cvtColor(gray, cv2.COLOR_GRAY2RGB)}
    for index, baseline in enumerate((0.14, -0.11), start=1):
        support = View(f"support-{index}", 1, np.eye(3), np.asarray([-baseline, 0.0, 0.0]), [])
        disparity = int(round(camera.focal * baseline / depth))
        matrix = np.asarray([[1.0, 0.0, -disparity], [0.0, 1.0, 0.0]], dtype=np.float32)
        support_gray = cv2.warpAffine(gray, matrix, (camera.width, camera.height), borderMode=cv2.BORDER_CONSTANT)
        supports.append(support)
        images[support.name] = cv2.cvtColor(support_gray, cv2.COLOR_GRAY2RGB)
    return reference, supports, {1: camera}, images


def test_three_camera_plane_sweep_recovers_front_plane() -> None:
    reference, supports, cameras, images = _scene()
    result = plane_sweep_depth(
        reference, supports, cameras, images,
        minimum_depth=1.4, maximum_depth=2.8,
        config=PlaneSweepConfig(width=192, depth_planes=64, minimum_confidence=0.03),
    )
    assert result.valid.mean() > 0.15
    assert np.median(result.depth[result.valid]) == pytest.approx(2.0, rel=0.12)
    assert result.report["independent_cameras"] == 3
    assert result.report["evaluated_planes"] == 64


def test_plane_sweep_rejects_flat_texture_and_requires_two_supports() -> None:
    reference, supports, cameras, images = _scene(flat=True)
    result = plane_sweep_depth(
        reference, supports, cameras, images,
        minimum_depth=1.4, maximum_depth=2.8,
        config=PlaneSweepConfig(width=192, depth_planes=24),
    )
    assert not np.any(result.valid)
    with pytest.raises(ValueError, match="duas câmeras"):
        plane_sweep_depth(
            reference, supports[:1], cameras, images,
            minimum_depth=1.4, maximum_depth=2.8,
        )
    with pytest.raises(ValueError, match="duas câmeras"):
        plane_sweep_depth(
            reference, [supports[0], supports[0]], cameras, images,
            minimum_depth=1.4, maximum_depth=2.8,
        )
