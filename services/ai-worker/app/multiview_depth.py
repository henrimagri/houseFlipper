from __future__ import annotations

from dataclasses import dataclass
from typing import Any

import numpy as np


@dataclass(frozen=True)
class DepthObservation:
    name: str
    depth: np.ndarray
    rotation: np.ndarray
    translation: np.ndarray
    focal_x: float
    focal_y: float
    cx: float
    cy: float
    calibration_confidence: float = 1.0

    @property
    def camera_center(self) -> np.ndarray:
        return -self.rotation.T @ self.translation


@dataclass(frozen=True)
class ConsistencyConfig:
    pixel_stride: int = 12
    relative_tolerance: float = 0.10
    absolute_tolerance: float = 0.025
    maximum_neighbours: int = 8
    minimum_pair_samples: int = 24
    discontinuity_threshold: float = 0.15

    def validated(self) -> "ConsistencyConfig":
        if not 2 <= self.pixel_stride <= 64:
            raise ValueError("O stride de consistência deve ficar entre 2 e 64.")
        if not 0.01 <= self.relative_tolerance <= 0.5:
            raise ValueError("A tolerância relativa deve ficar entre 1% e 50%.")
        if not 0.001 <= self.absolute_tolerance <= 0.5:
            raise ValueError("A tolerância absoluta deve ficar entre 1 mm e 50 cm.")
        if not 1 <= self.maximum_neighbours <= 32:
            raise ValueError("O máximo de vistas vizinhas deve ficar entre 1 e 32.")
        return self


def _sample_grid(shape: tuple[int, int], stride: int) -> tuple[np.ndarray, np.ndarray]:
    height, width = shape
    ys = np.arange(stride, height - stride, stride, dtype=np.int64)
    xs = np.arange(stride, width - stride, stride, dtype=np.int64)
    return np.meshgrid(xs, ys)


def _project_source(
    source: DepthObservation,
    target: DepthObservation,
    px: np.ndarray,
    py: np.ndarray,
) -> tuple[np.ndarray, np.ndarray, np.ndarray, np.ndarray]:
    source_depth = source.depth[py, px]
    source_camera = np.stack((
        (px - source.cx) / source.focal_x * source_depth,
        (py - source.cy) / source.focal_y * source_depth,
        source_depth,
    ), axis=-1)
    world = (source.rotation.T @ (source_camera - source.translation).T).T
    target_camera = (target.rotation @ world.T).T + target.translation
    target_z = target_camera[:, 2]
    target_x = np.rint(target.focal_x * target_camera[:, 0] / np.maximum(target_z, 1e-9) + target.cx).astype(np.int64)
    target_y = np.rint(target.focal_y * target_camera[:, 1] / np.maximum(target_z, 1e-9) + target.cy).astype(np.int64)
    inside = (
        np.isfinite(source_depth) & (source_depth > 0.05) & (target_z > 0.05)
        & (target_x >= 0) & (target_x < target.depth.shape[1])
        & (target_y >= 0) & (target_y < target.depth.shape[0])
    )
    return target_x, target_y, target_z, inside


