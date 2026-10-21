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
        confidence: float | np.ndarray,
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
        if np.isscalar(confidence):
            confidence_map = np.full(depth.shape, float(confidence), dtype=np.float64)
        else:
            confidence_map = np.asarray(confidence, dtype=np.float64)
            if confidence_map.shape != depth.shape:
                raise ValueError("O mapa de confiança precisa possuir a mesma resolução de depth.")
        confidence_map = np.clip(np.nan_to_num(confidence_map, nan=0.0), 0.0, 1.0)
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
        candidate_voxels = np.rint(world / self.config.voxel_size).astype(np.int64)
        block_keys = np.unique(
            np.floor_divide(candidate_voxels, self.config.block_resolution), axis=0,
        )
        local_grid = np.stack(np.meshgrid(
            np.arange(self.config.block_resolution),
            np.arange(self.config.block_resolution),
            np.arange(self.config.block_resolution),
            indexing="ij",
        ), axis=-1).reshape((-1, 3))
        updated = 0
        for block_key in block_keys:
            if cancel and cancel():
                raise InterruptedError("Reconstrução TSDF cancelada.")
            key = tuple(int(value) for value in block_key)
            block = self._allocate(key)
            if block is None:
                continue
            tsdf, weights, block_colors = block
            voxel_coordinates = block_key[None, :] * self.config.block_resolution + local_grid
            world_centres = voxel_coordinates.astype(np.float64) * self.config.voxel_size
            camera_centres = (rotation @ world_centres.T).T + translation
            camera_z = camera_centres[:, 2]
            projected_x = np.rint(focal_x * camera_centres[:, 0] / np.maximum(camera_z, 1e-9) + cx).astype(np.int64)
            projected_y = np.rint(focal_y * camera_centres[:, 1] / np.maximum(camera_z, 1e-9) + cy).astype(np.int64)
            projectable = (
                (camera_z > 0.03)
                & (projected_x >= 0) & (projected_x < depth.shape[1])
                & (projected_y >= 0) & (projected_y < depth.shape[0])
            )
            source_depth = np.full(len(local_grid), np.nan, dtype=np.float64)
            source_confidence = np.zeros(len(local_grid), dtype=np.float64)
            source_color = np.zeros((len(local_grid), 3), dtype=np.float64)
            source_depth[projectable] = depth[projected_y[projectable], projected_x[projectable]]
            source_confidence[projectable] = confidence_map[projected_y[projectable], projected_x[projectable]]
            source_color[projectable] = color[projected_y[projectable], projected_x[projectable]]
            signed_distance = (source_depth - camera_z) / self.config.truncation
            supported = (
                projectable & np.isfinite(source_depth)
                & (source_depth > 0.05)
                & (np.abs(signed_distance) <= 1.0)
                & (source_confidence > 0.0)
            )
            if not np.any(supported):
                continue
            xyz = local_grid[supported]
            index = tuple(xyz[:, axis] for axis in range(3))
            previous = weights[index]
            incoming = source_confidence[supported]
            total = np.minimum(previous + incoming, self.config.max_weight)
            effective = total - previous
            denominator = np.maximum(previous + effective, 1e-9)
            tsdf[index] = (
                tsdf[index] * previous + signed_distance[supported] * effective
            ) / denominator
            block_colors[index] = (
                block_colors[index] * previous[:, None] + source_color[supported] * effective[:, None]
            ) / denominator[:, None]
            weights[index] = total
            updated += int(np.sum(supported))
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
            denominator = values[a] - values[b]
            amount = 0.5 if abs(denominator) < 1e-9 else float(np.clip(values[a] / denominator, 0, 1))
            if amount <= 1e-7:
                key = (ca, ca)
            elif amount >= 1.0 - 1e-7:
                key = (cb, cb)
            else:
                key = (ca, cb) if ca <= cb else (cb, ca)
            existing = edge_vertices.get(key)
            if existing is not None:
                return existing
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
                            tetrahedron_indices = list(tetrahedron)
                            local_coordinates = coordinates[tetrahedron_indices].astype(np.float64)
                            local_values = values[list(tetrahedron)]
                            crossings: list[int] = []
                            for left, right in tetra_edges:
                                a, b = tetrahedron[left], tetrahedron[right]
                                if (values[a] <= 0) != (values[b] <= 0):
                                    crossings.append(edge_vertex(a, b, coordinates, values, shades))
                            crossings = list(dict.fromkeys(crossings))
                            if len(crossings) < 3:
                                continue

                            # Edge iteration order is not polygon order. Sorting the
                            # intersections in their plane prevents crossed quads and
                            # overlapping faces at tetrahedron and block boundaries.
                            polygon = np.asarray([vertices[index] for index in crossings], dtype=np.float64)
                            centroid = polygon.mean(axis=0)
                            design = np.column_stack((local_coordinates, np.ones(4)))
                            gradient = np.linalg.lstsq(design, local_values, rcond=None)[0][:3]
                            gradient_length = float(np.linalg.norm(gradient))
                            if gradient_length < 1e-9:
                                continue
                            normal = gradient / gradient_length
                            first_axis = polygon[0] - centroid
                            first_axis -= normal * float(np.dot(first_axis, normal))
                            first_length = float(np.linalg.norm(first_axis))
                            if first_length < 1e-9:
                                continue
                            first_axis /= first_length
                            second_axis = np.cross(normal, first_axis)
                            angles = np.arctan2(
                                (polygon - centroid) @ second_axis,
                                (polygon - centroid) @ first_axis,
                            )
                            ordered = [crossings[index] for index in np.argsort(angles)]
                            for triangle_index in range(1, len(ordered) - 1):
                                triangle = [ordered[0], ordered[triangle_index], ordered[triangle_index + 1]]
                                triangle_points = np.asarray([vertices[index] for index in triangle])
                                face_normal = np.cross(
                                    triangle_points[1] - triangle_points[0],
                                    triangle_points[2] - triangle_points[0],
                                )
                                if float(np.dot(face_normal, normal)) < 0:
                                    triangle[1], triangle[2] = triangle[2], triangle[1]
                                faces.append(triangle)
            if block_index % 128 == 0 and cancel and cancel():
                raise InterruptedError("Reconstrução TSDF cancelada durante a extração.")

        if not faces:
            raise RuntimeError("O volume TSDF não contém uma isossuperfície com suporte multivista suficiente.")
        face_array = np.asarray(faces, dtype=np.int64)
        nondegenerate = (
            (face_array[:, 0] != face_array[:, 1])
            & (face_array[:, 1] != face_array[:, 2])
            & (face_array[:, 0] != face_array[:, 2])
        )
        face_array = face_array[nondegenerate]
        canonical = np.sort(face_array, axis=1)
        _, unique_indices = np.unique(canonical, axis=0, return_index=True)
        face_array = face_array[np.sort(unique_indices)]
        face_array = self._orient_faces(face_array)
        report = self.report()
        report.update({
            "surface_cells": processed_cells,
            "vertices": len(vertices),
            "faces": len(face_array),
            "removed_duplicate_faces": int(len(faces) - len(face_array)),
        })
        return (
            np.asarray(vertices, dtype=np.float32),
            face_array,
            np.asarray(vertex_colors, dtype=np.uint8),
            report,
        )

    @staticmethod
    def _orient_faces(faces: np.ndarray) -> np.ndarray:
        """Make shared manifold edges use opposite directions.

        Local TSDF gradients orient individual tetrahedra, but small numerical
        differences around corners can still flip a neighboring polygon.  This
        graph pass changes winding only; it never joins components or creates
        geometry.
        """
        edge_uses: dict[tuple[int, int], list[tuple[int, bool]]] = {}
        for face_index, face in enumerate(faces):
            for start, end in ((face[0], face[1]), (face[1], face[2]), (face[2], face[0])):
                key = (int(min(start, end)), int(max(start, end)))
                edge_uses.setdefault(key, []).append((face_index, bool(start < end)))
        adjacency: list[list[tuple[int, bool]]] = [[] for _ in range(len(faces))]
        for uses in edge_uses.values():
            if len(uses) != 2:
                continue
            (left, left_direction), (right, right_direction) = uses
            different_flip = left_direction == right_direction
            adjacency[left].append((right, different_flip))
            adjacency[right].append((left, different_flip))
        flips = np.zeros(len(faces), dtype=bool)
        visited = np.zeros(len(faces), dtype=bool)
        for root in range(len(faces)):
            if visited[root]:
                continue
            visited[root] = True
            stack = [root]
            while stack:
                current = stack.pop()
                for neighbour, different_flip in adjacency[current]:
                    expected = bool(flips[current] ^ different_flip)
                    if not visited[neighbour]:
                        visited[neighbour] = True
                        flips[neighbour] = expected
                        stack.append(neighbour)
        oriented = faces.copy()
        oriented[flips, 1], oriented[flips, 2] = (
            oriented[flips, 2].copy(), oriented[flips, 1].copy(),
        )
        return oriented

    def report(self) -> dict[str, Any]:
        r = self.config.block_resolution
        bytes_per_block = r ** 3 * (4 + 4 + 3 * 4)
        observed = sum(int(np.count_nonzero(block[1])) for block in self.blocks.values())
        return {
            "method": "bounded_sparse_projective_tsdf",
            "world_to_camera": "x_camera = R * x_world + t",
            "camera_to_world": "x_world = R.T * (x_camera - t)",
            "depth_convention": "camera_z_not_radial_distance",
            "signed_distance": "clip((observed_z - voxel_camera_z) / truncation, -1, 1)",
            "voxel_coordinate": "integer_coordinates_are_voxel_centres_rounded_from_world",
            "block_boundary_support": "global_voxel_lookup_and_global_edge_vertex_deduplication",
            "isosurface": "zero_level_marching_tetrahedra_with_ordered_polygons",
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
