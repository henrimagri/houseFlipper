from __future__ import annotations

import hashlib
import json
import math
import os
import struct
import subprocess
import time
from dataclasses import dataclass
from pathlib import Path
from typing import Any, Callable

import numpy as np
from PIL import Image

from .reconstruction import evaluate_geometry
from .runtime_diagnostics import native_stage, record_event
from .vision import vision_pipeline
from .photo_texturing import TextureCamera, texture_mesh_from_photos
from .surface_stitching import stitch_nearby_surfaces


Progress = Callable[[str, int, dict[str, Any]], None]


@dataclass(frozen=True)
class Camera:
    width: int
    height: int
    focal: float
    cx: float
    cy: float


@dataclass
class View:
    image_id: int
    camera_id: int
    name: str
    rotation: np.ndarray
    translation: np.ndarray
    observations: list[tuple[float, float, int]]


def _quaternion_rotation(values: list[float]) -> np.ndarray:
    qw, qx, qy, qz = values
    return np.asarray([
        [1 - 2 * (qy * qy + qz * qz), 2 * (qx * qy - qz * qw), 2 * (qx * qz + qy * qw)],
        [2 * (qx * qy + qz * qw), 1 - 2 * (qx * qx + qz * qz), 2 * (qy * qz - qx * qw)],
        [2 * (qx * qz - qy * qw), 2 * (qy * qz + qx * qw), 1 - 2 * (qx * qx + qy * qy)],
    ], dtype=np.float64)


def read_colmap_text(model: Path) -> tuple[dict[int, Camera], list[View], dict[int, np.ndarray]]:
    cameras: dict[int, Camera] = {}
    for line in (model / "cameras.txt").read_text(encoding="utf-8").splitlines():
        if not line or line.startswith("#"):
            continue
        fields = line.split()
        if fields[1] != "SIMPLE_PINHOLE":
            raise ValueError(f"Modelo de câmera não suportado para fusão calibrada: {fields[1]}")
        cameras[int(fields[0])] = Camera(int(fields[2]), int(fields[3]), *map(float, fields[4:7]))

    points: dict[int, np.ndarray] = {}
    for line in (model / "points3D.txt").read_text(encoding="utf-8").splitlines():
        if not line or line.startswith("#"):
            continue
        fields = line.split()
        points[int(fields[0])] = np.asarray(list(map(float, fields[1:4])), dtype=np.float64)

    data = [line for line in (model / "images.txt").read_text(encoding="utf-8").splitlines() if not line.startswith("#")]
    views: list[View] = []
    for index in range(0, len(data), 2):
        header = data[index].split()
        observation_fields = data[index + 1].split() if index + 1 < len(data) else []
        observations = []
        for offset in range(0, len(observation_fields), 3):
            point_id = int(observation_fields[offset + 2])
            if point_id >= 0:
                observations.append((float(observation_fields[offset]), float(observation_fields[offset + 1]), point_id))
        views.append(View(
            image_id=int(header[0]),
            camera_id=int(header[8]),
            name=header[9],
            rotation=_quaternion_rotation(list(map(float, header[1:5]))),
            translation=np.asarray(list(map(float, header[5:8])), dtype=np.float64),
            observations=observations,
        ))
    return cameras, views, points


def _fit_metric_depth(
    relative: np.ndarray,
    view: View,
    camera: Camera,
    points: dict[int, np.ndarray],
) -> dict[str, Any] | None:
    height, width = relative.shape
    scale_x, scale_y = width / camera.width, height / camera.height
    samples: list[tuple[float, float]] = []
    for x, y, point_id in view.observations:
        world = points.get(point_id)
        if world is None:
            continue
        z = float((view.rotation @ world + view.translation)[2])
        px = min(width - 1, max(0, int(round(x * scale_x))))
        py = min(height - 1, max(0, int(round(y * scale_y))))
        value = float(relative[py, px])
        if z > 0.05 and math.isfinite(value):
            samples.append((value, z))
    if len(samples) < 24:
        return None
    sample = np.asarray(samples, dtype=np.float64)
    x = sample[:, 0]
    inverse_z = 1.0 / sample[:, 1]
    mask = np.ones(len(sample), dtype=bool)
    for _ in range(4):
        if int(mask.sum()) < 20:
            return None
        slope, intercept = np.polyfit(x[mask], inverse_z[mask], 1)
        residual = inverse_z - (slope * x + intercept)
        median = np.median(residual[mask])
        mad = np.median(np.abs(residual[mask] - median)) + 1e-9
        mask = np.abs(residual - median) <= 3.5 * 1.4826 * mad
    predicted_inverse = slope * x[mask] + intercept
    valid = predicted_inverse > 1e-6
    if int(valid.sum()) < 20 or slope <= 0:
        return None
    predicted_z = 1.0 / predicted_inverse[valid]
    observed_z = sample[mask, 1][valid]
    relative_error = np.abs(predicted_z - observed_z) / np.maximum(observed_z, 1e-6)
    median_error = float(np.median(relative_error))
    if median_error > 0.45:
        return None
    return {
        "slope": float(slope),
        "intercept": float(intercept),
        "samples": len(samples),
        "inliers": int(valid.sum()),
        "median_relative_error": median_error,
        "z_min": float(np.quantile(observed_z, 0.02)),
        "z_max": float(np.quantile(observed_z, 0.98)),
    }


