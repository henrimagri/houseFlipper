from __future__ import annotations

import math
from dataclasses import dataclass
from pathlib import Path
from typing import Any, Callable, Iterable, Protocol

import cv2
import numpy as np
from PIL import Image


class CameraLike(Protocol):
    width: int
    height: int
    focal: float
    cx: float
    cy: float


class ViewLike(Protocol):
    name: str
    camera_id: int
    rotation: np.ndarray
    translation: np.ndarray
    observations: list[tuple[float, float, int]]


@dataclass(frozen=True)
class StereoConfig:
    width: int = 640
    minimum_shared_points: int = 24
    minimum_baseline: float = 0.035
    maximum_baseline: float = 0.80
    minimum_angle_degrees: float = 0.25
    maximum_angle_degrees: float = 32.0
    maximum_pairs: int = 12
    maximum_pairs_per_view: int = 2
    selection_strategy: str = "score"
    coverage_grid_size: int = 6
    incremental_gain_floor: float = 0.0025
    number_of_disparities: int = 128
    adaptive_disparity: bool = True
    block_size: int = 5
    lr_tolerance: float = 1.5
    maximum_photometric_error: float = 0.35
    maximum_depth: float = 12.0
    minimum_confidence: float = 0.52
    spatial_voxel_size: float = 0.10


@dataclass(frozen=True)
class StereoPair:
    left_name: str
    right_name: str
    shared_points: int
    shared_ratio: float
    baseline: float
    angle_degrees: float
    sharpness: float
    luminance_difference: float
    score: float


@dataclass
class StereoResult:
    left_name: str
    right_name: str
    disparity: np.ndarray
    depth: np.ndarray
    confidence: np.ndarray
    occlusion: np.ndarray
    error: np.ndarray
    report: dict[str, Any]


def camera_center(view: ViewLike) -> np.ndarray:
    """Return the camera centre in COLMAP world coordinates."""
    return -(view.rotation.T @ view.translation)


def relative_pose(left: ViewLike, right: ViewLike) -> tuple[np.ndarray, np.ndarray]:
    """Transform points from the left camera frame into the right frame."""
    rotation = right.rotation @ left.rotation.T
    translation = right.translation - rotation @ left.translation
    return rotation.astype(np.float64), translation.astype(np.float64)


def scaled_intrinsics(camera: CameraLike, width: int, height: int) -> np.ndarray:
    sx = width / camera.width
    sy = height / camera.height
    return np.asarray([
        [camera.focal * sx, 0.0, camera.cx * sx],
        [0.0, camera.focal * sy, camera.cy * sy],
        [0.0, 0.0, 1.0],
    ], dtype=np.float64)


def _point_map(view: ViewLike) -> dict[int, tuple[float, float]]:
    return {point_id: (x, y) for x, y, point_id in view.observations if point_id >= 0}


def _forward(view: ViewLike) -> np.ndarray:
    direction = view.rotation.T @ np.asarray([0.0, 0.0, 1.0])
    return direction / max(float(np.linalg.norm(direction)), 1e-12)


def _image_quality(path: Path, width: int) -> tuple[float, float]:
    with Image.open(path) as opened:
        image = opened.convert("L")
        if image.width > width:
            height = max(1, round(image.height * width / image.width))
            image = image.resize((width, height), Image.Resampling.BILINEAR)
        gray = np.asarray(image, dtype=np.uint8)
    sharpness = float(cv2.Laplacian(gray, cv2.CV_64F).var())
    return sharpness, float(gray.mean())


