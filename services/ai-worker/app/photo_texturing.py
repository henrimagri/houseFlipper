from __future__ import annotations

import json
import math
import time
from dataclasses import dataclass
from pathlib import Path
from typing import Any, Iterable

import cv2
import numpy as np
from PIL import Image, ImageEnhance


@dataclass(frozen=True)
class TextureCamera:
    name: str
    width: int
    height: int
    focal: float
    cx: float
    cy: float
    rotation: np.ndarray
    translation: np.ndarray


def _project(vertices: np.ndarray, camera: TextureCamera) -> tuple[np.ndarray, np.ndarray]:
    local = (camera.rotation @ vertices.T).T + camera.translation
    z = local[:, 2]
    pixels = np.column_stack((
        camera.focal * local[:, 0] / np.maximum(z, 1e-9) + camera.cx,
        camera.focal * local[:, 1] / np.maximum(z, 1e-9) + camera.cy,
    ))
    return pixels, z


def _sharpness(path: Path) -> float:
    gray = cv2.imread(str(path), cv2.IMREAD_GRAYSCALE)
    if gray is None:
        return 0.0
    return float(cv2.Laplacian(gray, cv2.CV_64F).var())


def _scaled_to_image(camera: TextureCamera, path: Path) -> TextureCamera:
    with Image.open(path) as opened:
        width, height = opened.size
    scale_x, scale_y = width / camera.width, height / camera.height
    return TextureCamera(
        name=camera.name,
        width=width,
        height=height,
        focal=camera.focal * (scale_x + scale_y) / 2.0,
        cx=camera.cx * scale_x,
        cy=camera.cy * scale_y,
        rotation=camera.rotation,
        translation=camera.translation,
    )


def _face_camera_scores(mesh: Any, camera: TextureCamera, sharpness: float) -> np.ndarray:
    centers = np.asarray(mesh.triangles_center, dtype=np.float64)
    normals = np.asarray(mesh.face_normals, dtype=np.float64)
    pixels, depth = _project(centers, camera)
    camera_center = -(camera.rotation.T @ camera.translation)
    direction = camera_center[None, :] - centers
    distance = np.linalg.norm(direction, axis=1)
    direction /= np.maximum(distance[:, None], 1e-9)
    # Winding in a partially reconstructed mesh is not guaranteed. The absolute
    # angle keeps a visible surface eligible while the depth buffer still rejects
    # geometry hidden behind another surface.
    angle = np.abs(np.einsum("ij,ij->i", normals, direction))
    valid = (
        (depth > 0.03)
        & (pixels[:, 0] >= 1)
        & (pixels[:, 0] < camera.width - 1)
        & (pixels[:, 1] >= 1)
        & (pixels[:, 1] < camera.height - 1)
        & (angle >= 0.18)
    )

    # A bounded screen-space z-buffer is sufficient for camera selection and
    # prevents a rear surface from borrowing texture from the wall in front.
    grid_width = 96
    grid_height = max(32, int(round(grid_width * camera.height / camera.width)))
    cell_x = np.clip((pixels[:, 0] / camera.width * grid_width).astype(np.int64), 0, grid_width - 1)
    cell_y = np.clip((pixels[:, 1] / camera.height * grid_height).astype(np.int64), 0, grid_height - 1)
    cell = cell_y * grid_width + cell_x
    nearest = np.full(grid_width * grid_height, np.inf, dtype=np.float64)
    np.minimum.at(nearest, cell[valid], depth[valid])
    visible = depth <= nearest[cell] + np.maximum(0.035, depth * 0.025)
    valid &= visible
    score = angle * (1.0 + min(math.log1p(max(sharpness, 0.0)) / 12.0, 0.8))
    score /= 1.0 + distance * 0.025
    score[~valid] = -np.inf
    return score


def _exposure_adjusted(path: Path, target_luma: float, tile_size: int) -> Image.Image:
    with Image.open(path) as opened:
        image = opened.convert("RGB")
    sample = np.asarray(image.resize((64, 64)), dtype=np.float32)
    luma = float(np.median(sample @ np.asarray([0.2126, 0.7152, 0.0722], dtype=np.float32)))
    factor = float(np.clip(target_luma / max(luma, 1.0), 0.72, 1.38))
    image = ImageEnhance.Brightness(image).enhance(factor)
    return image.resize((tile_size, tile_size), Image.Resampling.LANCZOS)


def _runs(indices: np.ndarray) -> list[list[int]]:
    if len(indices) == 0:
        return []
    values = np.sort(indices.astype(np.int64))
    starts = np.r_[values[0], values[1:][values[1:] != values[:-1] + 1]]
    ends = np.r_[values[:-1][values[1:] != values[:-1] + 1], values[-1]]
    return [[int(start), int(end)] for start, end in zip(starts, ends)]