def _view_cloud(
    image: Image.Image,
    relative: np.ndarray,
    view: View,
    camera: Camera,
    fit: dict[str, Any],
    stride: int,
) -> tuple[np.ndarray, np.ndarray, np.ndarray, np.ndarray]:
    rgb = np.asarray(image.convert("RGB"))
    height, width = relative.shape
    scale_x, scale_y = width / camera.width, height / camera.height
    focal_x, focal_y = camera.focal * scale_x, camera.focal * scale_y
    cx, cy = camera.cx * scale_x, camera.cy * scale_y
    inverse = fit["slope"] * relative + fit["intercept"]
    metric = np.where(inverse > 1e-6, 1.0 / inverse, np.nan)
    metric[(metric < max(0.05, fit["z_min"] * 0.55)) | (metric > fit["z_max"] * 1.7)] = np.nan

    ys = np.arange(stride, height - stride, stride)
    xs = np.arange(stride, width - stride, stride)
    grid_x, grid_y = np.meshgrid(xs, ys)
    z = metric[grid_y, grid_x]
    z_right = metric[grid_y, np.minimum(grid_x + stride, width - 1)]
    z_down = metric[np.minimum(grid_y + stride, height - 1), grid_x]
    valid = np.isfinite(z) & np.isfinite(z_right) & np.isfinite(z_down)
    valid &= np.abs(z_right - z) / np.maximum(z, 1e-6) < 0.18
    valid &= np.abs(z_down - z) / np.maximum(z, 1e-6) < 0.18

    def camera_points(px: np.ndarray, py: np.ndarray, depth: np.ndarray) -> np.ndarray:
        return np.stack(((px - cx) / focal_x * depth, (py - cy) / focal_y * depth, depth), axis=-1)

    base = camera_points(grid_x, grid_y, z)
    right = camera_points(grid_x + stride, grid_y, z_right)
    down = camera_points(grid_x, grid_y + stride, z_down)
    normals = np.cross(right - base, down - base)
    lengths = np.linalg.norm(normals, axis=-1)
    valid &= lengths > 1e-9
    normals = normals / np.maximum(lengths[..., None], 1e-9)
    flip = np.sum(normals * base, axis=-1) > 0
    normals[flip] *= -1

    camera_points_valid = base[valid]
    world = (view.rotation.T @ (camera_points_valid - view.translation).T).T
    world_normals = (view.rotation.T @ normals[valid].T).T
    colors = rgb[grid_y[valid], grid_x[valid]]
    vertex_index = np.full(valid.shape, -1, dtype=np.int64)
    vertex_index[valid] = np.arange(int(valid.sum()), dtype=np.int64)
    quads = valid[:-1, :-1] & valid[:-1, 1:] & valid[1:, :-1] & valid[1:, 1:]
    top_left = vertex_index[:-1, :-1][quads]
    top_right = vertex_index[:-1, 1:][quads]
    bottom_left = vertex_index[1:, :-1][quads]
    bottom_right = vertex_index[1:, 1:][quads]
    faces = np.concatenate((
        np.column_stack((top_left, bottom_left, top_right)),
        np.column_stack((top_right, bottom_left, bottom_right)),
    ), axis=0) if int(quads.sum()) else np.empty((0, 3), dtype=np.int64)
    return world.astype(np.float32), world_normals.astype(np.float32), colors.astype(np.uint8), faces