def select_stereo_pairs(
    views: Iterable[ViewLike],
    cameras: dict[int, CameraLike],
    images: Path,
    config: StereoConfig,
    *,
    excluded_names: set[str] | None = None,
) -> tuple[list[StereoPair], dict[str, Any]]:
    """Select reproducible real-image pairs from geometric and image evidence."""
    excluded_names = excluded_names or set()
    available = [view for view in views if view.name not in excluded_names and (images / view.name).is_file()]
    qualities = {view.name: _image_quality(images / view.name, 320) for view in available}
    point_maps = {view.name: _point_map(view) for view in available}
    candidates: list[StereoPair] = []
    coverage_tokens: dict[tuple[str, str], set[tuple[Any, ...]]] = {}
    rejected: dict[str, int] = {}

    def reject(reason: str) -> None:
        rejected[reason] = rejected.get(reason, 0) + 1

    for left_index, left in enumerate(available):
        left_points = point_maps[left.name]
        for right in available[left_index + 1:]:
            right_points = point_maps[right.name]
            shared = len(left_points.keys() & right_points.keys())
            if shared < config.minimum_shared_points:
                reject("insufficient_shared_sfm_points")
                continue
            baseline = float(np.linalg.norm(camera_center(left) - camera_center(right)))
            if baseline < config.minimum_baseline:
                reject("baseline_too_small")
                continue
            if baseline > config.maximum_baseline:
                reject("baseline_too_large")
                continue
            cosine = float(np.clip(np.dot(_forward(left), _forward(right)), -1.0, 1.0))
            angle = math.degrees(math.acos(cosine))
            if angle < config.minimum_angle_degrees:
                reject("view_angle_too_small")
                continue
            if angle > config.maximum_angle_degrees:
                reject("view_angle_too_large")
                continue
            shared_ratio = shared / max(1, min(len(left_points), len(right_points)))
            left_quality, left_luma = qualities[left.name]
            right_quality, right_luma = qualities[right.name]
            sharpness = min(left_quality, right_quality)
            luma_difference = abs(left_luma - right_luma) / 255.0
            baseline_score = math.exp(-abs(baseline - 0.20) / 0.30)
            angle_score = math.exp(-abs(angle - 7.0) / 14.0)
            texture_score = min(1.0, math.log1p(sharpness) / math.log(151.0))
            score = (
                0.38 * min(1.0, shared / 180.0)
                + 0.18 * min(1.0, shared_ratio * 2.0)
                + 0.17 * baseline_score
                + 0.12 * angle_score
                + 0.10 * texture_score
                + 0.05 * (1.0 - min(1.0, luma_difference * 3.0))
            )
            candidates.append(StereoPair(
                left_name=left.name,
                right_name=right.name,
                shared_points=shared,
                shared_ratio=shared_ratio,
                baseline=baseline,
                angle_degrees=angle,
                sharpness=sharpness,
                luminance_difference=luma_difference,
                score=score,
            ))
            tokens: set[tuple[Any, ...]] = {("point", point_id) for point_id in left_points.keys() & right_points.keys()}
            grid = max(2, config.coverage_grid_size)
            for point_id in left_points.keys() & right_points.keys():
                for view, points in ((left, left_points), (right, right_points)):
                    camera = cameras[view.camera_id]
                    x, y = points[point_id]
                    tokens.add(("cell", view.name, min(grid - 1, int(x * grid / camera.width)), min(grid - 1, int(y * grid / camera.height))))
            coverage_tokens[(left.name, right.name)] = tokens

    def choose(strategy: str, limit: int, per_view: int) -> tuple[list[StereoPair], list[dict[str, Any]]]:
        selected_items: list[StereoPair] = []
        increments: list[dict[str, Any]] = []
        usage: dict[str, int] = {}
        covered: set[tuple[Any, ...]] = set()
        remaining = list(candidates)
        universe = set().union(*(coverage_tokens.values())) if coverage_tokens else set()
        while remaining and len(selected_items) < limit:
            eligible = [item for item in remaining if usage.get(item.left_name, 0) < per_view and usage.get(item.right_name, 0) < per_view]
            if not eligible:
                break
            def utility(item: StereoPair) -> tuple[float, float, str, str]:
                tokens = coverage_tokens[(item.left_name, item.right_name)]
                new = len(tokens - covered)
                new_fraction = new / max(1, len(universe))
                unused_views = int(usage.get(item.left_name, 0) == 0) + int(usage.get(item.right_name, 0) == 0)
                if strategy == "score":
                    value = item.score
                elif strategy == "diversity":
                    value = item.score + 0.18 * unused_views + 0.45 * new_fraction
                else:
                    value = 0.25 * item.score + 2.0 * new_fraction + 0.12 * unused_views
                return value, item.score, item.left_name, item.right_name
            candidate = max(eligible, key=utility)
            tokens = coverage_tokens[(candidate.left_name, candidate.right_name)]
            new_tokens = tokens - covered
            gain_fraction = len(new_tokens) / max(1, len(universe))
            if strategy == "coverage" and selected_items and gain_fraction < config.incremental_gain_floor:
                break
            selected_items.append(candidate)
            covered.update(tokens)
            usage[candidate.left_name] = usage.get(candidate.left_name, 0) + 1
            usage[candidate.right_name] = usage.get(candidate.right_name, 0) + 1
            remaining.remove(candidate)
            increments.append({
                "pair": [candidate.left_name, candidate.right_name],
                "new_coverage_tokens": len(new_tokens),
                "cumulative_coverage_tokens": len(covered),
                "cumulative_fraction": len(covered) / max(1, len(universe)),
            })
        return selected_items, increments

    baseline, baseline_increments = choose("score", min(12, config.maximum_pairs), min(2, config.maximum_pairs_per_view))
    diverse, diverse_increments = choose("diversity", config.maximum_pairs, config.maximum_pairs_per_view)
    coverage, coverage_increments = choose("coverage", config.maximum_pairs, config.maximum_pairs_per_view)
    strategies = {
        "score": (baseline, baseline_increments),
        "diversity": (diverse, diverse_increments),
        "coverage": (coverage, coverage_increments),
    }
    if config.selection_strategy not in strategies:
        raise ValueError("selection_strategy deve ser score, diversity ou coverage.")
    selected, selected_increments = strategies[config.selection_strategy]
    comparisons = {
        name: {
            "selected_pairs": len(items),
            "unique_views": len({value for item in items for value in (item.left_name, item.right_name)}),
            "coverage_tokens": increments[-1]["cumulative_coverage_tokens"] if increments else 0,
            "coverage_fraction": increments[-1]["cumulative_fraction"] if increments else 0.0,
            "incremental_coverage": increments,
            "pairs": [[item.left_name, item.right_name] for item in items],
        }
        for name, (items, increments) in strategies.items()
    }
    return selected, {
        "method": "sfm_overlap_baseline_capture_quality_and_incremental_coverage",
        "strategy": config.selection_strategy,
        "available_views": len(available),
        "candidate_pairs": len(candidates),
        "selected_pairs": len(selected),
        "excluded_views": sorted(excluded_names),
        "rejected": rejected,
        "pairs": [pair.__dict__ for pair in selected],
        "incremental_coverage": selected_increments,
        "strategy_comparison": comparisons,
    }


