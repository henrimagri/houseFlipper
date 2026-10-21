from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path

import cv2
import numpy as np
import pytest
from PIL import Image

from app.calibrated_stereo import (
    StereoConfig,
    StereoPair,
    camera_center,
    compute_stereo_pair,
    conservative_local_depth_correction,
    relative_pose,
    select_stereo_pairs,
)


@dataclass(frozen=True)
class Camera:
    width: int
    height: int
    focal: float
    cx: float
    cy: float


@dataclass
class View:
    name: str
    camera_id: int
    rotation: np.ndarray
    translation: np.ndarray
    observations: list[tuple[float, float, int]]


def _camera(width: int = 320, height: int = 240, focal: float = 180.0) -> Camera:
    return Camera(width, height, focal, width / 2, height / 2)


def _parallel_views(disparity: float = 18.0, depth: float = 2.0) -> tuple[View, View, Camera]:
    camera = _camera()
    baseline = disparity * depth / camera.focal
    observations_left = []
    observations_right = []
    point_id = 1
    for y in range(28, camera.height - 28, 22):
        for x in range(42, camera.width - 28, 24):
            observations_left.append((float(x), float(y), point_id))
            observations_right.append((float(x - disparity), float(y), point_id))
            point_id += 1
    left = View("left.png", 1, np.eye(3), np.zeros(3), observations_left)
    right = View("right.png", 1, np.eye(3), np.asarray([-baseline, 0.0, 0.0]), observations_right)
    return left, right, camera


def _texture(width: int, height: int) -> np.ndarray:
    rng = np.random.default_rng(43)
    noise = rng.integers(0, 256, (height, width), dtype=np.uint8)
    gray = cv2.GaussianBlur(noise, (3, 3), 0.6)
    return cv2.cvtColor(gray, cv2.COLOR_GRAY2RGB)


def _write_shifted_pair(root: Path, disparity: int, *, flat: bool = False, brightness: float = 1.0) -> None:
    left = np.full((240, 320, 3), 127, dtype=np.uint8) if flat else _texture(320, 240)
    right = np.zeros_like(left)
    right[:, :-disparity] = left[:, disparity:]
    right = np.clip(right.astype(np.float32) * brightness, 0, 255).astype(np.uint8)
    Image.fromarray(left).save(root / "left.png")
    Image.fromarray(right).save(root / "right.png")


def _pair(left: View, right: View, camera: Camera, disparity: float = 18.0) -> StereoPair:
    return StereoPair(
        left.name, right.name, len(left.observations), 1.0,
        float(np.linalg.norm(camera_center(left) - camera_center(right))),
        0.5, 100.0, 0.0, 0.9,
    )


def test_relative_pose_uses_colmap_world_to_camera_convention() -> None:
    left, right, _ = _parallel_views()
    rotation, translation = relative_pose(left, right)
    assert np.allclose(rotation, np.eye(3))
    assert np.allclose(translation, right.translation)
    assert np.allclose(camera_center(right), -right.translation)


def test_pair_selection_uses_shared_points_baseline_and_excludes_holdout(tmp_path: Path) -> None:
    left, right, camera = _parallel_views()
    _write_shifted_pair(tmp_path, 18)
    duplicate = View("duplicate.png", 1, np.eye(3), np.asarray([-0.001, 0.0, 0.0]), left.observations)
    holdout = View("holdout.png", 1, np.eye(3), np.asarray([-0.2, 0.0, 0.0]), left.observations)
    Image.fromarray(_texture(320, 240)).save(tmp_path / duplicate.name)
    Image.fromarray(_texture(320, 240)).save(tmp_path / holdout.name)
    selected, report = select_stereo_pairs(
        [left, right, duplicate, holdout], {1: camera}, tmp_path,
        StereoConfig(minimum_angle_degrees=0.0, maximum_pairs=4),
        excluded_names={holdout.name},
    )
    assert selected
    assert all(holdout.name not in (pair.left_name, pair.right_name) for pair in selected)
    assert report["rejected"]["baseline_too_small"] >= 1