def evaluate_multiview_consistency(
    observations: list[DepthObservation],
    config: ConsistencyConfig = ConsistencyConfig(),
) -> tuple[dict[str, np.ndarray], dict[str, np.ndarray], dict[str, Any]]:
    """Reproject metric depth between calibrated cameras.

    Confidence is evidence, not a semantic prediction. Occluded samples are
    reported separately and do not count as inconsistent observations.
    """
    config = config.validated()
    confidence_maps: dict[str, np.ndarray] = {}
    state_maps: dict[str, np.ndarray] = {}
    view_reports: list[dict[str, Any]] = []
    pair_reports: list[dict[str, Any]] = []

    for source_index, source in enumerate(observations):
        px_grid, py_grid = _sample_grid(source.depth.shape, config.pixel_stride)
        px = px_grid.ravel()
        py = py_grid.ravel()
        source_values = source.depth[py, px]
        valid_source = np.isfinite(source_values) & (source_values > 0.05)
        checked = np.zeros(len(px), dtype=np.uint16)
        consistent = np.zeros(len(px), dtype=np.uint16)
        inconsistent = np.zeros(len(px), dtype=np.uint16)
        occluded = np.zeros(len(px), dtype=np.uint16)

        distances = [
            (float(np.linalg.norm(source.camera_center - candidate.camera_center)), index)
            for index, candidate in enumerate(observations) if index != source_index
        ]
        neighbours = [index for _, index in sorted(distances)[:config.maximum_neighbours]]
        used_pairs = 0
        for target_index in neighbours:
            target = observations[target_index]
            target_x, target_y, projected_z, inside = _project_source(source, target, px, py)
            target_depth = np.full(len(px), np.nan, dtype=np.float64)
            target_depth[inside] = target.depth[target_y[inside], target_x[inside]]
            comparable = inside & np.isfinite(target_depth) & (target_depth > 0.05)
            delta = target_depth - projected_z
            tolerance = np.maximum(
                config.absolute_tolerance,
                config.relative_tolerance * np.minimum(target_depth, projected_z),
            )
            nearby_parallel_view = (
                float(np.linalg.norm(source.camera_center - target.camera_center))
                <= max(config.absolute_tolerance * 6, 0.10)
                and float(np.dot(source.rotation[2], target.rotation[2])) >= 0.98
            )
            pair_occluded = comparable & (delta < -tolerance) & (not nearby_parallel_view)
            pair_consistent = comparable & (np.abs(delta) <= tolerance)
            pair_inconsistent = comparable & (
                (delta > tolerance) | ((delta < -tolerance) & nearby_parallel_view)
            )
            comparable_count = int(np.sum(pair_consistent | pair_inconsistent))
            if comparable_count < config.minimum_pair_samples:
                continue
            used_pairs += 1
            checked += (pair_consistent | pair_inconsistent).astype(np.uint16)
            consistent += pair_consistent.astype(np.uint16)
            inconsistent += pair_inconsistent.astype(np.uint16)
            occluded += pair_occluded.astype(np.uint16)
            relative_error = np.abs(delta[pair_consistent | pair_inconsistent]) / np.maximum(
                projected_z[pair_consistent | pair_inconsistent], 1e-6,
            )
            pair_reports.append({
                "source": source.name,
                "target": target.name,
                "compared": comparable_count,
                "consistent": int(np.sum(pair_consistent)),
                "inconsistent": int(np.sum(pair_inconsistent)),
                "occluded": int(np.sum(pair_occluded)),
                "median_relative_depth_error": float(np.median(relative_error)),
                "p90_relative_depth_error": float(np.quantile(relative_error, 0.90)),
            })

        base = float(np.clip(source.calibration_confidence, 0.05, 1.0))
        sampled_confidence = np.full(len(px), base * 0.30, dtype=np.float32)
        has_comparison = checked > 0
        ratio = (consistent + 0.5) / (checked + 0.5)
        repeated = np.minimum(checked.astype(np.float32) / 2.0, 1.0)
        sampled_confidence[has_comparison] = (
            base * (0.25 + 0.75 * ratio[has_comparison])
            * (0.60 + 0.40 * repeated[has_comparison])
        )
        sampled_state = np.ones(len(px), dtype=np.uint8)
        sampled_state[has_comparison & (inconsistent > consistent)] = 2
        # A source plus two independent target observations is the minimum for
        # the reliable state. A single pair remains useful but stays uncertain.
        repeated_observation = checked >= 2
        sampled_state[has_comparison & repeated_observation & (ratio >= 0.60)] = 3

        right = np.roll(source.depth, -config.pixel_stride, axis=1)
        down = np.roll(source.depth, -config.pixel_stride, axis=0)
        discontinuity = (
            np.abs(right[py, px] - source_values) / np.maximum(source_values, 1e-6)
            > config.discontinuity_threshold
        ) | (
            np.abs(down[py, px] - source_values) / np.maximum(source_values, 1e-6)
            > config.discontinuity_threshold
        )
        sampled_confidence[discontinuity] *= 0.45
        sampled_state[discontinuity & (sampled_state == 3)] = 1

        # Reprojection runs on a bounded regular grid. Propagate each sampled
        # value to its nearest grid cell so TSDF projections between sample
        # coordinates receive evidence instead of an arbitrary low default.
        sample_height, sample_width = px_grid.shape
        sampled_confidence_grid = sampled_confidence.reshape((sample_height, sample_width))
        sampled_state_grid = sampled_state.reshape((sample_height, sample_width))
        sample_x = px_grid[0]
        sample_y = py_grid[:, 0]
        dense_x = np.argmin(
            np.abs(np.arange(source.depth.shape[1])[:, None] - sample_x[None, :]), axis=1,
        )
        dense_y = np.argmin(
            np.abs(np.arange(source.depth.shape[0])[:, None] - sample_y[None, :]), axis=1,
        )
        confidence = sampled_confidence_grid[np.ix_(dense_y, dense_x)].astype(np.float32)
        state = sampled_state_grid[np.ix_(dense_y, dense_x)].astype(np.uint8)
        invalid_depth = ~np.isfinite(source.depth) | (source.depth <= 0.05)
        confidence[invalid_depth] = 0.0
        state[invalid_depth] = 0
        confidence_maps[source.name] = confidence
        state_maps[source.name] = state
        view_reports.append({
            "image": source.name,
            "neighbour_pairs": used_pairs,
            "sampled_valid_pixels": int(np.sum(valid_source)),
            "compared_samples": int(np.sum(checked)),
            "consistent_samples": int(np.sum(consistent)),
            "inconsistent_samples": int(np.sum(inconsistent)),
            "occluded_samples": int(np.sum(occluded)),
            "mean_confidence": float(np.mean(sampled_confidence[valid_source])) if np.any(valid_source) else 0.0,
            "reliable_samples": int(np.sum(sampled_state == 3)),
            "single_pair_only_samples": int(np.sum(has_comparison & ~repeated_observation)),
            "uncertain_samples": int(np.sum(sampled_state == 1)),
            "inconsistent_classified_samples": int(np.sum(sampled_state == 2)),
        })

    compared = sum(report["compared"] for report in pair_reports)
    report = {
        "method": "calibrated_depth_geometric_reprojection",
        "depth_convention": "camera_z_not_radial_distance",
        "world_to_camera": "x_camera = R * x_world + t",
        "config": {
            "pixel_stride": config.pixel_stride,
            "relative_tolerance": config.relative_tolerance,
            "absolute_tolerance_m": config.absolute_tolerance,
            "maximum_neighbours": config.maximum_neighbours,
            "minimum_pair_samples": config.minimum_pair_samples,
        },
        "view_reports": view_reports,
        "pair_reports": pair_reports,
        "summary": {
            "views": len(observations),
            "usable_pairs": len(pair_reports),
            "compared_samples": compared,
            "consistent_samples": sum(report["consistent"] for report in pair_reports),
            "inconsistent_samples": sum(report["inconsistent"] for report in pair_reports),
            "occluded_samples": sum(report["occluded"] for report in pair_reports),
        },
    }
    return confidence_maps, state_maps, report
