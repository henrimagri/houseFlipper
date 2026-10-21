from __future__ import annotations

import time
import warnings
from dataclasses import dataclass
from typing import Any

import cv2
import numpy as np

from .calibrated_stereo import CameraLike, ViewLike, camera_center, relative_pose, scaled_intrinsics


@dataclass(frozen=True)
class PlaneSweepConfig:
    width: int = 320
    depth_planes: int = 48
    maximum_support_views: int = 4
    minimum_support_views: int = 2
    patch_size: int = 5
    minimum_confidence: float = 0.08
    maximum_seconds: float = 20.0


@dataclass
class PlaneSweepResult:
    depth: np.ndarray
    confidence: np.ndarray
    valid: np.ndarray
    report: dict[str, Any]


def _resize(image: np.ndarray, width: int, height: int) -> np.ndarray:
    return cv2.resize(image, (width, height), interpolation=cv2.INTER_AREA)


def plane_sweep_depth(
    reference: ViewLike,
    supports: list[ViewLike],
    cameras: dict[int, CameraLike],
    images_rgb: dict[str, np.ndarray],
    *,
    minimum_depth: float,
    maximum_depth: float,
    config: PlaneSweepConfig = PlaneSweepConfig(),
) -> PlaneSweepResult:
    """Bounded CPU fronto-parallel plane sweep requiring independent cameras."""
    if maximum_depth <= minimum_depth or minimum_depth <= 0.0:
        raise ValueError("A faixa de profundidade do plane sweep é inválida.")
    independent = []
    seen_names = {reference.name}
    centres = [camera_center(reference)]
    for view in supports:
        centre = camera_center(view)
        if view.name in seen_names or any(float(np.linalg.norm(centre - value)) < 1e-4 for value in centres):
            continue
        independent.append(view)
        seen_names.add(view.name)
        centres.append(centre)
    independent = independent[: config.maximum_support_views]
    if len(independent) < config.minimum_support_views:
        raise ValueError("Plane sweep exige ao menos duas câmeras de suporte independentes.")
    reference_camera = cameras[reference.camera_id]
    width = max(96, min(config.width, reference_camera.width))
    height = max(72, round(width * reference_camera.height / reference_camera.width))
    reference_gray = cv2.cvtColor(_resize(images_rgb[reference.name], width, height), cv2.COLOR_RGB2GRAY).astype(np.float32) / 255.0
    kernel = max(3, config.patch_size | 1)
    reference_gray = cv2.boxFilter(reference_gray, -1, (kernel, kernel), normalize=True)
    reference_k = scaled_intrinsics(reference_camera, width, height)
    reference_k_inverse = np.linalg.inv(reference_k)
    support_data = []
    for support in independent:
        camera = cameras[support.camera_id]
        support_gray = cv2.cvtColor(_resize(images_rgb[support.name], width, height), cv2.COLOR_RGB2GRAY).astype(np.float32) / 255.0
        support_gray = cv2.boxFilter(support_gray, -1, (kernel, kernel), normalize=True)
        support_k = scaled_intrinsics(camera, width, height)
        rotation, translation = relative_pose(reference, support)
        support_data.append((support, support_gray, support_k, rotation, translation))

    planes = np.geomspace(minimum_depth, maximum_depth, max(8, min(config.depth_planes, 128))).astype(np.float32)
    best_cost = np.full((height, width), np.inf, dtype=np.float32)
    second_cost = np.full_like(best_cost, np.inf)
    best_depth = np.full_like(best_cost, np.nan)
    best_support = np.zeros((height, width), dtype=np.uint8)
    ones = np.ones((height, width), dtype=np.uint8)
    normal = np.asarray([[0.0, 0.0, 1.0]], dtype=np.float64)
    started = time.perf_counter()
    evaluated_planes = 0
    for depth in planes:
        if time.perf_counter() - started > config.maximum_seconds:
            break
        costs = []
        validities = []
        for _, support_gray, support_k, rotation, translation in support_data:
            homography = support_k @ (rotation + translation.reshape(3, 1) @ normal / float(depth)) @ reference_k_inverse
            flags = cv2.INTER_LINEAR | cv2.WARP_INVERSE_MAP
            warped = cv2.warpPerspective(support_gray, homography, (width, height), flags=flags, borderMode=cv2.BORDER_CONSTANT)
            valid = cv2.warpPerspective(ones, homography, (width, height), flags=cv2.INTER_NEAREST | cv2.WARP_INVERSE_MAP, borderMode=cv2.BORDER_CONSTANT).astype(bool)
            costs.append(np.abs(reference_gray - warped))
            validities.append(valid)
        stack = np.stack(costs)
        valid_stack = np.stack(validities)
        support_count = valid_stack.sum(axis=0)
        stack[~valid_stack] = np.nan
        # Median aggregation limits the influence of one illumination change or occlusion.
        with warnings.catch_warnings():
            warnings.simplefilter("ignore", RuntimeWarning)
            cost = np.nanmedian(stack, axis=0).astype(np.float32)
        eligible = support_count >= config.minimum_support_views
        cost[~eligible] = np.inf
        improved = cost < best_cost
        second_cost = np.where(improved, best_cost, np.minimum(second_cost, cost))
        best_cost = np.where(improved, cost, best_cost)
        best_depth = np.where(improved, depth, best_depth)
        best_support = np.where(improved, support_count, best_support)
        evaluated_planes += 1

    texture_x = cv2.Sobel(reference_gray, cv2.CV_32F, 1, 0, ksize=3)
    texture_y = cv2.Sobel(reference_gray, cv2.CV_32F, 0, 1, ksize=3)
    texture = np.clip(np.sqrt(texture_x * texture_x + texture_y * texture_y) / 0.20, 0.0, 1.0)
    finite_second = np.isfinite(second_cost) & np.isfinite(best_cost)
    margin = np.zeros_like(best_cost)
    margin[finite_second] = (second_cost[finite_second] - best_cost[finite_second]) / np.maximum(second_cost[finite_second], 1e-4)
    confidence = np.clip(margin * texture * np.minimum(best_support / 3.0, 1.0), 0.0, 1.0).astype(np.float32)
    valid = np.isfinite(best_depth) & (best_support >= config.minimum_support_views) & (confidence >= config.minimum_confidence)
    depth = best_depth.astype(np.float32)
    depth[~valid] = np.nan
    confidence[~valid] = 0.0
    elapsed = time.perf_counter() - started
    report = {
        "method": "bounded_cpu_multiview_plane_sweep",
        "reference": reference.name,
        "support_views": [view.name for view in independent],
        "independent_cameras": 1 + len(independent),
        "processing_size": [width, height],
        "depth_range_m": [minimum_depth, maximum_depth],
        "requested_planes": len(planes),
        "evaluated_planes": evaluated_planes,
        "valid_pixels": int(valid.sum()),
        "valid_fraction": float(valid.mean()),
        "median_confidence": float(np.median(confidence[valid])) if np.any(valid) else None,
        "elapsed_seconds": round(elapsed, 3),
        "estimated_peak_cost_volume_bytes": int(len(independent) * height * width * 5),
        "bounded": evaluated_planes <= len(planes) and elapsed <= config.maximum_seconds + 1.0,
    }
    return PlaneSweepResult(depth, confidence, valid, report)
