import numpy as np
import trimesh

from app import reconstruction
from app.reconstruction import evaluate_geometry, unavailable_geometry_quality


def test_connected_watertight_mesh_is_only_viewer_preview() -> None:
    mesh = trimesh.creation.icosphere(subdivisions=2)

    quality = evaluate_geometry(mesh, sparse_point_count=200)

    assert quality["method"] == "sparse_delaunay_preview"
    assert quality["status"] == "preview_only"
    assert quality["viewer_eligible"] is True
    assert quality["final_model_eligible"] is False
    assert quality["metrics"]["connected_components"] == 1
    assert quality["metrics"]["largest_component_face_ratio"] == 1.0
    assert quality["metrics"]["watertight"] is True
    assert any("nunca é classificada como modelo final" in reason for reason in quality["reasons"])


def test_sparse_triangle_is_rejected_for_viewer_and_final_use() -> None:
    mesh = trimesh.Trimesh(
        vertices=[[0, 0, 0], [1, 0, 0], [0, 1, 0]],
        faces=[[0, 1, 2]],
        process=False,
    )

    quality = evaluate_geometry(mesh, sparse_point_count=3)

    assert quality["status"] == "rejected"
    assert quality["viewer_eligible"] is False
    assert quality["final_model_eligible"] is False
    assert quality["metrics"]["vertices"] == 3
    assert quality["metrics"]["faces"] == 1
    assert any("poucos vértices" in reason for reason in quality["reasons"])
    assert any("poucas faces" in reason for reason in quality["reasons"])
    assert any("poucos pontos" in reason for reason in quality["reasons"])


def test_fragmented_mesh_is_rejected_by_connectivity_metrics() -> None:
    pieces = []
    for index in range(10):
        piece = trimesh.creation.box()
        piece.apply_translation([index * 3.0, 0, 0])
        pieces.append(piece)
    mesh = trimesh.util.concatenate(pieces)

    quality = evaluate_geometry(mesh, sparse_point_count=500)

    assert quality["status"] == "rejected"
    assert quality["viewer_eligible"] is False
    assert quality["metrics"]["connected_components"] == 10
    assert quality["metrics"]["largest_component_face_ratio"] == 0.1
    assert any("componentes demais" in reason for reason in quality["reasons"])
    assert any("maior componente" in reason for reason in quality["reasons"])


def test_connected_open_mesh_can_only_be_an_explicit_non_watertight_preview() -> None:
    size = 10
    vertices = np.array([[x, y, 0.0] for y in range(size) for x in range(size)], dtype=float)
    faces = []
    for y in range(size - 1):
        for x in range(size - 1):
            top_left = y * size + x
            faces.extend(
                [
                    [top_left, top_left + 1, top_left + size],
                    [top_left + 1, top_left + size + 1, top_left + size],
                ]
            )
    mesh = trimesh.Trimesh(vertices=vertices, faces=faces, process=False)

    quality = evaluate_geometry(mesh, sparse_point_count=100)

    assert quality["status"] == "preview_only"
    assert quality["viewer_eligible"] is True
    assert quality["final_model_eligible"] is False
    assert quality["metrics"]["watertight"] is False
    assert quality["metrics"]["connected_components"] == 1
    assert any("não é watertight" in reason for reason in quality["reasons"])


def test_degenerate_faces_over_threshold_reject_mesh() -> None:
    source = trimesh.creation.icosphere(subdivisions=2)
    faces = source.faces.copy()
    faces[:10] = np.repeat(faces[:10, :1], 3, axis=1)
    mesh = trimesh.Trimesh(vertices=source.vertices.copy(), faces=faces, process=False)

    quality = evaluate_geometry(mesh, sparse_point_count=200)

    assert quality["status"] == "rejected"
    assert quality["viewer_eligible"] is False
    assert quality["metrics"]["degenerate_faces"] == 10
    assert quality["metrics"]["degenerate_face_ratio"] > 0.02
    assert any("faces degeneradas" in reason for reason in quality["reasons"])


def test_non_finite_vertices_reject_mesh_without_geometry_claim() -> None:
    source = trimesh.creation.icosphere(subdivisions=2)
    vertices = source.vertices.copy()
    vertices[0, 0] = np.nan
    mesh = trimesh.Trimesh(vertices=vertices, faces=source.faces.copy(), process=False)

    quality = evaluate_geometry(mesh, sparse_point_count=200)

    assert quality["status"] == "rejected"
    assert quality["viewer_eligible"] is False
    assert quality["final_model_eligible"] is False
    assert quality["metrics"]["finite_vertices"] is False
    assert any("não finitos" in reason for reason in quality["reasons"])