def _voxel_fuse(
    points: np.ndarray,
    normals: np.ndarray,
    colors: np.ndarray,
    voxel_size: float,
) -> tuple[np.ndarray, np.ndarray, np.ndarray]:
    fused_points, fused_normals, fused_colors, _ = _voxel_fuse_with_mapping(
        points, normals, colors, voxel_size,
    )
    return fused_points, fused_normals, fused_colors


def _voxel_fuse_with_mapping(
    points: np.ndarray,
    normals: np.ndarray,
    colors: np.ndarray,
    voxel_size: float,
) -> tuple[np.ndarray, np.ndarray, np.ndarray, np.ndarray]:
    keys = np.floor(points / voxel_size).astype(np.int64)
    _, first_indices, inverse = np.unique(keys, axis=0, return_index=True, return_inverse=True)
    counts = np.bincount(inverse).astype(np.float64)
    fused_points = np.column_stack([np.bincount(inverse, weights=points[:, i]) / counts for i in range(3)])
    reference_normals = normals[first_indices][inverse]
    signs = np.where(np.einsum("ij,ij->i", normals, reference_normals) < 0, -1.0, 1.0)
    aligned_normals = normals * signs[:, None]
    fused_normals = np.column_stack([np.bincount(inverse, weights=aligned_normals[:, i]) / counts for i in range(3)])
    fused_colors = np.column_stack([np.bincount(inverse, weights=colors[:, i]) / counts for i in range(3)])
    normal_lengths = np.linalg.norm(fused_normals, axis=1)
    keep = normal_lengths > 1e-6
    fused_normals[keep] /= normal_lengths[keep, None]
    compact_indices = np.full(len(keep), -1, dtype=np.int64)
    compact_indices[keep] = np.arange(int(keep.sum()), dtype=np.int64)
    vertex_mapping = compact_indices[inverse]
    return (
        fused_points[keep].astype(np.float32),
        fused_normals[keep].astype(np.float32),
        np.clip(fused_colors[keep], 0, 255).astype(np.uint8),
        vertex_mapping,
    )


def _fuse_surface_mesh(
    points: np.ndarray,
    normals: np.ndarray,
    colors: np.ndarray,
    faces: np.ndarray,
    voxel_size: float,
) -> tuple[np.ndarray, np.ndarray, np.ndarray, np.ndarray, dict[str, int]]:
    fused_points, fused_normals, fused_colors, vertex_mapping = _voxel_fuse_with_mapping(
        points, normals, colors, voxel_size,
    )
    remapped = vertex_mapping[faces]
    valid = np.all(remapped >= 0, axis=1)
    valid &= remapped[:, 0] != remapped[:, 1]
    valid &= remapped[:, 1] != remapped[:, 2]
    valid &= remapped[:, 0] != remapped[:, 2]
    remapped = remapped[valid]
    if len(remapped):
        canonical = np.sort(remapped, axis=1)
        _, unique_indices = np.unique(canonical, axis=0, return_index=True)
        remapped = remapped[np.sort(unique_indices)]
    metrics = {
        "raw_faces": int(len(faces)),
        "valid_faces": int(valid.sum()),
        "fused_faces": int(len(remapped)),
        "removed_degenerate_faces": int(len(faces) - valid.sum()),
        "removed_duplicate_faces": int(valid.sum() - len(remapped)),
    }
    return fused_points, fused_normals, fused_colors, remapped, metrics


def write_binary_ply(path: Path, points: np.ndarray, normals: np.ndarray, colors: np.ndarray) -> None:
    header = (
        "ply\nformat binary_little_endian 1.0\n"
        f"element vertex {len(points)}\n"
        "property float x\nproperty float y\nproperty float z\n"
        "property float nx\nproperty float ny\nproperty float nz\n"
        "property uchar red\nproperty uchar green\nproperty uchar blue\nend_header\n"
    ).encode("ascii")
    dtype = np.dtype([
        ("x", "<f4"), ("y", "<f4"), ("z", "<f4"),
        ("nx", "<f4"), ("ny", "<f4"), ("nz", "<f4"),
        ("red", "u1"), ("green", "u1"), ("blue", "u1"),
    ])
    data = np.empty(len(points), dtype=dtype)
    for index, name in enumerate(("x", "y", "z")):
        data[name] = points[:, index]
    for index, name in enumerate(("nx", "ny", "nz")):
        data[name] = normals[:, index]
    for index, name in enumerate(("red", "green", "blue")):
        data[name] = colors[:, index]
    with path.open("wb") as stream:
        stream.write(header)
        data.tofile(stream)