def _resize_rgb(path: Path, size: tuple[int, int]) -> np.ndarray:
    with Image.open(path) as opened:
        return np.asarray(opened.convert("RGB").resize(size, Image.Resampling.LANCZOS), dtype=np.uint8)


def _rectified_sparse_error(
    left: ViewLike,
    right: ViewLike,
    left_camera: CameraLike,
    right_camera: CameraLike,
    size: tuple[int, int],
    rectification_left: np.ndarray,
    rectification_right: np.ndarray,
    projection_left: np.ndarray,
    projection_right: np.ndarray,
    *,
    vertical: bool,
) -> dict[str, Any]:
    left_points = _point_map(left)
    right_points = _point_map(right)
    shared = sorted(left_points.keys() & right_points.keys())
    if not shared:
        return {"shared_points": 0, "median_vertical_error_px": None, "p90_vertical_error_px": None}
    width, height = size
    points_left = np.asarray([left_points[point] for point in shared], dtype=np.float64)
    points_right = np.asarray([right_points[point] for point in shared], dtype=np.float64)
    points_left *= (width / left_camera.width, height / left_camera.height)
    points_right *= (width / right_camera.width, height / right_camera.height)
    rectified_left = cv2.undistortPoints(
        points_left.reshape(-1, 1, 2), scaled_intrinsics(left_camera, width, height), None,
        R=rectification_left, P=projection_left,
    ).reshape(-1, 2)
    rectified_right = cv2.undistortPoints(
        points_right.reshape(-1, 1, 2), scaled_intrinsics(right_camera, width, height), None,
        R=rectification_right, P=projection_right,
    ).reshape(-1, 2)
    # Horizontal stereo must share y; vertical stereo must share x.
    epipolar_coordinate = 0 if vertical else 1
    errors = np.abs(
        rectified_left[:, epipolar_coordinate] - rectified_right[:, epipolar_coordinate]
    )
    return {
        "shared_points": len(shared),
        "epipolar_axis": "vertical_disparity" if vertical else "horizontal_disparity",
        "median_orthogonal_error_px": float(np.median(errors)),
        "p90_orthogonal_error_px": float(np.percentile(errors, 90)),
    }