def test_rectified_stereo_recovers_known_front_plane_depth(tmp_path: Path) -> None:
    disparity, expected_depth = 18, 2.0
    left, right, camera = _parallel_views(disparity, expected_depth)
    _write_shifted_pair(tmp_path, disparity)
    result = compute_stereo_pair(
        _pair(left, right, camera), left, right, camera, camera, tmp_path,
        StereoConfig(width=320, number_of_disparities=64, minimum_confidence=0.20),
    )
    valid = np.isfinite(result.depth)
    assert valid.mean() > 0.12
    assert np.median(result.depth[valid]) == pytest.approx(expected_depth, rel=0.08)
    assert result.report["rectification"]["median_orthogonal_error_px"] < 0.1
    assert result.report["depth"]["convention"] == "z_along_original_left_camera_axis"


def test_low_texture_pair_is_not_reported_as_dense_measurement(tmp_path: Path) -> None:
    left, right, camera = _parallel_views()
    _write_shifted_pair(tmp_path, 18, flat=True)
    result = compute_stereo_pair(
        _pair(left, right, camera), left, right, camera, camera, tmp_path,
        StereoConfig(width=320, number_of_disparities=64),
    )
    assert np.isfinite(result.depth).mean() < 0.01


def test_illumination_difference_reduces_valid_stereo_support(tmp_path: Path) -> None:
    left, right, camera = _parallel_views()
    _write_shifted_pair(tmp_path, 18, brightness=0.35)
    result = compute_stereo_pair(
        _pair(left, right, camera), left, right, camera, camera, tmp_path,
        StereoConfig(width=320, number_of_disparities=64, minimum_confidence=0.52),
    )
    assert result.report["disparity"]["valid_fraction"] < 0.15


def test_insufficient_baseline_is_rejected_before_matching(tmp_path: Path) -> None:
    left, right, camera = _parallel_views()
    right.translation = np.asarray([-0.002, 0.0, 0.0])
    _write_shifted_pair(tmp_path, 18)
    selected, report = select_stereo_pairs(
        [left, right], {1: camera}, tmp_path,
        StereoConfig(minimum_angle_degrees=0.0),
    )
    assert selected == []
    assert report["rejected"] == {"baseline_too_small": 1}


def test_low_overlap_pair_is_rejected_before_matching(tmp_path: Path) -> None:
    left, right, camera = _parallel_views()
    right.observations = right.observations[:8]
    _write_shifted_pair(tmp_path, 18)
    selected, report = select_stereo_pairs(
        [left, right], {1: camera}, tmp_path,
        StereoConfig(minimum_angle_degrees=0.0),
    )
    assert selected == []
    assert report["rejected"] == {"insufficient_shared_sfm_points": 1}


def test_occluded_strip_is_not_promoted_to_valid_depth(tmp_path: Path) -> None:
    left, right, camera = _parallel_views()
    _write_shifted_pair(tmp_path, 18)
    right_image = np.asarray(Image.open(tmp_path / "right.png").convert("RGB")).copy()
    right_image[:, 120:165] = 0
    Image.fromarray(right_image).save(tmp_path / "right.png")
    result = compute_stereo_pair(
        _pair(left, right, camera), left, right, camera, camera, tmp_path,
        StereoConfig(width=320, number_of_disparities=64, minimum_confidence=0.20),
    )
    occluded_valid = np.isfinite(result.depth[:, 138:183]).mean()
    ordinary_valid = np.isfinite(result.depth[:, 200:250]).mean()
    assert occluded_valid < ordinary_valid


def test_local_correction_is_bounded_edge_aware_and_preserves_anchors() -> None:
    mono = np.full((80, 100), 2.0, dtype=np.float32)
    mono[:, 50:] = 4.0
    stereo = np.full_like(mono, np.nan)
    confidence = np.zeros_like(mono)
    stereo[20:60, 15:35] = 2.2
    confidence[20:60, 15:35] = 0.95
    stereo[20:60, 65:85] = 3.4  # rejected: more than the 22% correction limit
    confidence[20:60, 65:85] = 0.95
    anchors = np.zeros_like(mono, dtype=bool)
    anchors[40, 25] = True
    corrected, hybrid, report = conservative_local_depth_correction(
        mono, stereo, confidence, anchor_mask=anchors,
    )
    assert corrected[40, 25] == mono[40, 25]
    assert corrected[:, 52:].max() == pytest.approx(4.0)
    assert np.nanmax(np.abs(corrected - mono) / mono) <= 0.22 + 1e-6
    assert hybrid[30, 25] == pytest.approx(2.2, rel=0.01)
    assert hybrid[30, 25] > mono[30, 25]
    assert report["unknown_pixels_promoted_to_stereo"] == 0

