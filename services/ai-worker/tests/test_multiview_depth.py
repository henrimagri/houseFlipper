from __future__ import annotations

import numpy as np

from app.multiview_depth import (
    ConsistencyConfig,
    DepthObservation,
    evaluate_multiview_consistency,
)


def _observation(
    name: str,
    depth: np.ndarray,
    *,
    center_x: float = 0.0,
    calibration_confidence: float = 0.95,
) -> DepthObservation:
    rotation = np.eye(3)
    translation = np.asarray([-center_x, 0.0, 0.0])
    return DepthObservation(
        name=name,
        depth=depth.astype(np.float32),
        rotation=rotation,
        translation=translation,
        focal_x=42.0,
        focal_y=42.0,
        cx=23.5,
        cy=23.5,
        calibration_confidence=calibration_confidence,
    )


def _config() -> ConsistencyConfig:
    return ConsistencyConfig(
        pixel_stride=4,
        relative_tolerance=0.05,
        absolute_tolerance=0.02,
        maximum_neighbours=3,
        minimum_pair_samples=12,
    )


def test_multiview_consistency_accepts_matching_metric_depth() -> None:
    depth = np.full((48, 48), 2.0, dtype=np.float32)
    observations = [
        _observation("left", depth, center_x=-0.08),
        _observation("centre", depth),
        _observation("right", depth, center_x=0.08),
    ]

    confidence, states, report = evaluate_multiview_consistency(observations, _config())

    assert report["depth_convention"] == "camera_z_not_radial_distance"
    assert report["summary"]["usable_pairs"] == 6
    assert report["summary"]["inconsistent_samples"] == 0
    assert report["summary"]["consistent_samples"] > 100
    assert float(np.max(confidence["centre"])) > 0.8
    assert int(np.sum(states["centre"] == 3)) > 50


def test_multiview_consistency_reduces_confidence_for_wrong_depth_patch() -> None:
    depth = np.full((48, 48), 2.0, dtype=np.float32)
    wrong = depth.copy()
    wrong[12:36, 12:36] = 2.6
    observations = [
        _observation("reference-a", depth, center_x=-0.05),
        _observation("wrong", wrong),
        _observation("reference-b", depth, center_x=0.05),
    ]

    confidence, states, report = evaluate_multiview_consistency(observations, _config())

    assert report["summary"]["inconsistent_samples"] > 0
    assert float(confidence["wrong"][24, 24]) < float(confidence["wrong"][8, 8])
    assert states["wrong"][24, 24] == 2


def test_multiview_consistency_reports_occlusion_without_false_rejection() -> None:
    source = np.full((48, 48), 2.0, dtype=np.float32)
    target = source.copy()
    target[12:36, 12:36] = 1.0
    observations = [_observation("source", source), _observation("occluder", target, center_x=0.4)]

    _, states, report = evaluate_multiview_consistency(observations, _config())

    source_to_target = next(
        pair for pair in report["pair_reports"]
        if pair["source"] == "source" and pair["target"] == "occluder"
    )
    assert source_to_target["occluded"] > 0
    assert states["source"][24, 24] != 2


def test_calibration_confidence_caps_geometric_confidence() -> None:
    depth = np.full((48, 48), 2.0, dtype=np.float32)
    observations = [
        _observation("weak", depth, calibration_confidence=0.25),
        _observation("strong", depth, center_x=0.05, calibration_confidence=0.95),
    ]

    confidence, _, _ = evaluate_multiview_consistency(observations, _config())

    assert float(np.max(confidence["weak"])) <= 0.25
    assert float(np.max(confidence["strong"])) > 0.75
