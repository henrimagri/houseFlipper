from __future__ import annotations

import json
import hashlib
from io import BytesIO
import os
import platform
import shutil
import subprocess
import time
import uuid
from collections.abc import Mapping
from pathlib import Path
from typing import Any, Callable

from PIL import Image, ImageStat


GEOMETRY_THRESHOLDS = {
    "min_vertices": 50,
    "min_faces": 80,
    "min_sparse_points": 50,
    "min_faces_per_vertex": 1.0,
    "min_largest_component_face_ratio": 0.75,
    "max_connected_components": 8,
    "max_degenerate_face_ratio": 0.02,
}

REQUIRED_DENSE_COMMANDS = {
    "image_undistorter", "patch_match_stereo", "stereo_fusion",
    "poisson_mesher", "delaunay_mesher", "mesh_texturer",
}


def inspect_colmap_runtime(executable: Path, *, timeout: int = 15) -> dict[str, Any]:
    """Inspect the installed binary instead of trusting an image label."""
    try:
        completed = subprocess.run(
            [str(executable), "help"], capture_output=True, text=True, timeout=timeout, check=False,
        )
    except InterruptedError:
        raise
    except (OSError, subprocess.SubprocessError) as exception:
        return {"available": False, "error": type(exception).__name__, "commands": [], "gpu_support": False}
    output = f"{completed.stdout}\n{completed.stderr}"
    commands = sorted(command for command in REQUIRED_DENSE_COMMANDS if command in output)
    lowered = output.lower()
    return {
        "available": completed.returncode == 0,
        "version_line": next((line.strip() for line in output.splitlines() if line.strip().startswith("COLMAP ")), None),
        "commands": commands,
        "missing_commands": sorted(REQUIRED_DENSE_COMMANDS.difference(commands)),
        "gpu_support": "without gpu support" not in lowered and ("cuda" in lowered or "gpu support" in lowered),
    }


def _environment_flag(environment: Mapping[str, str], name: str, default: bool = False) -> bool:
    value = environment.get(name)
    if value is None:
        return default
    return value.strip().lower() in {"1", "true", "yes", "on"}


def dense_reconstruction_capability(
    *,
    environment: Mapping[str, str] | None = None,
    system_name: str | None = None,
    executable: Path | None = None,
    gpu_device_visible: bool | None = None,
    runtime_probe: Mapping[str, Any] | None = None,
) -> dict[str, Any]:
    environment = environment if environment is not None else os.environ
    requested = _environment_flag(environment, "COLMAP_DENSE_ENABLED")
    linux = (system_name or platform.system()).lower() == "linux"
    gpu_build = _environment_flag(environment, "COLMAP_GPU_BUILD")
    if gpu_device_visible is None:
        visible_devices = environment.get("NVIDIA_VISIBLE_DEVICES", "").strip().lower()
        gpu_device_visible = Path("/dev/nvidia0").exists() or visible_devices not in {"", "none", "void"}
    selected_mesher = environment.get("COLMAP_DENSE_MESHER", "poisson").strip().lower()
    valid_mesher = selected_mesher in {"poisson", "delaunay"}
    max_image_size = _bounded_integer(environment.get("COLMAP_DENSE_MAX_IMAGE_SIZE"), 2400, 512, 8192)
    poisson_depth = _bounded_integer(environment.get("COLMAP_POISSON_DEPTH"), 8, 6, 10)
    gpu_index = environment.get("COLMAP_GPU_INDEX", "0").strip() or "0"

    reasons = []
    if not requested:
        reasons.append("Reconstrução densa desativada; defina COLMAP_DENSE_ENABLED=true para solicitar o recurso.")
    if requested and not linux:
        reasons.append("Reconstrução densa habilitada somente no worker Linux GPU.")
    if requested and not gpu_build:
        reasons.append("COLMAP_GPU_BUILD=true não foi confirmado para o binário configurado.")
    if requested and not gpu_device_visible:
        reasons.append("Nenhum dispositivo NVIDIA foi disponibilizado ao processo do worker.")
    if requested and executable is None:
        reasons.append("O executável COLMAP não está disponível.")
    if requested and runtime_probe is not None and runtime_probe.get("missing_commands"):
        reasons.append("O COLMAP configurado não oferece todos os comandos densos exigidos.")
    if requested and runtime_probe is not None and not runtime_probe.get("gpu_support"):
        reasons.append("O próprio binário COLMAP informou que não possui suporte CUDA/GPU.")
    if requested and not valid_mesher:
        reasons.append("COLMAP_DENSE_MESHER deve ser poisson ou delaunay.")

    return {
        "requested": requested,
        "enabled": requested and linux and gpu_build and bool(gpu_device_visible) and executable is not None and valid_mesher
        and (runtime_probe is None or (bool(runtime_probe.get("gpu_support")) and not runtime_probe.get("missing_commands"))),
        "platform": "linux" if linux else (system_name or platform.system()).lower(),
        "gpu_build_confirmed": gpu_build,
        "gpu_device_visible": bool(gpu_device_visible),
        "mesher": selected_mesher,
        "max_image_size": max_image_size,
        "poisson_depth": poisson_depth,
        "gpu_index": gpu_index,
        "reasons": reasons,
        "runtime_probe": dict(runtime_probe) if runtime_probe is not None else None,
    }