def test_low_source_point_density_rejects_otherwise_valid_mesh() -> None:
    mesh = trimesh.creation.icosphere(subdivisions=2)

    quality = evaluate_geometry(mesh, sparse_point_count=12)

    assert quality["status"] == "rejected"
    assert quality["viewer_eligible"] is False
    assert quality["metrics"]["source_sparse_points"] == 12
    assert any("nuvem esparsa" in reason for reason in quality["reasons"])


def test_missing_mesh_has_explicit_rejected_quality_contract() -> None:
    quality = unavailable_geometry_quality("Nenhuma malha geométrica foi gerada.")

    assert quality["method"] == "sparse_delaunay_preview"
    assert quality["status"] == "rejected"
    assert quality["viewer_eligible"] is False
    assert quality["final_model_eligible"] is False
    assert quality["metrics"] is None
    assert quality["reasons"][0] == "Nenhuma malha geométrica foi gerada."


def test_reconstruction_does_not_export_rejected_sparse_mesh(monkeypatch, tmp_path) -> None:
    executable = tmp_path / "colmap.exe"
    executable.write_bytes(b"stub")
    sources = []
    for index in range(8):
        source = tmp_path / f"source-{index}.jpg"
        source.write_bytes(b"image")
        sources.append(source)

    monkeypatch.setattr(reconstruction, "colmap_executable", lambda: executable)
    monkeypatch.setattr(
        reconstruction,
        "preflight",
        lambda paths: {"accepted": True, "photo_count": len(paths), "blurry": [], "dark": [], "resolution_groups": 1},
    )

    def fake_run(command, log, timeout=1800):
        operation = command[1]
        log.write_text(f"simulated {operation}", encoding="utf-8")
        if operation == "mapper":
            (log.parent / "sparse" / "0").mkdir()
        elif operation == "model_converter":
            target = log.parent / "sparse.ply"
            trimesh.points.PointCloud(np.arange(36, dtype=float).reshape(12, 3)).export(target)
        elif operation == "delaunay_mesher":
            target = log.parent / "mesh.ply"
            trimesh.Trimesh(
                vertices=[[0, 0, 0], [1, 0, 0], [0, 1, 0]],
                faces=[[0, 1, 2]],
                process=False,
            ).export(target)
        return 1.0

    monkeypatch.setattr(reconstruction, "_run", fake_run)

    result = reconstruction.reconstruct(sources, tmp_path / "artifacts")
    output = tmp_path / "artifacts" / result["job_id"]

    assert result["status"] == "completed"
    assert result["geometry_quality"]["status"] == "rejected"
    assert result["geometry_quality"]["viewer_eligible"] is False
    assert result["geometry_quality"]["final_model_eligible"] is False
    assert result["artifacts"]["mesh_status"] == "sparse_delaunay_preview"
    assert result["artifacts"]["glb"] is None
    assert result["artifacts"]["glb_status"] == "not_generated"
    assert result["dense_reconstruction"]["status"] == "disabled"
    assert result["artifacts"]["dense_point_cloud"] is None
    assert not (output / "model.glb").exists()


def test_dense_capability_is_opt_in_and_never_enabled_on_windows(tmp_path) -> None:
    executable = tmp_path / "colmap"
    executable.write_bytes(b"stub")

    default = reconstruction.dense_reconstruction_capability(
        environment={},
        system_name="Linux",
        executable=executable,
        gpu_device_visible=True,
    )
    windows = reconstruction.dense_reconstruction_capability(
        environment={"COLMAP_DENSE_ENABLED": "true", "COLMAP_GPU_BUILD": "true"},
        system_name="Windows",
        executable=executable,
        gpu_device_visible=True,
    )

    assert default["requested"] is False
    assert default["enabled"] is False
    assert windows["requested"] is True
    assert windows["enabled"] is False
    assert any("somente no worker Linux GPU" in reason for reason in windows["reasons"])


def test_dense_capability_requires_gpu_build_and_visible_device(tmp_path) -> None:
    executable = tmp_path / "colmap"
    executable.write_bytes(b"stub")
    base = {"COLMAP_DENSE_ENABLED": "true", "COLMAP_DENSE_MAX_IMAGE_SIZE": "99999"}

    without_gpu_build = reconstruction.dense_reconstruction_capability(
        environment=base,
        system_name="Linux",
        executable=executable,
        gpu_device_visible=True,
    )
    without_device = reconstruction.dense_reconstruction_capability(
        environment={**base, "COLMAP_GPU_BUILD": "true"},
        system_name="Linux",
        executable=executable,
        gpu_device_visible=False,
    )
    enabled = reconstruction.dense_reconstruction_capability(
        environment={
            **base,
            "COLMAP_GPU_BUILD": "true",
            "COLMAP_DENSE_MESHER": "poisson",
            "COLMAP_GPU_INDEX": "1",
        },
        system_name="Linux",
        executable=executable,
        gpu_device_visible=True,
    )

    assert without_gpu_build["enabled"] is False
    assert without_device["enabled"] is False
    assert enabled["enabled"] is True
    assert enabled["max_image_size"] == 8192
    assert enabled["gpu_index"] == "1"


