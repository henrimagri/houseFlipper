from __future__ import annotations

import argparse
import json
from pathlib import Path

import numpy as np
import trimesh


def read_obj_vertices(path: Path, maximum: int) -> np.ndarray:
    vertices: list[tuple[float, float, float]] = []
    with path.open("r", encoding="utf-8", errors="replace") as stream:
        for line in stream:
            if not line.startswith("v "):
                continue
            values = line.split()
            if len(values) >= 4:
                vertices.append((float(values[1]), float(values[2]), float(values[3])))
    array = np.asarray(vertices, dtype=np.float32)
    if len(array) > maximum:
        indices = np.linspace(0, len(array) - 1, maximum, dtype=np.int64)
        array = array[indices]
    return array


def deterministic_sample(values: np.ndarray, maximum: int) -> np.ndarray:
    if len(values) <= maximum:
        return values
    return values[np.linspace(0, len(values) - 1, maximum, dtype=np.int64)]


def nearest_distances(source: np.ndarray, target: np.ndarray, chunk: int = 128) -> np.ndarray:
    distances = np.empty(len(source), dtype=np.float32)
    for start in range(0, len(source), chunk):
        values = source[start:start + chunk, None, :] - target[None, :, :]
        distances[start:start + chunk] = np.sqrt(np.min(np.sum(values * values, axis=2), axis=1))
    return distances


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--generated", type=Path, required=True)
    parser.add_argument("--reference", type=Path, required=True)
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()

    generated_mesh = trimesh.load(args.generated, force="mesh", process=False)
    generated_all = np.asarray(generated_mesh.vertices, dtype=np.float32)
    generated = deterministic_sample(generated_all, 10_000)
    reference = read_obj_vertices(args.reference, maximum=10_000)
    generated_to_reference = nearest_distances(generated, reference)
    reference_subset = deterministic_sample(reference, 10_000)
    reference_to_generated = nearest_distances(reference_subset, generated)

    result = {
        "comparison_only": True,
        "reference_used_during_generation": False,
        "coordinate_alignment": "dataset_colmap_world_coordinates_no_post_alignment",
        "samples": {
            "generated": len(generated),
            "generated_total_vertices": len(generated_all),
            "reference": len(reference_subset),
        },
        "accuracy_generated_to_reference_m": {
            "median": float(np.median(generated_to_reference)),
            "p90": float(np.quantile(generated_to_reference, 0.90)),
            "within_5cm": float(np.mean(generated_to_reference <= 0.05)),
            "within_10cm": float(np.mean(generated_to_reference <= 0.10)),
            "within_20cm": float(np.mean(generated_to_reference <= 0.20)),
        },
        "completeness_reference_to_generated_m": {
            "median": float(np.median(reference_to_generated)),
            "p90": float(np.quantile(reference_to_generated, 0.90)),
            "within_5cm": float(np.mean(reference_to_generated <= 0.05)),
            "within_10cm": float(np.mean(reference_to_generated <= 0.10)),
            "within_20cm": float(np.mean(reference_to_generated <= 0.20)),
        },
        "generated_bounds": np.asarray(generated_mesh.bounds).tolist(),
        "reference_bounds": [reference.min(axis=0).tolist(), reference.max(axis=0).tolist()],
        "limitations": [
            "Métrica aproximada por amostragem de vértices; não substitui distância ponto-superfície.",
            "Nenhum ICP foi aplicado: a comparação preserva as coordenadas COLMAP originais.",
        ],
    }
    args.output.write_text(json.dumps(result, ensure_ascii=False, indent=2), encoding="utf-8")
    print(json.dumps(result, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