def _bounded_integer(value: str | None, default: int, minimum: int, maximum: int) -> int:
    try:
        parsed = int(value) if value is not None else default
    except ValueError:
        parsed = default
    return max(minimum, min(parsed, maximum))


def colmap_executable() -> Path | None:
    configured = os.environ.get("COLMAP_EXECUTABLE")
    candidates = [
        Path(configured) if configured else None,
        Path(os.environ.get("LOCALAPPDATA", "")) / "COLMAP" / "4.2.1" / "bin" / "colmap.exe",
    ]
    located = shutil.which("colmap")
    if located:
        candidates.append(Path(located))
    return next((path for path in candidates if path and path.is_file()), None)


def preflight(paths: list[Path]) -> dict[str, Any]:
    if not 8 <= len(paths) <= 120:
        return {"accepted": False, "reason": "Envie entre 8 e 120 imagens do mesmo ambiente."}
    sizes: set[tuple[int, int]] = set()
    blurry = []
    dark = []
    for path in paths:
        with Image.open(path) as image:
            rgb = image.convert("RGB")
            sizes.add(rgb.size)
            stats = ImageStat.Stat(rgb.convert("L"))
            if stats.mean[0] < 22:
                dark.append(path.name)
            # Edge variance is a deterministic capture-quality gate, not neural inference.
            import cv2
            import numpy as np

            array = np.asarray(rgb)
            variance = float(cv2.Laplacian(cv2.cvtColor(array, cv2.COLOR_RGB2GRAY), cv2.CV_64F).var())
            if variance < 18:
                blurry.append(path.name)
    if len(sizes) > max(3, len(paths) // 3):
        return {"accepted": False, "reason": "O conjunto mistura resoluções demais; separe as capturas por ambiente."}
    if len(blurry) > len(paths) // 3:
        return {"accepted": False, "reason": "Muitas imagens estão borradas ou sem textura.", "blurry": blurry}
    if len(dark) > len(paths) // 3:
        return {"accepted": False, "reason": "Muitas imagens estão escuras.", "dark": dark}
    return {"accepted": True, "photo_count": len(paths), "blurry": blurry, "dark": dark, "resolution_groups": len(sizes)}


def _run(command: list[str], log: Path, timeout: int = 1800) -> float:
    started = time.perf_counter()
    completed = subprocess.run(command, capture_output=True, text=True, timeout=timeout, check=False)
    log.write_text(completed.stdout + "\n" + completed.stderr, encoding="utf-8", errors="replace")
    if completed.returncode != 0:
        raise RuntimeError(f"COLMAP falhou em {command[1]} (código {completed.returncode}).")
    return round((time.perf_counter() - started) * 1000, 2)


def unavailable_geometry_quality(reason: str, method: str = "sparse_delaunay_preview") -> dict[str, Any]:
    method_reason = (
        "A triangulação Delaunay esparsa é somente uma prévia e nunca é classificada como modelo final."
        if method == "sparse_delaunay_preview"
        else "A reconstrução densa exige revisão visual humana antes de qualquer classificação como modelo final."
    )
    return {
        "method": method,
        "status": "rejected",
        "viewer_eligible": False,
        "final_model_eligible": False,
        "reasons": [
            reason,
            method_reason,
        ],
        "metrics": None,
        "thresholds": GEOMETRY_THRESHOLDS.copy(),
    }


def evaluate_geometry(
    mesh: Any,
    *,
    method: str = "sparse_delaunay_preview",
    sparse_point_count: int | None = None,
    thresholds: Mapping[str, float | int] | None = None,
) -> dict[str, Any]:
    """Evaluate objective mesh properties without claiming visual fidelity."""
    import numpy as np

    active_thresholds = {**GEOMETRY_THRESHOLDS, **(thresholds or {})}
    vertices = np.asarray(mesh.vertices)
    faces = np.asarray(mesh.faces)
    vertex_count = int(len(vertices))
    face_count = int(len(faces))
    source_points = int(sparse_point_count if sparse_point_count is not None else vertex_count)
    finite_vertices = bool(vertices.ndim == 2 and vertices.shape[1:] == (3,) and np.isfinite(vertices).all())
    valid_faces = bool(
        faces.ndim == 2
        and faces.shape[1:] == (3,)
        and (face_count == 0 or (faces.min() >= 0 and faces.max() < vertex_count))
    )

    degenerate_count = face_count
    bounds_diagonal = 0.0
    used_vertex_count = 0
    component_count = 0
    largest_component_faces = 0
    significant_component_count = 0
    significant_component_minimum_faces = 0
    if finite_vertices and vertex_count:
        extents = np.ptp(vertices, axis=0)
        bounds_diagonal = float(np.linalg.norm(extents))
    if valid_faces and face_count:
        used_vertex_count = int(len(np.unique(faces)))
        repeated_index = (
            (faces[:, 0] == faces[:, 1])
            | (faces[:, 1] == faces[:, 2])
            | (faces[:, 0] == faces[:, 2])
        )
        if finite_vertices:
            triangles = vertices[faces]
            double_areas = np.linalg.norm(
                np.cross(triangles[:, 1] - triangles[:, 0], triangles[:, 2] - triangles[:, 0]),
                axis=1,
            )
            area_tolerance = max(bounds_diagonal * bounds_diagonal * 1e-12, 1e-15)
            degenerate_count = int(np.count_nonzero(repeated_index | (double_areas <= area_tolerance)))
        else:
            degenerate_count = int(np.count_nonzero(repeated_index))
        component_sizes = _face_component_sizes(faces)
        component_count = len(component_sizes)
        largest_component_faces = max(component_sizes, default=0)
        significant_component_minimum_faces = max(100, int(face_count * 0.002))
        significant_component_count = sum(
            size >= significant_component_minimum_faces for size in component_sizes
        )

    degenerate_ratio = float(degenerate_count / face_count) if face_count else 1.0
    faces_per_vertex = float(face_count / used_vertex_count) if used_vertex_count else 0.0
    largest_component_ratio = float(largest_component_faces / face_count) if face_count else 0.0
    watertight = bool(mesh.is_watertight) if valid_faces and face_count and finite_vertices else False
    winding_consistent = bool(mesh.is_winding_consistent) if valid_faces and face_count and finite_vertices else False

    metrics = {
        "vertices": vertex_count,
        "used_vertices": used_vertex_count,
        "faces": face_count,
        "source_points": source_points,
        "source_sparse_points": source_points,
        "faces_per_vertex": round(faces_per_vertex, 6),
        "connected_components": component_count,
        "connected_components_total": component_count,
        "connected_components_significant": significant_component_count,
        "significant_component_minimum_faces": significant_component_minimum_faces,
        "largest_component_faces": largest_component_faces,
        "largest_component_face_ratio": round(largest_component_ratio, 6),
        "degenerate_faces": degenerate_count,
        "degenerate_face_ratio": round(degenerate_ratio, 6),
        "bounds_diagonal": round(bounds_diagonal, 9),
        "finite_vertices": finite_vertices,
        "valid_triangle_indices": valid_faces,
        "watertight": watertight,
        "winding_consistent": winding_consistent,
    }
    rejection_reasons = []
    if not finite_vertices:
        rejection_reasons.append("A malha contém vértices inválidos ou não finitos.")
    if not valid_faces:
        rejection_reasons.append("A malha não contém índices triangulares válidos.")
    if vertex_count < active_thresholds["min_vertices"]:
        rejection_reasons.append("A malha possui poucos vértices para uma prévia geométrica útil.")
    if face_count < active_thresholds["min_faces"]:
        rejection_reasons.append("A malha possui poucas faces para uma prévia geométrica útil.")
    if source_points < active_thresholds["min_sparse_points"]:
        rejection_reasons.append("A nuvem esparsa possui poucos pontos reconstruídos.")
    if faces_per_vertex < active_thresholds["min_faces_per_vertex"]:
        rejection_reasons.append("A densidade topológica de faces por vértice é insuficiente.")
    if component_count > active_thresholds["max_connected_components"]:
        rejection_reasons.append("A malha está fragmentada em componentes demais.")
    if largest_component_ratio < active_thresholds["min_largest_component_face_ratio"]:
        rejection_reasons.append("O maior componente não concentra faces suficientes da malha.")
    if degenerate_ratio > active_thresholds["max_degenerate_face_ratio"]:
        rejection_reasons.append("A proporção de faces degeneradas excede o limite permitido.")
    if bounds_diagonal <= 1e-9:
        rejection_reasons.append("A malha não possui extensão espacial mensurável.")

    viewer_eligible = not rejection_reasons
    reasons = list(rejection_reasons)
    if not watertight:
        reasons.append("A malha não é watertight; eventuais aberturas permanecem visíveis na prévia.")
    if method == "sparse_delaunay_preview":
        reasons.append("A triangulação Delaunay esparsa é somente uma prévia e nunca é classificada como modelo final.")
    else:
        reasons.append("A qualidade geométrica automática não comprova fidelidade; revisão visual humana é obrigatória para uso final.")

    return {
        "method": method,
        "status": ("preview_only" if method == "sparse_delaunay_preview" else "viewer_ready") if viewer_eligible else "rejected",
        "viewer_eligible": viewer_eligible,
        "final_model_eligible": False,
        "reasons": reasons,
        "metrics": metrics,
        "thresholds": active_thresholds,
    }


def _face_component_sizes(faces: Any) -> list[int]:
    """Return edge-connected face component sizes with no optional graph dependency."""
    parent = list(range(len(faces)))
    sizes = [1] * len(faces)

    def find(index: int) -> int:
        while parent[index] != index:
            parent[index] = parent[parent[index]]
            index = parent[index]
        return index

    def union(left: int, right: int) -> None:
        left_root = find(left)
        right_root = find(right)
        if left_root == right_root:
            return
        if sizes[left_root] < sizes[right_root]:
            left_root, right_root = right_root, left_root
        parent[right_root] = left_root
        sizes[left_root] += sizes[right_root]

    edge_owner: dict[tuple[int, int], int] = {}
    for face_index, face in enumerate(faces):
        for start, end in ((face[0], face[1]), (face[1], face[2]), (face[2], face[0])):
            edge = tuple(sorted((int(start), int(end))))
            owner = edge_owner.setdefault(edge, face_index)
            union(face_index, owner)

    components: dict[int, int] = {}
    for face_index in range(len(faces)):
        root = find(face_index)
        components[root] = components.get(root, 0) + 1
    return sorted(components.values(), reverse=True)


def export_verified_texture(source: Path, destination: Path, *, expected_faces: int, texture_files: list[Path]) -> dict[str, Any]:
    """Verify UV/image bindings before and after GLB conversion, not visual fidelity."""
    import numpy as np
    import trimesh

    def image_fingerprint(image: Image.Image) -> tuple:
        image.load()
        return image.size, hashlib.sha256(image.convert("RGBA").tobytes()).hexdigest()

    # Some loaders silently substitute a generated default image for a broken
    # texture. Require the bound image to match a decoded worker atlas.
    authorized_images = set()
    for path in texture_files:
        with Image.open(path) as image:
            authorized_images.add(image_fingerprint(image))
    if not authorized_images:
        raise ValueError("Nenhuma imagem válida de atlas foi produzida.")

    def validate(scene) -> int:
        faces = 0
        if not scene.geometry:
            raise ValueError("O atlas não contém uma malha.")
        for mesh in scene.geometry.values():
            uv = np.asarray(getattr(mesh.visual, "uv", None))
            if (getattr(mesh.visual, "kind", None) != "texture"
                    or uv.shape != (len(mesh.vertices), 2) or not np.isfinite(uv).all()):
                raise ValueError("A malha não possui UVs finitas vinculadas a todos os vértices.")
            triangles = uv[mesh.faces]
            edge_a = triangles[:, 1] - triangles[:, 0]
            edge_b = triangles[:, 2] - triangles[:, 0]
            uv_area = np.abs(edge_a[:, 0] * edge_b[:, 1] - edge_a[:, 1] * edge_b[:, 0])
            if not len(uv_area) or np.any(uv_area <= 1e-12):
                raise ValueError("O atlas contém faces sem área UV válida.")
            material = mesh.visual.material
            image = getattr(material, "baseColorTexture", None)
            if image is None:
                image = getattr(material, "image", None)
            if not isinstance(image, Image.Image):
                raise ValueError("O material não contém imagem de textura vinculada.")
            image.load()  # Pillow is lazy: decoding must succeed, not just the header.
            if image.width < 1 or image.height < 1:
                raise ValueError("A imagem de textura está vazia.")
            if image_fingerprint(image) not in authorized_images:
                raise ValueError("A imagem vinculada ao material não corresponde ao atlas produzido.")
            faces += len(mesh.faces)
        if faces != expected_faces:
            raise ValueError("A conversão texturizada não preservou a quantidade de faces.")
        return faces

    scene = trimesh.load(source, force="scene", process=False)
    validate(scene)
    payload = scene.export(file_type="glb")
    restored = trimesh.load(BytesIO(payload), file_type="glb", force="scene", process=False)
    faces = validate(restored)
    destination.write_bytes(payload)
    return {"status": "verified_binding", "textured_faces": faces,
            "uv_and_embedded_images_validated": True, "photometric_fidelity_validated": False}


def run_dense_reconstruction(
    *,
    executable: Path,
    images: Path,
    sparse_model: Path,
    output: Path,
    capability: dict[str, Any],
    cancel: Callable[[], bool] | None = None,
    progress: Callable[[str, int], None] | None = None,
) -> dict[str, Any]:
    """Run COLMAP MVS only after an explicit, validated Linux GPU opt-in."""
    if not capability.get("enabled"):
        return {
            "status": "disabled",
            "capability": capability,
            "geometry_quality": unavailable_geometry_quality(
                "A reconstrução densa não foi executada porque o capability gate não foi aprovado.",
                method="dense_photogrammetry",
            ),
            "artifacts": {"point_cloud": None, "mesh": None, "glb": None},
            "timings": {},
        }

    import trimesh

    workspace = output / "dense"
    dense_cloud = output / "dense.ply"
    dense_mesh = output / "dense-mesh.ply"
    dense_glb = output / "dense-model.glb"
    workspace.mkdir(exist_ok=False)
    timings = {}
    def checkpoint(stage: str, percent: int) -> None:
        if cancel and cancel():
            raise InterruptedError("Reconstrução densa cancelada pelo usuário.")
        if progress:
            progress(stage, percent)

    checkpoint("dense_undistorting", 66)
    timings["image_undistortion_ms"] = _run(
        [
            str(executable),
            "image_undistorter",
            "--image_path",
            str(images),
            "--input_path",
            str(sparse_model),
            "--output_path",
            str(workspace),
            "--output_type",
            "COLMAP",
            "--max_image_size",
            str(capability["max_image_size"]),
        ],
        output / "image-undistorter.log",
    )
    checkpoint("dense_matching", 72)
    timings["patch_match_stereo_ms"] = _run(
        [
            str(executable),
            "patch_match_stereo",
            "--workspace_path",
            str(workspace),
            "--workspace_format",
            "COLMAP",
            "--PatchMatchStereo.geom_consistency",
            "true",
            "--PatchMatchStereo.gpu_index",
            str(capability["gpu_index"]),
        ],
        output / "patch-match-stereo.log",
    )
    checkpoint("dense_fusing", 80)
    timings["stereo_fusion_ms"] = _run(
        [
            str(executable),
            "stereo_fusion",
            "--workspace_path",
            str(workspace),
            "--workspace_format",
            "COLMAP",
            "--input_type",
            "geometric",
            "--output_path",
            str(dense_cloud),
        ],
        output / "stereo-fusion.log",
    )

    mesher = capability["mesher"]
    if mesher == "poisson":
        mesh_command = [
            str(executable),
            "poisson_mesher",
            "--PoissonMeshing.depth",
            str(_bounded_integer(capability.get("poisson_depth"), 8, 6, 10)),
            "--input_path",
            str(dense_cloud),
            "--output_path",
            str(dense_mesh),
        ]
        method = "dense_poisson_preview"
    else:
        mesh_command = [
            str(executable),
            "delaunay_mesher",
            "--input_path",
            str(workspace),
            "--input_type",
            "dense",
            "--output_path",
            str(dense_mesh),
        ]
        method = "dense_delaunay_preview"
    checkpoint("dense_meshing", 87)
    timings["dense_meshing_ms"] = _run(mesh_command, output / f"dense-{mesher}-mesher.log")

    if not dense_cloud.is_file() or dense_cloud.stat().st_size == 0:
        raise RuntimeError("COLMAP não gerou a nuvem de pontos densa.")
    if not dense_mesh.is_file() or dense_mesh.stat().st_size == 0:
        raise RuntimeError("COLMAP não gerou a malha densa.")

    textured_workspace = output / "dense-textured"
    point_cloud = trimesh.load(dense_cloud, process=False)
    dense_points = len(getattr(point_cloud, "vertices", []))
    loaded_mesh = trimesh.load(dense_mesh, force="mesh", process=False)
    geometry_quality = evaluate_geometry(loaded_mesh, method=method, sparse_point_count=dense_points)
    texture_validation = {"status": "not_attempted", "photometric_fidelity_validated": False}
    if geometry_quality["viewer_eligible"]:
        checkpoint("dense_texturing", 92)
        timings["mesh_texturing_ms"] = _run([
            str(executable), "mesh_texturer", "--workspace_path", str(workspace),
            "--input_path", str(dense_mesh), "--output_path", str(textured_workspace),
            "--output_type", "BIN", "--MeshTextureMapping.apply_color_correction", "true",
        ], output / "mesh-texturer.log")
    textured_candidates = [*textured_workspace.rglob("*.obj"), *textured_workspace.rglob("*.ply")]
    texture_files = [
        path for path in textured_workspace.rglob("*")
        if path.is_file() and path.suffix.lower() in {".png", ".jpg", ".jpeg", ".webp"}
    ] if textured_workspace.is_dir() else []
    textured_source = textured_candidates[0] if textured_candidates else None
    if geometry_quality["viewer_eligible"]:
        try:
            if textured_source is None or not texture_files:
                raise ValueError("COLMAP não produziu malha e imagem de atlas.")
            texture_validation = export_verified_texture(
                textured_source, dense_glb, expected_faces=len(loaded_mesh.faces), texture_files=texture_files,
            )
        except InterruptedError:
            raise
        except (ValueError, TypeError, OSError, IndexError) as exception:
            texture_validation = {"status": "rejected", "reason": str(exception), "photometric_fidelity_validated": False}
            geometry_quality["status"] = "rejected"
            geometry_quality["viewer_eligible"] = False
            geometry_quality["final_model_eligible"] = False
            geometry_quality["reasons"].append("O atlas não passou na validação de UVs, imagem vinculada e GLB: " + str(exception))

    return {
        "status": "completed" if geometry_quality["viewer_eligible"] and dense_glb.is_file() else "rejected",
        "capability": capability,
        "geometry_quality": geometry_quality,
        "texture_validation": texture_validation,
        "artifacts": {
            "point_cloud": dense_cloud.name,
            "mesh": dense_mesh.name,
            "glb": dense_glb.name if dense_glb.is_file() else None,
            "textured_workspace": textured_workspace.name if textured_workspace.is_dir() else None,
            "texture_files": [str(path.relative_to(textured_workspace)) for path in texture_files],
        },
        "timings": timings,
    }


def reconstruct(
    image_paths: list[Path],
    artifact_root: Path,
    *,
    job_id: str | None = None,
    method: str = "auto",
    cancel: Callable[[], bool] | None = None,
    progress: Callable[[str, int], None] | None = None,
) -> dict[str, Any]:
    def report(stage: str, percent: int) -> None:
        if cancel and cancel():
            raise InterruptedError("Reconstrução cancelada pelo usuário.")
        if progress is not None:
            progress(stage, percent)

    if method not in {"auto", "calibrated", "tsdf", "dense_colmap"}:
        raise ValueError("Método de reconstrução inválido.")

    report("validating_photos", 5)
    executable = colmap_executable()
    if executable is None:
        raise RuntimeError("COLMAP não está instalado no worker.")
    runtime_probe = inspect_colmap_runtime(executable)
    dense_capability = dense_reconstruction_capability(executable=executable, runtime_probe=runtime_probe)
    quality = preflight(image_paths)
    if not quality.get("accepted"):
        raise ValueError(str(quality.get("reason")))
    job_id = job_id or str(uuid.uuid4())
    output = artifact_root / job_id
    images = output / "images"
    sparse = output / "sparse"
    images.mkdir(parents=True, exist_ok=True)
    sparse.mkdir(exist_ok=True)
    for index, source in enumerate(image_paths):
        shutil.copy2(source, images / f"{index:04d}{source.suffix.lower()}")
    database = output / "database.db"
    timings = {}
    report("extracting_features", 12)
    timings["feature_extraction_ms"] = _run([
        str(executable), "feature_extractor", "--database_path", str(database), "--image_path", str(images),
        "--ImageReader.single_camera", "1", "--FeatureExtraction.use_gpu", "0",
    ], output / "feature-extractor.log")
    report("matching_images", 24)
    timings["matching_ms"] = _run([
        str(executable), "exhaustive_matcher", "--database_path", str(database), "--FeatureMatching.use_gpu", "0",
    ], output / "matcher.log")
    report("registering_cameras", 38)
    timings["mapping_ms"] = _run([
        str(executable), "mapper", "--database_path", str(database), "--image_path", str(images), "--output_path", str(sparse),
    ], output / "mapper.log")
    models = sorted(path for path in sparse.iterdir() if path.is_dir())
    if not models:
        raise RuntimeError("COLMAP não registrou câmeras suficientes para uma reconstrução esparsa.")
    model = models[0]
    sparse_ply = output / "sparse.ply"
    _run([str(executable), "model_converter", "--input_path", str(model), "--output_path", str(sparse_ply), "--output_type", "PLY"], output / "model-converter.log")
    info_log = output / "model-analyzer.log"
    _run([str(executable), "model_analyzer", "--path", str(model)], info_log)
    analysis = info_log.read_text(encoding="utf-8", errors="replace")
    mesh = output / "mesh.ply"
    glb = output / "model.glb"
    mesh_status = "not_generated"
    glb_status = "not_generated"
    geometry_quality = unavailable_geometry_quality("Nenhuma malha geométrica foi gerada.")
    report("generating_sparse_mesh", 52)
    try:
        _run([str(executable), "delaunay_mesher", "--input_path", str(model), "--input_type", "sparse", "--output_path", str(mesh)], output / "delaunay.log")
        if mesh.is_file() and mesh.stat().st_size > 0:
            mesh_status = "sparse_delaunay_preview"
            import trimesh

            loaded = trimesh.load(mesh, force="mesh", process=False)
            sparse_loaded = trimesh.load(sparse_ply, process=False)
            sparse_points = len(getattr(sparse_loaded, "vertices", []))
            geometry_quality = evaluate_geometry(loaded, sparse_point_count=sparse_points)
            if geometry_quality["viewer_eligible"]:
                loaded.export(glb, file_type="glb")
                glb_status = "sparse_delaunay_preview"
    except InterruptedError:
        raise
    except (ImportError, OSError, RuntimeError, ValueError) as exception:
        geometry_quality = unavailable_geometry_quality(f"Não foi possível validar a malha: {exception}")
        (output / "delaunay-error.txt").write_text(str(exception), encoding="utf-8")

    report("reconstructing_dense", 64)
    try:
        if method in {"auto", "dense_colmap"}:
            dense_reconstruction = run_dense_reconstruction(
                executable=executable,
                images=images,
                sparse_model=model,
                output=output,
                capability=dense_capability,
                cancel=cancel,
                progress=progress,
            )
        else:
            dense_reconstruction = {
                "status": "skipped",
                "capability": dense_capability,
                "geometry_quality": unavailable_geometry_quality("Método denso não solicitado.", method="dense_photogrammetry"),
                "artifacts": {"point_cloud": None, "mesh": None, "glb": None},
                "timings": {},
            }
    except InterruptedError:
        raise
    except (ImportError, OSError, RuntimeError, ValueError) as exception:
        (output / "dense-reconstruction-error.txt").write_text(str(exception), encoding="utf-8")
        dense_reconstruction = {
            "status": "failed",
            "capability": dense_capability,
            "geometry_quality": unavailable_geometry_quality(
                f"A reconstrução densa falhou: {exception}",
                method="dense_photogrammetry",
            ),
            "artifacts": {"point_cloud": None, "mesh": None, "glb": None},
            "timings": {},
        }
    dense_artifacts = dense_reconstruction["artifacts"]
    calibrated_fallback = None
    calibrated_enabled = os.environ.get(
        "AI_CALIBRATED_DEPTH_FALLBACK_ENABLED", "true" if os.name == "nt" else "false",
    ).strip().lower() in {"1", "true", "yes", "on"}
    if dense_reconstruction["status"] != "completed" and calibrated_enabled and method != "dense_colmap":
        report("calibrating_depth", 68)
        try:
            from .calibrated_depth_fusion import reconstruct_calibrated_depth

            text_model = output / "calibrated-sparse-txt"
            text_model.mkdir(exist_ok=False)
            _run([
                str(executable), "model_converter", "--input_path", str(model),
                "--output_path", str(text_model), "--output_type", "TXT",
            ], output / "calibrated-model-converter.log")
            calibrated_output = output / "calibrated-depth"

            def calibrated_progress(stage: str, percent: int, _details: dict[str, Any]) -> None:
                report(f"calibrated_{stage}", min(91, 68 + int(percent * 0.23)))

            calibrated_fallback = reconstruct_calibrated_depth(
                images=images,
                colmap_text_model=text_model,
                output=calibrated_output,
                colmap_executable=executable,
                maximum_views=min(42, len(image_paths)),
                stride=max(3, min(int(os.environ.get("AI_DEPTH_FUSION_STRIDE", "7")), 16)),
                voxel_size=max(0.005, min(float(os.environ.get("AI_DEPTH_FUSION_VOXEL", "0.01")), 0.05)),
                method="tsdf" if method == "tsdf" else "calibrated",
                cancel=cancel,
                progress=calibrated_progress,
            )
            fallback_artifacts = calibrated_fallback["artifacts"]
            if calibrated_fallback["status"] != "completed" or not fallback_artifacts.get("glb"):
                dense_reconstruction = {
                    "status": "rejected",
                    "capability": dense_capability,
                    "method": calibrated_fallback["method"],
                    "geometry_quality": calibrated_fallback["geometry_quality"],
                    "photo_texturing": calibrated_fallback.get("photo_texturing"),
                    "surface_stitching": calibrated_fallback.get("surface_stitching"),
                    "artifacts": {"point_cloud": None, "mesh": None, "glb": None},
                    "timings": {"calibrated_depth_seconds": calibrated_fallback["elapsed_seconds"]},
                    "limitations": calibrated_fallback["limitations"],
                }
                dense_artifacts = dense_reconstruction["artifacts"]
            else:
                standard_cloud = output / "dense.ply"
                standard_mesh = output / "dense-mesh.ply"
                standard_glb = output / "dense-model.glb"
                shutil.copy2(calibrated_output / fallback_artifacts["dense_point_cloud"], standard_cloud)
                shutil.copy2(calibrated_output / fallback_artifacts["clean_mesh"], standard_mesh)
                shutil.copy2(calibrated_output / fallback_artifacts["glb"], standard_glb)
                dense_reconstruction = {
                    "status": "completed",
                    "capability": dense_capability,
                    "method": calibrated_fallback["method"],
                    "geometry_quality": calibrated_fallback["geometry_quality"],
                    "photo_texturing": calibrated_fallback.get("photo_texturing"),
                    "surface_stitching": calibrated_fallback.get("surface_stitching"),
                    "artifacts": {
                        "point_cloud": standard_cloud.name,
                        "mesh": standard_mesh.name,
                        "glb": standard_glb.name,
                    },
                    "timings": {"calibrated_depth_seconds": calibrated_fallback["elapsed_seconds"]},
                    "limitations": calibrated_fallback["limitations"],
                }
                dense_artifacts = dense_reconstruction["artifacts"]
        except InterruptedError:
            raise
        except (ImportError, OSError, RuntimeError, ValueError, subprocess.SubprocessError) as exception:
            (output / "calibrated-depth-error.txt").write_text(str(exception), encoding="utf-8")
            calibrated_fallback = {"status": "failed", "error": f"{type(exception).__name__}: {exception}"}
    if dense_reconstruction["status"] == "completed" and dense_artifacts["glb"]:
        shutil.copy2(output / dense_artifacts["glb"], glb)
        geometry_quality = dense_reconstruction["geometry_quality"]
        glb_status = geometry_quality["method"]
    timings.update({f"dense_{key}": value for key, value in dense_reconstruction["timings"].items()})

    report("exporting", 92)
    result = {
        "job_id": job_id,
        "status": "completed",
        "kind": "photogrammetry",
        "requested_method": method,
        "actual_method": dense_reconstruction.get("method") or geometry_quality.get("method"),
        "colmap": {"version": "4.2.1", "gpu_build": dense_capability["gpu_build_confirmed"], "license": "BSD-3-Clause"},
        "quality_gate": quality,
        "geometry_quality": geometry_quality,
        "dense_reconstruction": dense_reconstruction,
        "calibrated_depth_fallback": calibrated_fallback,
        "timings": timings,
        "artifacts": {
            "sparse_point_cloud": sparse_ply.name,
            "mesh": mesh.name if mesh_status != "not_generated" else None,
            "mesh_status": mesh_status,
            "dense_point_cloud": dense_artifacts["point_cloud"],
            "dense_mesh": dense_artifacts["mesh"],
            "dense_status": dense_reconstruction["status"],
            "glb": glb.name if glb_status != "not_generated" else None,
            "glb_status": glb_status,
        },
        "integrity": {
            "sparse_ply_bytes": sparse_ply.stat().st_size,
            "mesh_ply_bytes": mesh.stat().st_size if mesh.is_file() else 0,
            "glb_bytes": glb.stat().st_size if glb.is_file() else 0,
            "dense_point_cloud_bytes": (output / dense_artifacts["point_cloud"]).stat().st_size if dense_artifacts["point_cloud"] else 0,
            "dense_mesh_bytes": (output / dense_artifacts["mesh"]).stat().st_size if dense_artifacts["mesh"] else 0,
            "registered_model": model.name,
            "analysis": analysis[-4000:],
        },
        "limitations": [
            "A reconstrução densa só é executada por opt-in em worker Linux com COLMAP CUDA e GPU NVIDIA explicitamente confirmados.",
            "A malha Delaunay esparsa é uma prévia geométrica e não deve ser apresentada como superfície final fiel.",
            "Mesmo uma malha densa aprovada pelo geometry_quality exige revisão visual humana antes de uso final.",
        ],
    }
    (output / "result.json").write_text(json.dumps(result, ensure_ascii=False, indent=2), encoding="utf-8")
    report("completed", 100)
    return result