def classify_surface_regions(mesh: Any) -> dict[str, Any]:
    labels = _surface_labels(mesh)
    centers = np.asarray(mesh.triangles_center)
    areas = np.asarray(mesh.area_faces)

    regions: list[dict[str, Any]] = []
    for label in sorted(set(labels.tolist())):
        faces = np.flatnonzero(labels == label)
        confidence = {
            "wall_candidate": 0.62,
            "floor_candidate": 0.72,
            "ceiling_candidate": 0.64,
            "horizontal_surface": 0.45,
            "other_surface": 0.25,
        }[label]
        regions.append({
            "id": f"geometry-{label}",
            "kind": label,
            "origin": "geometric_orientation_rule",
            "confidence": confidence,
            "face_count": int(len(faces)),
            "area_m2": float(areas[faces].sum()),
            "face_index_runs": _runs(faces),
            "manual_correction_allowed": True,
        })
    return {
        "coordinate_system": "colmap_world_y_up",
        "regions": regions,
        "architectural_class_limitations": [
            "Parede, piso e teto são candidatos geométricos e exigem confirmação visual.",
            "Portas, janelas e móveis não recebem classe sem correspondência visual multivista validada.",
            "A correção manual deve substituir a classificação automática sem alterar faces fora da seleção.",
        ],
    }


def _surface_labels(mesh: Any) -> np.ndarray:
    centers = np.asarray(mesh.triangles_center)
    normals = np.asarray(mesh.face_normals)
    low, high = np.quantile(centers[:, 1], [0.16, 0.84])
    vertical = np.abs(normals[:, 1]) <= 0.28
    horizontal = np.abs(normals[:, 1]) >= 0.82
    labels = np.full(len(mesh.faces), "other_surface", dtype=object)
    labels[vertical] = "wall_candidate"
    labels[horizontal & (centers[:, 1] <= low)] = "floor_candidate"
    labels[horizontal & (centers[:, 1] >= high)] = "ceiling_candidate"
    labels[horizontal & (centers[:, 1] > low) & (centers[:, 1] < high)] = "horizontal_surface"
    return labels


