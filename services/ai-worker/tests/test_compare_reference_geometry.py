from __future__ import annotations

import numpy as np

from scripts.compare_reference_geometry import deterministic_sample, nearest_distances


def test_deterministic_sample_preserves_endpoints_and_limit() -> None:
    values = np.arange(60, dtype=np.float32).reshape(20, 3)

    sampled = deterministic_sample(values, 5)

    assert len(sampled) == 5
    assert sampled[0].tolist() == values[0].tolist()
    assert sampled[-1].tolist() == values[-1].tolist()
    assert deterministic_sample(values, 20) is values


def test_nearest_distances_matches_known_points() -> None:
    source = np.asarray([[0, 0, 0], [2, 0, 0]], dtype=np.float32)
    target = np.asarray([[0, 1, 0], [3, 0, 0]], dtype=np.float32)

    distances = nearest_distances(source, target, chunk=1)

    assert np.allclose(distances, [1.0, 1.0])