def test_dense_pipeline_runs_reproducible_colmap_sequence_and_gates_export(monkeypatch, tmp_path) -> None:
    executable = tmp_path / "colmap"
    executable.write_bytes(b"stub")
    images = tmp_path / "images"
    model = tmp_path / "sparse" / "0"
    output = tmp_path / "output"
    images.mkdir()
    model.mkdir(parents=True)
    output.mkdir()
    commands = []

    def fake_run(command, log, timeout=1800):
        commands.append(command)
        log.write_text(f"simulated {command[1]}", encoding="utf-8")
        if command[1] == "stereo_fusion":
            trimesh.points.PointCloud(trimesh.creation.icosphere(subdivisions=2).vertices).export(output / "dense.ply")
        elif command[1] == "poisson_mesher":
            trimesh.creation.icosphere(subdivisions=2).export(output / "dense-mesh.ply")
        return 1.0

    monkeypatch.setattr(reconstruction, "_run", fake_run)
    capability = reconstruction.dense_reconstruction_capability(
        environment={
            "COLMAP_DENSE_ENABLED": "true",
            "COLMAP_GPU_BUILD": "true",
            "COLMAP_DENSE_MESHER": "poisson",
            "COLMAP_DENSE_MAX_IMAGE_SIZE": "2048",
            "COLMAP_GPU_INDEX": "0",
        },
        system_name="Linux",
        executable=executable,
        gpu_device_visible=True,
    )

    result = reconstruction.run_dense_reconstruction(
        executable=executable,
        images=images,
        sparse_model=model,
        output=output,
        capability=capability,
    )

    assert [command[1] for command in commands] == [
        "image_undistorter",
        "patch_match_stereo",
        "stereo_fusion",
        "poisson_mesher",
    ]
    assert "--max_image_size" in commands[0]
    assert commands[0][commands[0].index("--max_image_size") + 1] == "2048"
    assert "--PatchMatchStereo.geom_consistency" in commands[1]
    assert "--PatchMatchStereo.gpu_index" in commands[1]
    assert result["status"] == "completed"
    assert result["geometry_quality"]["method"] == "dense_poisson_preview"
    assert result["geometry_quality"]["status"] == "viewer_ready"
    assert result["geometry_quality"]["viewer_eligible"] is True
    assert result["geometry_quality"]["final_model_eligible"] is False
    assert result["artifacts"]["point_cloud"] == "dense.ply"
    assert result["artifacts"]["mesh"] == "dense-mesh.ply"
    assert result["artifacts"]["glb"] == "dense-model.glb"
    assert (output / "dense-model.glb").is_file()


def test_dense_pipeline_never_exports_geometry_rejected_by_quality_gate(monkeypatch, tmp_path) -> None:
    executable = tmp_path / "colmap"
    executable.write_bytes(b"stub")
    images = tmp_path / "images"
    model = tmp_path / "sparse" / "0"
    output = tmp_path / "output"
    images.mkdir()
    model.mkdir(parents=True)
    output.mkdir()

    def fake_run(command, log, timeout=1800):
        log.write_text(f"simulated {command[1]}", encoding="utf-8")
        if command[1] == "stereo_fusion":
            trimesh.points.PointCloud([[0, 0, 0], [1, 0, 0], [0, 1, 0]]).export(output / "dense.ply")
        elif command[1] == "delaunay_mesher":
            trimesh.Trimesh(
                vertices=[[0, 0, 0], [1, 0, 0], [0, 1, 0]],
                faces=[[0, 1, 2]],
                process=False,
            ).export(output / "dense-mesh.ply")
        return 1.0

    monkeypatch.setattr(reconstruction, "_run", fake_run)
    capability = reconstruction.dense_reconstruction_capability(
        environment={
            "COLMAP_DENSE_ENABLED": "true",
            "COLMAP_GPU_BUILD": "true",
            "COLMAP_DENSE_MESHER": "delaunay",
        },
        system_name="Linux",
        executable=executable,
        gpu_device_visible=True,
    )

    result = reconstruction.run_dense_reconstruction(
        executable=executable,
        images=images,
        sparse_model=model,
        output=output,
        capability=capability,
    )

    assert result["status"] == "rejected"
    assert result["geometry_quality"]["method"] == "dense_delaunay_preview"
    assert result["geometry_quality"]["viewer_eligible"] is False
    assert result["geometry_quality"]["final_model_eligible"] is False
    assert result["artifacts"]["glb"] is None
    assert not (output / "dense-model.glb").exists()