def texture_mesh_from_photos(
    *,
    mesh: Any,
    images: Path,
    cameras: Iterable[TextureCamera],
    output_glb: Path,
    atlas_path: Path,
    report_path: Path,
    regions_path: Path,
    atlas_size: int = 4096,
    maximum_cameras: int = 35,
) -> dict[str, Any]:
    """Project authorized photos onto generated geometry and export a UV GLB.

    The function never reads reference geometry. Camera choice uses calibrated
    projection, a coarse depth buffer, view angle and image sharpness.
    """
    started = time.perf_counter()
    import trimesh
    from trimesh.visual.texture import TextureVisuals

    camera_list = [
        _scaled_to_image(camera, images / camera.name)
        for camera in cameras
        if (images / camera.name).is_file()
    ]
    if not camera_list:
        raise ValueError("Nenhuma fotografia calibrada disponível para texturização.")
    sharpness = {camera.name: _sharpness(images / camera.name) for camera in camera_list}
    all_scores = np.column_stack([
        _face_camera_scores(mesh, camera, sharpness[camera.name]) for camera in camera_list
    ])
    initial = np.argmax(all_scores, axis=1)
    initial_score = np.max(all_scores, axis=1)
    usable = np.isfinite(initial_score)
    counts = np.bincount(initial[usable], minlength=len(camera_list))
    selected_indices = np.argsort(counts)[::-1][:maximum_cameras]
    selected_indices = selected_indices[counts[selected_indices] > 0]
    if len(selected_indices) == 0:
        raise RuntimeError("Nenhuma face teve observação fotográfica válida.")
    selected_scores = all_scores[:, selected_indices]
    selected_local = np.argmax(selected_scores, axis=1)
    selected_score = np.max(selected_scores, axis=1)
    assigned = np.where(np.isfinite(selected_score), selected_local, -1)
    selected = [camera_list[int(index)] for index in selected_indices]

    columns = int(math.ceil(math.sqrt(len(selected) + 1)))
    rows = int(math.ceil((len(selected) + 1) / columns))
    tile_size = min(1024, atlas_size // max(columns, rows))
    atlas_width, atlas_height = columns * tile_size, rows * tile_size
    lumas = []
    for camera in selected:
        with Image.open(images / camera.name) as opened:
            sample = np.asarray(opened.convert("RGB").resize((64, 64)), dtype=np.float32)
        lumas.append(float(np.median(sample @ np.asarray([0.2126, 0.7152, 0.0722], dtype=np.float32))))
    target_luma = float(np.median(lumas))
    atlas = Image.new("RGB", (atlas_width, atlas_height), (96, 103, 113))
    for index, camera in enumerate(selected):
        atlas.paste(_exposure_adjusted(images / camera.name, target_luma, tile_size), ((index % columns) * tile_size, (index // columns) * tile_size))
    fallback_index = len(selected)
    fallback_tile = Image.new("RGB", (tile_size, tile_size), (104, 112, 122))
    atlas.paste(fallback_tile, ((fallback_index % columns) * tile_size, (fallback_index // columns) * tile_size))
    atlas.save(atlas_path, format="JPEG", quality=90, optimize=True)

    source_vertices = np.asarray(mesh.vertices)
    new_vertices: list[np.ndarray] = []
    new_uv: list[tuple[float, float]] = []
    new_faces: list[list[int]] = []
    vertex_cache: dict[tuple[int, int], int] = {}
    for face_index, face in enumerate(np.asarray(mesh.faces)):
        local_camera = int(assigned[face_index])
        tile_index = local_camera if local_camera >= 0 else fallback_index
        camera = selected[local_camera] if local_camera >= 0 else None
        projected = _project(source_vertices[face], camera)[0] if camera else np.zeros((3, 2))
        rebuilt_face = []
        for corner, source_index in enumerate(face):
            key = (int(source_index), tile_index)
            target_index = vertex_cache.get(key)
            if target_index is None:
                target_index = len(new_vertices)
                vertex_cache[key] = target_index
                new_vertices.append(source_vertices[source_index])
                if camera:
                    u = (tile_index % columns + projected[corner, 0] / camera.width) / columns
                    v = 1.0 - (tile_index // columns + projected[corner, 1] / camera.height) / rows
                else:
                    u = (tile_index % columns + 0.5) / columns
                    v = 1.0 - (tile_index // columns + 0.5) / rows
                new_uv.append((float(np.clip(u, 0, 1)), float(np.clip(v, 0, 1))))
            rebuilt_face.append(target_index)
        new_faces.append(rebuilt_face)

    rebuilt_vertices = np.asarray(new_vertices)
    rebuilt_faces = np.asarray(new_faces)
    rebuilt_uv = np.asarray(new_uv)
    semantic_labels = _surface_labels(mesh)
    label_names = sorted(set(semantic_labels.tolist()))
    from trimesh.visual.material import PBRMaterial
    scene = trimesh.Scene()
    for label in label_names:
        face_indices = np.flatnonzero(semantic_labels == label)
        label_faces = rebuilt_faces[face_indices]
        used, inverse = np.unique(label_faces.reshape(-1), return_inverse=True)
        region = trimesh.Trimesh(
            vertices=rebuilt_vertices[used],
            faces=inverse.reshape((-1, 3)),
            process=False,
        )
        region.visual = TextureVisuals(
            uv=rebuilt_uv[used],
            material=PBRMaterial(
                baseColorTexture=atlas,
                name=f"geometry-{label}",
                metallicFactor=0.0,
                roughnessFactor=0.9,
                doubleSided=True,
            ),
        )
        scene.add_geometry(region, node_name=f"geometry-{label}", geom_name=f"geometry-{label}")
    scene.export(output_glb, file_type="glb")
    regions = classify_surface_regions(mesh)
    regions_path.write_text(json.dumps(regions, ensure_ascii=False, indent=2), encoding="utf-8")
    coverage = float(np.mean(assigned >= 0))
    report = {
        "method": "calibrated_multiview_photo_atlas",
        "reference_mesh_read": False,
        "source_photos": [camera.name for camera in selected],
        "selected_camera_count": len(selected),
        "face_count": int(len(mesh.faces)),
        "textured_face_count": int(np.sum(assigned >= 0)),
        "untextured_face_count": int(np.sum(assigned < 0)),
        "textured_face_ratio": coverage,
        "atlas": {"path": atlas_path.name, "width": atlas_width, "height": atlas_height, "tile_size": tile_size},
        "selection_evidence": ["calibrated_projection", "screen_space_occlusion", "view_angle", "image_sharpness"],
        "exposure_correction": "median_luminance_bounded_0.72_to_1.38",
        "fallback": "neutral_atlas_tile_for_faces_without_valid_observation",
        "elapsed_seconds": round(time.perf_counter() - started, 3),
        "limitations": [
            "Oclusão é aproximada por z-buffer de baixa resolução; requer inspeção visual de costuras.",
            "A exposição é normalizada por imagem, sem otimização global de cores entre costuras.",
            "Faces sem observação válida não são declaradas fototexturizadas.",
        ],
    }
    report_path.write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding="utf-8")
    return report
