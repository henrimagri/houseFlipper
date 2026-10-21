from __future__ import annotations

import math
import time
from dataclasses import dataclass
from typing import Any, Callable

import numpy as np


CancelCheck = Callable[[], bool]


@dataclass(frozen=True)
class TsdfConfig:
    voxel_size: float = 0.035
    truncation: float = 0.105
    block_resolution: int = 8
    max_blocks: int = 12_000
    max_weight: float = 32.0
    pixel_stride: int = 8
    minimum_mesh_weight: float = 1.25

    def validated(self) -> "TsdfConfig":
        if not 0.005 <= self.voxel_size <= 0.12:
            raise ValueError("O voxel TSDF deve ficar entre 5 mm e 12 cm.")
        if not self.voxel_size * 1.5 <= self.truncation <= self.voxel_size * 8:
            raise ValueError("O truncamento TSDF deve ficar entre 1,5 e 8 voxels.")
        if self.block_resolution not in {4, 8, 16}:
            raise ValueError("A resolução do bloco TSDF deve ser 4, 8 ou 16.")
        if not 64 <= self.max_blocks <= 100_000:
            raise ValueError("O limite de blocos TSDF deve ficar entre 64 e 100.000.")
        return self


class SparseTsdfVolume:
    """CPU TSDF stored in bounded voxel blocks.

    Every update comes from a calibrated depth ray. Voxels are allocated only in
    the observed truncation band, so unknown space is never turned into geometry.
    Camera extrinsics follow COLMAP's world-to-camera convention.
    """

    def __init__(self, config: TsdfConfig):
        self.config = config.validated()
        self.blocks: dict[tuple[int, int, int], tuple[np.ndarray, np.ndarray, np.ndarray]] = {}
        self.integrated_views = 0
        self.integrated_samples = 0
        self.dropped_block_updates = 0
        self.started = time.perf_counter()

    def _allocate(self, key: tuple[int, int, int]) -> tuple[np.ndarray, np.ndarray, np.ndarray] | None:
        block = self.blocks.get(key)
        if block is not None:
            return block
        if len(self.blocks) >= self.config.max_blocks:
            self.dropped_block_updates += 1
            return None
        shape = (self.config.block_resolution,) * 3
        block = (
            np.ones(shape, dtype=np.float32),
            np.zeros(shape, dtype=np.float32),
            np.zeros((*shape, 3), dtype=np.float32),
        )
        self.blocks[key] = block
        return block

    def integrate(
        self,
        *,
        depth: np.ndarray,
        color: np.ndarray,
        focal_x: float,
        focal_y: float,
        cx: float,
        cy: float,
        rotation: np.ndarray,
        translation: np.ndarray,
        confidence: float,
        cancel: CancelCheck | None = None,
    ) -> dict[str, int]:
        if depth.ndim != 2 or color.shape[:2] != depth.shape:
            raise ValueError("Depth e RGB precisam possuir a mesma resolução.")
        if cancel and cancel():
            raise InterruptedError("Reconstrução TSDF cancelada.")

        stride = self.config.pixel_stride
        ys = np.arange(stride, depth.shape[0] - stride, stride, dtype=np.int64)
        xs = np.arange(stride, depth.shape[1] - stride, stride, dtype=np.int64)
        px, py = np.meshgrid(xs, ys)
        observed = depth[py, px]
        right = depth[py, np.minimum(px + stride, depth.shape[1] - 1)]
        down = depth[np.minimum(py + stride, depth.shape[0] - 1), px]
        valid = np.isfinite(observed) & (observed > 0.05) & np.isfinite(right) & np.isfinite(down)
        valid &= np.abs(right - observed) / np.maximum(observed, 1e-6) < 0.18
        valid &= np.abs(down - observed) / np.maximum(observed, 1e-6) < 0.18
        if not np.any(valid):
            return {"pixels": 0, "voxel_updates": 0}

        px = px[valid].astype(np.float64)
        py = py[valid].astype(np.float64)
        observed = observed[valid].astype(np.float64)
        rgb = color[py.astype(np.int64), px.astype(np.int64)].astype(np.float64)
        offsets = np.arange(
            -self.config.truncation,
            self.config.truncation + self.config.voxel_size * 0.5,
            self.config.voxel_size,
            dtype=np.float64,
        )
        sample_depth = observed[:, None] + offsets[None, :]
        positive = sample_depth > 0.03
        camera_x = (px[:, None] - cx) / focal_x * sample_depth
        camera_y = (py[:, None] - cy) / focal_y * sample_depth
        camera_points = np.stack((camera_x, camera_y, sample_depth), axis=-1)[positive]
        world = (rotation.T @ (camera_points - translation).T).T
        voxel = np.floor(world / self.config.voxel_size).astype(np.int64)
        sdf = np.broadcast_to((-offsets / self.config.truncation)[None, :], positive.shape)[positive]
        colors = np.broadcast_to(rgb[:, None, :], (*positive.shape, 3))[positive]

        # One image must not gain extra weight by writing the same voxel through
        # neighbouring pixels. Aggregate the per-view observation first.
        unique, inverse = np.unique(voxel, axis=0, return_inverse=True)
        counts = np.bincount(inverse).astype(np.float64)
        sdf = np.bincount(inverse, weights=sdf) / counts
        colors = np.column_stack([
            np.bincount(inverse, weights=colors[:, channel]) / counts for channel in range(3)
        ])
        block_keys = np.floor_divide(unique, self.config.block_resolution)
        local = np.mod(unique, self.config.block_resolution)
        _, groups = np.unique(block_keys, axis=0, return_inverse=True)
        weight = float(np.clip(confidence, 0.08, 1.0))
        updated = 0
        for group in range(int(groups.max()) + 1):
            if cancel and cancel():
                raise InterruptedError("Reconstrução TSDF cancelada.")
            members = np.flatnonzero(groups == group)
            key = tuple(int(value) for value in block_keys[members[0]])
            block = self._allocate(key)
            if block is None:
                continue
            tsdf, weights, block_colors = block
            xyz = local[members]
            index = tuple(xyz[:, axis] for axis in range(3))
            previous = weights[index]
            total = np.minimum(previous + weight, self.config.max_weight)
            effective = total - previous
            denominator = np.maximum(previous + effective, 1e-9)
            tsdf[index] = (tsdf[index] * previous + sdf[members] * effective) / denominator
            block_colors[index] = (
                block_colors[index] * previous[:, None] + colors[members] * effective[:, None]
            ) / denominator[:, None]
            weights[index] = total
            updated += len(members)
        self.integrated_views += 1
        self.integrated_samples += int(np.sum(valid))
        return {"pixels": int(np.sum(valid)), "voxel_updates": updated}

    def _voxel(self, coordinate: tuple[int, int, int]) -> tuple[float, float, np.ndarray] | None:
        array = np.asarray(coordinate, dtype=np.int64)
        block_key = tuple(int(value) for value in np.floor_divide(array, self.config.block_resolution))
        block = self.blocks.get(block_key)
        if block is None:
            return None
        local = np.mod(array, self.config.block_resolution)
        index = tuple(int(value) for value in local)
        if block[1][index] < self.config.minimum_mesh_weight:
            return None
        return float(block[0][index]), float(block[1][index]), block[2][index]

    def extract_mesh(self, *, cancel: CancelCheck | None = None) -> tuple[np.ndarray, np.ndarray, np.ndarray, dict[str, Any]]:
        corners = np.asarray([
            [0, 0, 0], [1, 0, 0], [1, 1, 0], [0, 1, 0],
            [0, 0, 1], [1, 0, 1], [1, 1, 1], [0, 1, 1],
        ], dtype=np.int64)
        tetrahedra = ((0, 5, 1, 6), (0, 1, 2, 6), (0, 2, 3, 6), (0, 3, 7, 6), (0, 7, 4, 6), (0, 4, 5, 6))
        tetra_edges = ((0, 1), (0, 2), (0, 3), (1, 2), (1, 3), (2, 3))
        vertices: list[np.ndarray] = []
        vertex_colors: list[np.ndarray] = []
        faces: list[list[int]] = []
        edge_vertices: dict[tuple[tuple[int, int, int], tuple[int, int, int]], int] = {}
        processed_cells = 0

        def edge_vertex(a: int, b: int, coordinates: np.ndarray, values: np.ndarray, colors: np.ndarray) -> int:
            ca = tuple(int(v) for v in coordinates[a])
            cb = tuple(int(v) for v in coordinates[b])
            key = (ca, cb) if ca <= cb else (cb, ca)
            existing = edge_vertices.get(key)
            if existing is not None:
                return existing
            denominator = values[a] - values[b]
            amount = 0.5 if abs(denominator) < 1e-9 else float(np.clip(values[a] / denominator, 0, 1))
            position = (coordinates[a] + amount * (coordinates[b] - coordinates[a])) * self.config.voxel_size
            shade = colors[a] + amount * (colors[b] - colors[a])
            index = len(vertices)
            vertices.append(position.astype(np.float32))
            vertex_colors.append(np.clip(shade, 0, 255).astype(np.uint8))
            edge_vertices[key] = index
            return index

        resolution = self.config.block_resolution
        for block_index, block_key in enumerate(sorted(self.blocks)):
            if cancel and cancel():
                raise InterruptedError("Reconstrução TSDF cancelada durante a extração.")
            origin = np.asarray(block_key, dtype=np.int64) * resolution
            for x in range(resolution):
                for y in range(resolution):
                    for z in range(resolution):
                        base = origin + (x, y, z)
                        coordinates = base[None, :] + corners
                        samples = [self._voxel(tuple(int(v) for v in coordinate)) for coordinate in coordinates]
                        if any(sample is None for sample in samples):
                            continue
                        values = np.asarray([sample[0] for sample in samples if sample is not None])
                        if values.min() > 0 or values.max() < 0:
                            continue
                        shades = np.asarray([sample[2] for sample in samples if sample is not None])
                        processed_cells += 1
                        for tetrahedron in tetrahedra:
                            local_values = values[list(tetrahedron)]
                            crossings = []
                            for left, right in tetra_edges:
                                a, b = tetrahedron[left], tetrahedron[right]
                                if (values[a] < 0) != (values[b] < 0):
                                    crossings.append(edge_vertex(a, b, coordinates, values, shades))
                            crossings = list(dict.fromkeys(crossings))
                            if len(crossings) == 3:
                                faces.append(crossings)
                            elif len(crossings) == 4:
                                faces.extend(([crossings[0], crossings[1], crossings[2]], [crossings[0], crossings[2], crossings[3]]))
            if block_index % 128 == 0 and cancel and cancel():
                raise InterruptedError("Reconstrução TSDF cancelada durante a extração.")

        if not faces:
            raise RuntimeError("O volume TSDF não contém uma isossuperfície com suporte multivista suficiente.")
        report = self.report()
        report.update({"surface_cells": processed_cells, "vertices": len(vertices), "faces": len(faces)})
        return (
            np.asarray(vertices, dtype=np.float32),
            np.asarray(faces, dtype=np.int64),
            np.asarray(vertex_colors, dtype=np.uint8),
            report,
        )

    def report(self) -> dict[str, Any]:
        r = self.config.block_resolution
        bytes_per_block = r ** 3 * (4 + 4 + 3 * 4)
        observed = sum(int(np.count_nonzero(block[1])) for block in self.blocks.values())
        return {
            "method": "bounded_sparse_projective_tsdf",
            "voxel_size_m": self.config.voxel_size,
            "truncation_m": self.config.truncation,
            "block_resolution": r,
            "allocated_blocks": len(self.blocks),
            "maximum_blocks": self.config.max_blocks,
            "observed_voxels": observed,
            "estimated_block_memory_bytes": len(self.blocks) * bytes_per_block,
            "integrated_views": self.integrated_views,
            "integrated_pixels": self.integrated_samples,
            "dropped_block_updates": self.dropped_block_updates,
            "unknown_space_filled": False,
            "elapsed_seconds": round(time.perf_counter() - self.started, 3),
        }