def _expected_disparity_range(
    left: ViewLike,
    right: ViewLike,
    left_camera: CameraLike,
    right_camera: CameraLike,
    size: tuple[int, int],
    rectification_left: np.ndarray,
    rectification_right: np.ndarray,
    projection_left: np.ndarray,
    projection_right: np.ndarray,
    *,
    vertical: bool,
    configured_maximum: int,
) -> tuple[int, int, dict[str, Any]]:
    """Estimate a bounded pair-specific disparity interval from SfM matches."""
    left_points = _point_map(left)
    right_points = _point_map(right)
    shared = sorted(left_points.keys() & right_points.keys())
    if not shared:
        maximum = max(16, configured_maximum // 16 * 16)
        return 0, maximum, {"source": "configured_fallback", "samples": 0}
    width, height = size
    points_left = np.asarray([left_points[point] for point in shared], dtype=np.float64)
    points_right = np.asarray([right_points[point] for point in shared], dtype=np.float64)
    points_left *= (width / left_camera.width, height / left_camera.height)
    points_right *= (width / right_camera.width, height / right_camera.height)
    rectified_left = cv2.undistortPoints(
        points_left.reshape(-1, 1, 2), scaled_intrinsics(left_camera, width, height), None,
        R=rectification_left, P=projection_left,
    ).reshape(-1, 2)
    rectified_right = cv2.undistortPoints(
        points_right.reshape(-1, 1, 2), scaled_intrinsics(right_camera, width, height), None,
        R=rectification_right, P=projection_right,
    ).reshape(-1, 2)
    axis = 1 if vertical else 0
    samples = rectified_left[:, axis] - rectified_right[:, axis]
    samples = samples[np.isfinite(samples) & (samples > 0.0)]
    if len(samples) < 8:
        maximum = max(16, configured_maximum // 16 * 16)
        return 0, maximum, {"source": "configured_fallback", "samples": int(len(samples))}
    p02, p50, p98 = np.percentile(samples, (2, 50, 98))
    margin = max(4.0, 0.15 * (p98 - p02), 0.08 * p50)
    minimum = max(0, int(math.floor(p02 - margin)))
    required = max(16, int(math.ceil(p98 + margin - minimum)))
    count = min(max(16, configured_maximum // 16 * 16), int(math.ceil(required / 16.0) * 16))
    return minimum, count, {
        "source": "shared_sfm_percentiles",
        "samples": int(len(samples)),
        "p02_px": float(p02),
        "median_px": float(p50),
        "p98_px": float(p98),
        "margin_px": float(margin),
    }


def compute_stereo_pair(
    pair: StereoPair,
    left: ViewLike,
    right: ViewLike,
    left_camera: CameraLike,
    right_camera: CameraLike,
    images: Path,
    config: StereoConfig,
) -> StereoResult:
    """Rectify a calibrated pair and return only verified camera-Z measurements."""
    source_ratio = min(left_camera.height / left_camera.width, right_camera.height / right_camera.width)
    width = max(160, min(config.width, left_camera.width, right_camera.width))
    height = max(120, round(width * source_ratio))
    size = (width, height)
    left_rgb = _resize_rgb(images / left.name, size)
    right_rgb = _resize_rgb(images / right.name, size)
    left_k = scaled_intrinsics(left_camera, width, height)
    right_k = scaled_intrinsics(right_camera, width, height)
    rotation, translation = relative_pose(left, right)
    distortion = np.zeros(5, dtype=np.float64)
    rectify_left, rectify_right, projection_left, projection_right, q_matrix, roi_left, roi_right = cv2.stereoRectify(
        left_k, distortion, right_k, distortion, size, rotation, translation,
        flags=cv2.CALIB_ZERO_DISPARITY, alpha=0,
    )
    vertical_stereo = abs(float(projection_right[1, 3])) > abs(float(projection_right[0, 3]))
    baseline_projection = projection_right[1, 3] if vertical_stereo else projection_right[0, 3]
    # StereoSGBM represents x_left - x_right. A positive disparity requires
    # a negative translated projection. Vertical pairs are rotated temporarily
    # so their y disparity becomes a horizontal x disparity.
    if baseline_projection > 0:
        swapped = StereoPair(
            left_name=pair.right_name, right_name=pair.left_name,
            shared_points=pair.shared_points, shared_ratio=pair.shared_ratio,
            baseline=pair.baseline, angle_degrees=pair.angle_degrees,
            sharpness=pair.sharpness, luminance_difference=pair.luminance_difference,
            score=pair.score,
        )
        return compute_stereo_pair(swapped, right, left, right_camera, left_camera, images, config)

    map_lx, map_ly = cv2.initUndistortRectifyMap(left_k, distortion, rectify_left, projection_left, size, cv2.CV_32FC1)
    map_rx, map_ry = cv2.initUndistortRectifyMap(right_k, distortion, rectify_right, projection_right, size, cv2.CV_32FC1)
    rectified_left = cv2.remap(left_rgb, map_lx, map_ly, cv2.INTER_LINEAR, borderMode=cv2.BORDER_CONSTANT)
    rectified_right = cv2.remap(right_rgb, map_rx, map_ry, cv2.INTER_LINEAR, borderMode=cv2.BORDER_CONSTANT)
    gray_left = cv2.cvtColor(rectified_left, cv2.COLOR_RGB2GRAY)
    gray_right = cv2.cvtColor(rectified_right, cv2.COLOR_RGB2GRAY)
    left_roi = np.zeros((height, width), dtype=bool)
    x, y, roi_width, roi_height = roi_left
    left_roi[y:y + roi_height, x:x + roi_width] = True
    right_roi = np.zeros((height, width), dtype=bool)
    x, y, roi_width, roi_height = roi_right
    right_roi[y:y + roi_height, x:x + roi_width] = True
    if vertical_stereo:
        gray_left = cv2.rotate(gray_left, cv2.ROTATE_90_COUNTERCLOCKWISE)
        gray_right = cv2.rotate(gray_right, cv2.ROTATE_90_COUNTERCLOCKWISE)
        left_roi = cv2.rotate(left_roi.astype(np.uint8), cv2.ROTATE_90_COUNTERCLOCKWISE).astype(bool)
        right_roi = cv2.rotate(right_roi.astype(np.uint8), cv2.ROTATE_90_COUNTERCLOCKWISE).astype(bool)

    work_height, work_width = gray_left.shape
    maximum_disparities = max(16, min(config.number_of_disparities, (work_width // 3 // 16) * 16))
    if config.adaptive_disparity:
        minimum_disparity, disparities, disparity_estimate = _expected_disparity_range(
            left, right, left_camera, right_camera, size,
            rectify_left, rectify_right, projection_left, projection_right,
            vertical=vertical_stereo, configured_maximum=maximum_disparities,
        )
    else:
        minimum_disparity, disparities = 0, maximum_disparities
        disparity_estimate = {"source": "configured_fixed", "samples": 0}
    block_size = max(3, config.block_size | 1)
    common = dict(
        numDisparities=disparities, blockSize=block_size,
        P1=8 * block_size * block_size, P2=32 * block_size * block_size,
        disp12MaxDiff=1, uniquenessRatio=8, speckleWindowSize=80,
        speckleRange=2, preFilterCap=31, mode=cv2.STEREO_SGBM_MODE_SGBM_3WAY,
    )
    left_matcher = cv2.StereoSGBM_create(minDisparity=minimum_disparity, **common)
    right_matcher = cv2.StereoSGBM_create(minDisparity=-(minimum_disparity + disparities), **common)
    disparity = left_matcher.compute(gray_left, gray_right).astype(np.float32) / 16.0
    disparity_right = right_matcher.compute(gray_right, gray_left).astype(np.float32) / 16.0

    yy, xx = np.indices(disparity.shape)
    right_x = np.rint(xx - disparity).astype(np.int32)
    in_right = (right_x >= 0) & (right_x < work_width)
    sampled_right_disparity = np.full(disparity.shape, np.nan, dtype=np.float32)
    sampled_right_disparity[in_right] = disparity_right[yy[in_right], right_x[in_right]]
    lr_error = np.abs(disparity + sampled_right_disparity)
    corresponding_right_roi = np.zeros(disparity.shape, dtype=bool)
    corresponding_right_roi[in_right] = right_roi[yy[in_right], right_x[in_right]]
    geometric_overlap = left_roi & corresponding_right_roi
    within_range = (
        np.isfinite(disparity)
        & (disparity > max(0.5, minimum_disparity - 0.5))
        & (disparity < minimum_disparity + disparities)
    )
    valid_disparity = within_range & in_right & geometric_overlap
    disparity_range_eligible_count = int((within_range & in_right & geometric_overlap).sum())
    lr_valid = valid_disparity & np.isfinite(lr_error) & (lr_error <= config.lr_tolerance)

    warped_right = np.zeros_like(gray_left)
    warped_right[in_right] = gray_right[yy[in_right], right_x[in_right]]
    photometric_error = np.abs(gray_left.astype(np.float32) - warped_right.astype(np.float32)) / 255.0
    gx = cv2.Sobel(gray_left, cv2.CV_32F, 1, 0, ksize=3)
    gy = cv2.Sobel(gray_left, cv2.CV_32F, 0, 1, ksize=3)
    texture = np.sqrt(gx * gx + gy * gy)
    texture_confidence = np.clip(texture / 32.0, 0.0, 1.0)
    lr_confidence = np.exp(-np.nan_to_num(lr_error, nan=99.0) / max(config.lr_tolerance, 1e-6))
    photo_confidence = np.exp(-photometric_error / 0.12)
    confidence = (lr_confidence * photo_confidence * (0.25 + 0.75 * texture_confidence)).astype(np.float32)
    photometric_valid = photometric_error <= config.maximum_photometric_error

    occlusion = valid_disparity & ~lr_valid
    combined_error = np.where(
        valid_disparity,
        np.nan_to_num(lr_error, nan=config.lr_tolerance * 4) + photometric_error,
        np.nan,
    ).astype(np.float32)
    if vertical_stereo:
        disparity = cv2.rotate(disparity, cv2.ROTATE_90_CLOCKWISE)
        confidence = cv2.rotate(confidence, cv2.ROTATE_90_CLOCKWISE)
        valid_disparity = cv2.rotate(valid_disparity.astype(np.uint8), cv2.ROTATE_90_CLOCKWISE).astype(bool)
        lr_valid = cv2.rotate(lr_valid.astype(np.uint8), cv2.ROTATE_90_CLOCKWISE).astype(bool)
        photometric_valid = cv2.rotate(photometric_valid.astype(np.uint8), cv2.ROTATE_90_CLOCKWISE).astype(bool)
        geometric_overlap = cv2.rotate(geometric_overlap.astype(np.uint8), cv2.ROTATE_90_CLOCKWISE).astype(bool)
        left_roi = cv2.rotate(left_roi.astype(np.uint8), cv2.ROTATE_90_CLOCKWISE).astype(bool)
        occlusion = cv2.rotate(occlusion.astype(np.uint8), cv2.ROTATE_90_CLOCKWISE).astype(bool)
        combined_error = cv2.rotate(combined_error, cv2.ROTATE_90_CLOCKWISE)
    points_rectified = cv2.reprojectImageTo3D(disparity, q_matrix, handleMissingValues=False)
    points_original_left = np.einsum("ij,hwj->hwi", rectify_left.T, points_rectified)
    depth = points_original_left[..., 2].astype(np.float32)
    finite_depth = np.isfinite(depth) & (depth > 0.05) & (depth < config.maximum_depth)
    confidence_valid = confidence >= config.minimum_confidence
    pre_depth_valid = lr_valid & photometric_valid & confidence_valid & geometric_overlap
    valid = pre_depth_valid & finite_depth
    depth[~valid] = np.nan
    confidence[~valid] = 0.0
    epipolar = _rectified_sparse_error(
        left, right, left_camera, right_camera, size,
        rectify_left, rectify_right, projection_left, projection_right,
        vertical=vertical_stereo,
    )
    valid_count = int(valid.sum())
    # SGBM operates in rectified coordinates. Convert verified camera-Z back to
    # the original left image grid before exposing it to monocular correction.
    original_y, original_x = np.indices((height, width), dtype=np.float32)
    original_pixels = np.stack((original_x, original_y), axis=-1).reshape(-1, 1, 2)
    rectified_pixels = cv2.undistortPoints(
        original_pixels, left_k, distortion, R=rectify_left, P=projection_left,
    ).reshape(height, width, 2).astype(np.float32)
    rectified_valid = np.isfinite(depth).astype(np.float32)
    rectified_weight = confidence * rectified_valid
    mapped_weight = cv2.remap(
        rectified_weight, rectified_pixels[..., 0], rectified_pixels[..., 1],
        cv2.INTER_LINEAR, borderMode=cv2.BORDER_CONSTANT,
    )
    mapped_numerator = cv2.remap(
        np.nan_to_num(depth, nan=0.0) * rectified_weight,
        rectified_pixels[..., 0], rectified_pixels[..., 1],
        cv2.INTER_LINEAR, borderMode=cv2.BORDER_CONSTANT,
    )
    original_depth = np.divide(
        mapped_numerator, mapped_weight,
        out=np.full((height, width), np.nan, dtype=np.float32),
        where=mapped_weight > 1e-4,
    )
    original_confidence = np.clip(mapped_weight, 0.0, 1.0).astype(np.float32)
    original_valid = np.isfinite(original_depth) & (original_confidence > 1e-4)
    funnel = {
        "total_pixels": int(disparity.size),
        "rectified_left_roi_pixels": int(left_roi.sum()),
        "geometric_overlap_pixels": int(geometric_overlap.sum()),
        "disparity_range_eligible_pixels": disparity_range_eligible_count,
        "valid_disparity_pixels": int(valid_disparity.sum()),
        "left_right_consistent_pixels": int(lr_valid.sum()),
        "rejected_as_occlusion_pixels": int(occlusion.sum()),
        "rejected_photometric_pixels": int((lr_valid & ~photometric_valid).sum()),
        "rejected_confidence_pixels": int((lr_valid & photometric_valid & ~confidence_valid).sum()),
        "rejected_invalid_depth_pixels": int((lr_valid & photometric_valid & confidence_valid & ~finite_depth).sum()),
        "valid_rectified_pixels": valid_count,
        "valid_original_grid_pixels": int(original_valid.sum()),
        "whole_image_coverage": valid_count / max(1, disparity.size),
        "eligible_region_coverage": valid_count / max(1, int(geometric_overlap.sum())),
    }
    report = {
        "left": left.name,
        "right": right.name,
        "processing_size": [width, height],
        "shared_sfm_points": pair.shared_points,
        "baseline_m": pair.baseline,
        "view_angle_degrees": pair.angle_degrees,
        "rectification": epipolar,
        "disparity": {
            "number_of_disparities": disparities,
            "minimum_disparity": minimum_disparity,
            "estimated_range": disparity_estimate,
            "block_size": block_size,
            "positive_pixels": int(valid_disparity.sum()),
            "left_right_consistent_pixels": int(lr_valid.sum()),
            "valid_pixels": valid_count,
            "valid_fraction": valid_count / disparity.size,
            "median_valid_disparity_px": float(np.median(disparity[valid])) if valid_count else None,
        },
        "depth": {
            "convention": "z_along_original_left_camera_axis",
            "pixel_grid": "original_left_image",
            "median_m": float(np.median(depth[valid])) if valid_count else None,
            "p10_m": float(np.percentile(depth[valid], 10)) if valid_count else None,
            "p90_m": float(np.percentile(depth[valid], 90)) if valid_count else None,
        },
        "confidence": {
            "minimum_used": config.minimum_confidence,
            "mean_valid": float(np.mean(confidence[valid])) if valid_count else None,
        },
        "occluded_pixels": int(occlusion.sum()),
        "acceptance_funnel": funnel,
    }
    return StereoResult(
        left.name, right.name, disparity, original_depth, original_confidence,
        occlusion, combined_error, report,
    )


def conservative_local_depth_correction(
    monocular_depth: np.ndarray,
    stereo_depth: np.ndarray,
    stereo_confidence: np.ndarray,
    *,
    anchor_mask: np.ndarray | None = None,
    minimum_confidence: float = 0.52,
    maximum_relative_change: float = 0.22,
    smoothing_sigma: float = 7.0,
) -> tuple[np.ndarray, np.ndarray, dict[str, Any]]:
    """Correct local scale only around verified stereo measurements.

    The first result preserves monocular coverage. The hybrid result uses direct
    stereo measurements at supported pixels. Pixels without evidence remain
    explicitly monocular rather than being filled as stereo observations.
    """
    if monocular_depth.shape != stereo_depth.shape or monocular_depth.shape != stereo_confidence.shape:
        raise ValueError("Os mapas monocular, estéreo e de confiança precisam ter a mesma forma.")
    mono = monocular_depth.astype(np.float32, copy=False)
    stereo = stereo_depth.astype(np.float32, copy=False)
    confidence = np.clip(stereo_confidence.astype(np.float32, copy=False), 0.0, 1.0)
    valid = (
        np.isfinite(mono) & (mono > 0.05)
        & np.isfinite(stereo) & (stereo > 0.05)
        & (confidence >= minimum_confidence)
    )
    ratio = np.ones_like(mono, dtype=np.float32)
    ratio[valid] = stereo[valid] / mono[valid]
    lower, upper = 1.0 - maximum_relative_change, 1.0 + maximum_relative_change
    valid &= (ratio >= lower) & (ratio <= upper)
    clipped_ratio = np.clip(ratio, lower, upper)
    weights = np.where(valid, confidence, 0.0).astype(np.float32)
    numerator = cv2.GaussianBlur(clipped_ratio * weights, (0, 0), smoothing_sigma)
    denominator = cv2.GaussianBlur(weights, (0, 0), smoothing_sigma)
    local_ratio = np.divide(numerator, denominator, out=np.ones_like(numerator), where=denominator > 1e-4)
    # Do not diffuse corrections across strong monocular depth discontinuities.
    gradient_x = cv2.Sobel(mono, cv2.CV_32F, 1, 0, ksize=3)
    gradient_y = cv2.Sobel(mono, cv2.CV_32F, 0, 1, ksize=3)
    relative_gradient = np.sqrt(gradient_x * gradient_x + gradient_y * gradient_y) / np.maximum(mono, 0.05)
    support = (denominator > 0.015) & np.isfinite(mono) & (relative_gradient < 0.35)
    corrected = mono.copy()
    corrected[support] = mono[support] * np.clip(local_ratio[support], lower, upper)
    if anchor_mask is not None:
        if anchor_mask.shape != mono.shape:
            raise ValueError("A máscara de âncoras precisa acompanhar a profundidade.")
        corrected[anchor_mask.astype(bool)] = mono[anchor_mask.astype(bool)]
    hybrid = corrected.copy()
    blend = np.where(valid, confidence, 0.0)
    hybrid[valid] = corrected[valid] * (1.0 - blend[valid]) + stereo[valid] * blend[valid]
    changed = support & np.isfinite(mono) & (np.abs(corrected - mono) > 1e-6)
    report = {
        "method": "confidence_weighted_local_scale_regularization",
        "valid_stereo_pixels": int(valid.sum()),
        "valid_stereo_fraction": float(valid.mean()),
        "corrected_pixels": int(changed.sum()),
        "corrected_fraction": float(changed.mean()),
        "maximum_relative_change": maximum_relative_change,
        "median_relative_change": float(np.median(np.abs(corrected[changed] - mono[changed]) / mono[changed])) if np.any(changed) else 0.0,
        "anchor_pixels_preserved": int(anchor_mask.astype(bool).sum()) if anchor_mask is not None else 0,
        "unknown_pixels_promoted_to_stereo": 0,
    }
    return corrected, hybrid, report


def resize_stereo_evidence(
    result: StereoResult,
    shape: tuple[int, int],
) -> tuple[np.ndarray, np.ndarray]:
    height, width = shape
    valid = np.isfinite(result.depth).astype(np.float32)
    weighted = np.nan_to_num(result.depth, nan=0.0) * result.confidence
    numerator = cv2.resize(weighted, (width, height), interpolation=cv2.INTER_AREA)
    denominator = cv2.resize(result.confidence * valid, (width, height), interpolation=cv2.INTER_AREA)
    depth = np.divide(numerator, denominator, out=np.full((height, width), np.nan, dtype=np.float32), where=denominator > 1e-4)
    confidence = np.clip(denominator, 0.0, 1.0).astype(np.float32)
    return depth.astype(np.float32), confidence


def _observed_world_voxels(
    result: StereoResult,
    view: ViewLike,
    camera: CameraLike,
    voxel_size: float,
) -> set[tuple[int, int, int]]:
    """Return measured world-space cells; unknown pixels never create cells."""
    valid_y, valid_x = np.nonzero(np.isfinite(result.depth) & (result.confidence > 0.0))
    if not len(valid_x):
        return set()
    height, width = result.depth.shape
    z = result.depth[valid_y, valid_x].astype(np.float64)
    fx = camera.focal * width / camera.width
    fy = camera.focal * height / camera.height
    cx = camera.cx * width / camera.width
    cy = camera.cy * height / camera.height
    camera_points = np.stack(((valid_x - cx) * z / fx, (valid_y - cy) * z / fy, z), axis=1)
    world_points = (view.rotation.T @ (camera_points - view.translation).T).T
    quantized = np.floor(world_points / max(voxel_size, 1e-4)).astype(np.int64)
    return {tuple(map(int, cell)) for cell in np.unique(quantized, axis=0)}


def build_stereo_evidence(
    views: list[ViewLike],
    cameras: dict[int, CameraLike],
    images: Path,
    target_shapes: dict[str, tuple[int, int]],
    config: StereoConfig,
    *,
    sparse_points: dict[int, np.ndarray] | None = None,
    excluded_names: set[str] | None = None,
    cancel: Callable[[], bool] | None = None,
) -> tuple[dict[str, np.ndarray], dict[str, np.ndarray], dict[str, Any], list[StereoResult]]:
    """Compute and aggregate bounded stereo evidence on each source image grid."""
    selected, selection_report = select_stereo_pairs(
        views, cameras, images, config, excluded_names=excluded_names,
    )
    by_name = {view.name: view for view in views}
    results: list[StereoResult] = []
    validation_reports: list[dict[str, Any]] = []
    accumulated_depth: dict[str, np.ndarray] = {}
    accumulated_weight: dict[str, np.ndarray] = {}
    observations: dict[str, np.ndarray] = {}
    covered_voxels: set[tuple[int, int, int]] = set()
    voxel_pairs: dict[tuple[int, int, int], set[str]] = {}
    voxel_cameras: dict[tuple[int, int, int], set[str]] = {}
    for pair in selected:
        if cancel and cancel():
            raise InterruptedError("Reconstrução cancelada durante o estéreo calibrado.")
        left = by_name[pair.left_name]
        right = by_name[pair.right_name]
        result = compute_stereo_pair(
            pair, left, right, cameras[left.camera_id], cameras[right.camera_id], images, config,
        )
        # compute_stereo_pair can swap a vertical pair to preserve positive disparity.
        actual_left = by_name[result.left_name]
        shape = target_shapes.get(result.left_name)
        if shape is None:
            continue
        depth, confidence = resize_stereo_evidence(result, shape)
        accepted_for_fusion = confidence >= config.minimum_confidence
        result.report["acceptance_funnel"]["pixels_effectively_used_for_correction_or_fusion"] = int(accepted_for_fusion.sum())
        result.report["acceptance_funnel"]["original_grid_eligible_coverage"] = float(accepted_for_fusion.mean())
        pair_voxels = _observed_world_voxels(
            result, actual_left, cameras[actual_left.camera_id], config.spatial_voxel_size,
        )
        new_voxels = pair_voxels - covered_voxels
        pair_id = f"{result.left_name}|{result.right_name}"
        for voxel in pair_voxels:
            voxel_pairs.setdefault(voxel, set()).add(pair_id)
            voxel_cameras.setdefault(voxel, set()).update((result.left_name, result.right_name))
        covered_voxels.update(pair_voxels)
        result.report["spatial_coverage"] = {
            "voxel_size_m": config.spatial_voxel_size,
            "observed_voxels": len(pair_voxels),
            "new_voxels": len(new_voxels),
            "redundant_voxels": len(pair_voxels) - len(new_voxels),
            "cumulative_voxels": len(covered_voxels),
            "incremental_gain_fraction": len(new_voxels) / max(1, len(covered_voxels)),
        }
        weighted = np.nan_to_num(depth, nan=0.0) * confidence
        if result.left_name not in accumulated_depth:
            accumulated_depth[result.left_name] = np.zeros(shape, dtype=np.float32)
            accumulated_weight[result.left_name] = np.zeros(shape, dtype=np.float32)
            observations[result.left_name] = np.zeros(shape, dtype=np.uint8)
        accumulated_depth[result.left_name] += weighted
        accumulated_weight[result.left_name] += confidence
        observations[result.left_name] += (confidence >= config.minimum_confidence).astype(np.uint8)

        errors: list[float] = []
        camera = cameras[actual_left.camera_id]
        if sparse_points:
            for x, y, point_id in actual_left.observations:
                world = sparse_points.get(point_id)
                if world is None:
                    continue
                z = float((actual_left.rotation @ world + actual_left.translation)[2])
                px = min(shape[1] - 1, max(0, int(round(x * shape[1] / camera.width))))
                py = min(shape[0] - 1, max(0, int(round(y * shape[0] / camera.height))))
                estimate = float(depth[py, px])
                if z > 0.05 and math.isfinite(estimate):
                    errors.append(abs(estimate - z) / z)
        sfm_report = {
            "samples": len(errors),
            "median_relative_error": float(np.median(errors)) if errors else None,
            "p90_relative_error": float(np.percentile(errors, 90)) if errors else None,
        }
        result.report["sfm_validation"] = sfm_report
        validation_reports.append(result.report)
        results.append(result)

    depth_maps: dict[str, np.ndarray] = {}
    confidence_maps: dict[str, np.ndarray] = {}
    for name, numerator in accumulated_depth.items():
        weight = accumulated_weight[name]
        depth_maps[name] = np.divide(
            numerator, weight,
            out=np.full(numerator.shape, np.nan, dtype=np.float32),
            where=weight > 1e-4,
        )
        # Repeated independent pairs raise confidence; a single observation is
        # retained but never mislabeled as three-view evidence.
        repeat_factor = np.minimum(observations[name].astype(np.float32) / 2.0, 1.0)
        confidence_maps[name] = np.clip(weight / np.maximum(observations[name], 1), 0.0, 1.0) * (
            0.72 + 0.28 * repeat_factor
        )

    usable = [report for report in validation_reports if report["disparity"]["valid_pixels"] > 0]
    report = {
        "method": "opencv_calibrated_stereo_sgbm",
        "opencv_version": cv2.__version__,
        "selection": selection_report,
        "pair_reports": validation_reports,
        "summary": {
            "attempted_pairs": len(selected),
            "usable_pairs": len(usable),
            "views_with_evidence": len(depth_maps),
            "valid_pixels": sum(report["disparity"]["valid_pixels"] for report in validation_reports),
            "sfm_validation_samples": sum(report["sfm_validation"]["samples"] for report in validation_reports),
            "median_pair_sfm_relative_error": float(np.median([
                report["sfm_validation"]["median_relative_error"] for report in usable
                if report["sfm_validation"]["median_relative_error"] is not None
            ])) if any(
                report["sfm_validation"]["median_relative_error"] is not None for report in usable
            ) else None,
            "observed_world_voxels": len(covered_voxels),
            "voxels_with_two_independent_pairs": sum(len(pairs) >= 2 for pairs in voxel_pairs.values()),
            "voxels_with_three_or_more_cameras": sum(len(names) >= 3 for names in voxel_cameras.values()),
            "redundant_voxel_observations": sum(max(0, len(pairs) - 1) for pairs in voxel_pairs.values()),
        },
        "limits": {
            "width": config.width,
            "maximum_pairs": config.maximum_pairs,
            "maximum_pairs_per_view": config.maximum_pairs_per_view,
            "minimum_confidence": config.minimum_confidence,
            "spatial_voxel_size_m": config.spatial_voxel_size,
        },
    }
    return depth_maps, confidence_maps, report, results