def reconstruct_calibrated_depth(
    *,
    images: Path,
    colmap_text_model: Path,
    output: Path,
    colmap_executable: Path,
    maximum_views: int = 42,
    stride: int = 7,
    voxel_size: float = 0.025,
    progress: Progress | None = None,
) -> dict[str, Any]:
    """Generate new geometry from photos/depth; never reads a reference mesh."""
    started = time.perf_counter()
    output.mkdir(parents=True, exist_ok=False)
    stages: list[dict[str, Any]] = []

    def report(stage: str, percent: int, **details: Any) -> None:
        entry = {"stage": stage, "percent": percent, "time": time.time(), **details}
        stages.append(entry)
        (output / "progress.json").write_text(json.dumps(entry, ensure_ascii=False, indent=2), encoding="utf-8")
        record_event("reconstruction_progress", **entry)
        if progress:
            progress(stage, percent, details)

    report("validating_calibration", 5)
    cameras, all_views, sparse_points = read_colmap_text(colmap_text_model)
    available = [view for view in all_views if (images / view.name).is_file()]
    if len(available) < 8:
        raise ValueError("A calibração possui menos de oito imagens disponíveis.")
    step = max(1, len(available) // maximum_views)
    selected = available[::step][:maximum_views]
    report("estimating_depth", 10, selected_views=len(selected), available_views=len(available))

    clouds: list[np.ndarray] = []
    normals: list[np.ndarray] = []
    colors: list[np.ndarray] = []
    surface_faces: list[np.ndarray] = []
    surface_offset = 0
    fit_reports: list[dict[str, Any]] = []
    accepted_views: list[View] = []
    for index, view in enumerate(selected):
        with Image.open(images / view.name) as opened:
            image = opened.convert("RGB")
        with native_stage("calibrated_depth_view", image=view.name):
            relative = vision_pipeline.predict_relative_depth(image)
            fit = _fit_metric_depth(relative, view, cameras[view.camera_id], sparse_points)
            if fit is None:
                fit_reports.append({"image": view.name, "accepted": False})
                continue
            points, view_normals, view_colors, view_faces = _view_cloud(image, relative, view, cameras[view.camera_id], fit, stride)
            if len(points) < 100:
                fit_reports.append({"image": view.name, "accepted": False, "reason": "insufficient_filtered_points", **fit})
                continue
            clouds.append(points)
            normals.append(view_normals)
            colors.append(view_colors)
            surface_faces.append(view_faces + surface_offset)
            surface_offset += len(points)
            accepted_views.append(view)
            fit_reports.append({"image": view.name, "accepted": True, "points": len(points), **fit})
        report("estimating_depth", 10 + int(45 * (index + 1) / len(selected)), processed=index + 1, accepted=len(clouds))

    if len(clouds) < 8:
        raise RuntimeError(f"Somente {len(clouds)} vistas produziram profundidade alinhada; mínimo técnico: 8.")
    report("fusing_depth_maps", 58, accepted_views=len(clouds))
    raw_points = np.concatenate(clouds)
    raw_normals = np.concatenate(normals)
    raw_colors = np.concatenate(colors)
    raw_faces = np.concatenate(surface_faces)
    fused_points, fused_normals, fused_colors, fused_faces, fusion_metrics = _fuse_surface_mesh(
        raw_points, raw_normals, raw_colors, raw_faces, voxel_size,
    )
    fusion_metrics.update({
        "raw_points": int(len(raw_points)),
        "fused_points": int(len(fused_points)),
        "voxel_size": float(voxel_size),
    })
    fusion_report_path = output / "fusion-report.json"
    fusion_report_path.write_text(
        json.dumps(fusion_metrics, ensure_ascii=False, indent=2), encoding="utf-8",
    )
    if len(fused_faces) < 100:
        raise RuntimeError(
            "A fusão multivista não preservou faces suficientes "
            f"({len(fused_faces)} de {len(raw_faces)}); revise a escala do voxel."
        )
    cloud_path = output / "depth-fused.ply"
    write_binary_ply(cloud_path, fused_points, fused_normals, fused_colors)

    report("generating_mesh", 70, raw_points=len(raw_points), fused_points=len(fused_points))
    raw_mesh = output / "depth-poisson-raw.ply"
    import trimesh

    meshing_method = "calibrated_monocular_depth_fusion_poisson"
    poisson_error = None
    poisson_enabled = os.environ.get(
        "AI_POISSON_ENABLED", "false" if os.name == "nt" else "true",
    ).strip().lower() in {"1", "true", "yes", "on"}
    if poisson_enabled:
        try:
            completed = subprocess.run(
                [
                    str(colmap_executable), "poisson_mesher",
                    "--input_path", str(cloud_path),
                    "--output_path", str(raw_mesh),
                    "--PoissonMeshing.depth", "8",
                    "--PoissonMeshing.num_threads", "4",
                ],
                capture_output=True,
                text=True,
                timeout=max(15, min(int(os.environ.get("AI_POISSON_TIMEOUT", "180")), 600)),
                check=False,
            )
            (output / "poisson-mesher.log").write_text(completed.stdout + "\n" + completed.stderr, encoding="utf-8", errors="replace")
            if completed.returncode != 0 or not raw_mesh.is_file() or raw_mesh.stat().st_size == 0:
                poisson_error = f"Poisson mesher falhou com código {completed.returncode}."
        except subprocess.TimeoutExpired as exception:
            poisson_error = f"Poisson mesher excedeu {exception.timeout} segundos."
    else:
        poisson_error = "Poisson mesher desativado neste runtime; usando superfície multivista calibrada."

    if poisson_error:
        meshing_method = "calibrated_multiview_depth_surface"
        (output / "poisson-mesher-error.txt").write_text(poisson_error, encoding="utf-8")
        fallback = trimesh.Trimesh(
            vertices=fused_points,
            faces=fused_faces,
            vertex_normals=fused_normals,
            vertex_colors=fused_colors,
            process=False,
        )
        fallback.export(raw_mesh)

    report("cleaning_mesh", 82)
    loaded = trimesh.load(raw_mesh, force="mesh", process=True)
    stitching_report = None
    stitching_enabled = poisson_error and os.environ.get("AI_SURFACE_STITCHING_ENABLED", "true").strip().lower() in {"1", "true", "yes", "on"}
    if stitching_enabled:
        stitch_voxel = max(0.005, min(float(os.environ.get("AI_SURFACE_STITCHING_VOXEL", "0.0125")), 0.03))
        loaded_colors = np.asarray(loaded.visual.vertex_colors)[:, :3]
        stitched = stitch_nearby_surfaces(
            np.asarray(loaded.vertices),
            np.asarray(loaded.faces),
            loaded_colors,
            voxel_size=stitch_voxel,
        )
        loaded = trimesh.Trimesh(
            vertices=stitched[0], faces=stitched[3], vertex_normals=stitched[1],
            vertex_colors=stitched[2], process=False,
        )
        stitching_report = stitched[4]
    components = loaded.split(only_watertight=False)
    if not components:
        raise RuntimeError("A malha Poisson não possui componentes válidos.")
    minimum_faces = max(100, int(len(loaded.faces) * 0.002))
    kept = [mesh for mesh in components if len(mesh.faces) >= minimum_faces]
    if poisson_error:
        kept = sorted(kept, key=lambda mesh: len(mesh.faces), reverse=True)[:64]
    clean = trimesh.util.concatenate(kept or [max(components, key=lambda mesh: len(mesh.faces))])
    clean.remove_unreferenced_vertices()
    clean.remove_infinite_values()
    if stitching_report is not None:
        stitching_report.update({
            "all_components_after_stitching": len(components),
            "significant_component_minimum_faces": minimum_faces,
            "retained_significant_components": len(kept),
            "retained_faces": int(len(clean.faces)),
        })
        (output / "stitching-report.json").write_text(
            json.dumps(stitching_report, ensure_ascii=False, indent=2), encoding="utf-8",
        )
    clean_mesh = output / "depth-poisson-clean.ply"
    clean.export(clean_mesh)

    quality_thresholds = None
    if poisson_error:
        quality_thresholds = {
            "max_connected_components": 64,
            "min_largest_component_face_ratio": 0.02,
        }
    quality = evaluate_geometry(
        clean,
        method=meshing_method,
        sparse_point_count=len(fused_points),
        thresholds=quality_thresholds,
    )
    report("exporting_glb", 92, viewer_eligible=quality["viewer_eligible"])
    glb = output / "depth-fusion.glb"
    texture_report = None
    texture_error = None
    if quality["viewer_eligible"]:
        texture_enabled = os.environ.get("AI_PHOTO_TEXTURE_ENABLED", "true").strip().lower() in {"1", "true", "yes", "on"}
        if texture_enabled:
            try:
                texture_cameras = [
                    TextureCamera(
                        name=view.name,
                        width=cameras[view.camera_id].width,
                        height=cameras[view.camera_id].height,
                        focal=cameras[view.camera_id].focal,
                        cx=cameras[view.camera_id].cx,
                        cy=cameras[view.camera_id].cy,
                        rotation=view.rotation,
                        translation=view.translation,
                    )
                    for view in accepted_views
                ]
                texture_report = texture_mesh_from_photos(
                    mesh=clean,
                    images=images,
                    cameras=texture_cameras,
                    output_glb=glb,
                    atlas_path=output / "texture-atlas.jpg",
                    report_path=output / "texture-report.json",
                    regions_path=output / "surface-regions.json",
                    atlas_size=max(1024, min(int(os.environ.get("AI_TEXTURE_ATLAS_SIZE", "4096")), 4096)),
                )
            except Exception as exception:
                texture_error = f"{type(exception).__name__}: {exception}"
                (output / "texture-error.txt").write_text(texture_error, encoding="utf-8")
        if not glb.is_file():
            clean.export(glb, file_type="glb")

    hashes = {}
    for artifact in (
        cloud_path, raw_mesh, clean_mesh, glb, fusion_report_path,
        output / "texture-atlas.jpg", output / "texture-report.json", output / "surface-regions.json",
        output / "stitching-report.json",
    ):
        if artifact.is_file():
            hashes[artifact.name] = hashlib.sha256(artifact.read_bytes()).hexdigest()
    result = {
        "status": "completed" if glb.is_file() else "rejected",
        "method": meshing_method,
        "independent_of_reference_mesh": True,
        "reference_mesh_read": False,
        "inputs": {"available_views": len(available), "selected_views": len(selected), "accepted_views": len(clouds)},
        "fit_reports": fit_reports,
        "fusion_metrics": fusion_metrics,
        "poisson_attempted": poisson_enabled,
        "photo_texturing": texture_report,
        "photo_texturing_error": texture_error,
        "surface_stitching": stitching_report,
        "geometry_quality": quality,
        "artifacts": {
            "dense_point_cloud": cloud_path.name,
            "raw_mesh": raw_mesh.name,
            "clean_mesh": clean_mesh.name,
            "glb": glb.name if glb.is_file() else None,
            "fusion_report": fusion_report_path.name,
            "texture_atlas": "texture-atlas.jpg" if (output / "texture-atlas.jpg").is_file() else None,
            "texture_report": "texture-report.json" if (output / "texture-report.json").is_file() else None,
            "surface_regions": "surface-regions.json" if (output / "surface-regions.json").is_file() else None,
            "stitching_report": "stitching-report.json" if (output / "stitching-report.json").is_file() else None,
        },
        "hashes": hashes,
        "stages": stages,
        "elapsed_seconds": round(time.perf_counter() - started, 3),
        "limitations": [
            "A profundidade monocular foi alinhada por vista aos pontos SfM; não equivale a estéreo denso COLMAP.",
            "Quando o Poisson excede o limite de execução, a malha preserva os componentes significativos das superfícies calibradas e fundidas por voxel.",
            "Faces sem projeção fotográfica válida usam um bloco neutro do atlas e são contabilizadas separadamente.",
            "Regiões arquitetônicas são candidatos geométricos corrigíveis, não classes semânticas confirmadas.",
            "A costura aproxima somente superfícies dentro do limite configurado e com a mesma família dominante de normal; ela não preenche regiões ocultas.",
            "A malha requer comparação independente com a referência antes de qualquer aprovação comercial.",
        ],
    }
    (output / "fit-report.json").write_text(json.dumps(fit_reports, ensure_ascii=False, indent=2), encoding="utf-8")
    (output / "result.json").write_text(json.dumps(result, ensure_ascii=False, indent=2), encoding="utf-8")
    report("completed", 100, status=result["status"])
    return result
