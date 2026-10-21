from __future__ import annotations

import json
import math
import os
import time
import uuid
from dataclasses import dataclass
from pathlib import Path
from threading import Lock, RLock
from typing import Any

import numpy as np
import cv2
import torch
from PIL import Image, ImageColor, ImageDraw, ImageFilter, ImageOps
from transformers import (
    AutoImageProcessor,
    AutoModelForDepthEstimation,
    AutoModelForObjectDetection,
    Sam2Model,
    Sam2Processor,
)

from .runtime_diagnostics import native_stage, record_event

DEPTH_MODEL_ID = "depth-anything/Depth-Anything-V2-Small-hf"
SAM_MODEL_ID = "facebook/sam2.1-hiera-tiny"
DETECTION_MODEL_ID = "facebook/detr-resnet-50"
MODEL_LICENSE = "Apache-2.0"
MAX_PIXELS = 16_000_000


def _artifact_root() -> Path:
    root = Path(os.environ.get("AI_WORKER_ARTIFACT_ROOT", Path(__file__).resolve().parents[1] / "artifacts"))
    root.mkdir(parents=True, exist_ok=True)
    return root


def _device() -> torch.device:
    return torch.device("cuda" if torch.cuda.is_available() else "cpu")


def _peak_vram_mb() -> float:
    if not torch.cuda.is_available():
        return 0.0
    return round(torch.cuda.max_memory_allocated() / 1024 / 1024, 2)


def _start_measurement() -> float:
    if torch.cuda.is_available():
        torch.cuda.empty_cache()
        torch.cuda.reset_peak_memory_stats()
        torch.cuda.synchronize()
    return time.perf_counter()


def _finish_measurement(started: float) -> tuple[float, float]:
    if torch.cuda.is_available():
        torch.cuda.synchronize()
    return round((time.perf_counter() - started) * 1000, 2), _peak_vram_mb()


def load_image(path: Path) -> Image.Image:
    with Image.open(path) as opened:
        opened.verify()
    with Image.open(path) as opened:
        image = ImageOps.exif_transpose(opened).convert("RGB")
    if image.width * image.height > MAX_PIXELS:
        raise ValueError("Imagem excede o limite de 16 megapixels.")
    return image


@dataclass
class LoadedModels:
    depth_processor: Any | None = None
    depth_model: Any | None = None
    sam_processor: Any | None = None
    sam_model: Any | None = None
    detection_processor: Any | None = None
    detection_model: Any | None = None


class VisionPipeline:
    def __init__(self) -> None:
        self.models = LoadedModels()
        self.lock = Lock()
        # CUDA kernels and shared model instances are serialized per worker. This
        # prevents concurrent requests from racing over the 6 GB device.
        self.operation_lock = RLock()

    def model_status(self) -> dict[str, Any]:
        return {
            "depth_anything_v2": {
                "model": DEPTH_MODEL_ID,
                "license": MODEL_LICENSE,
                "loaded": self.models.depth_model is not None,
            },
            "sam2": {
                "model": SAM_MODEL_ID,
                "license": MODEL_LICENSE,
                "loaded": self.models.sam_model is not None,
            },
            "detr": {
                "model": DETECTION_MODEL_ID,
                "license": MODEL_LICENSE,
                "loaded": self.models.detection_model is not None,
                "scope": "COCO object detection; it does not classify rooms or materials",
            },
        }

    def _load_depth(self) -> tuple[Any, Any]:
        if self.models.depth_model is None:
            with self.lock:
                if self.models.depth_model is None:
                    with native_stage("load_depth_model", model=DEPTH_MODEL_ID):
                        self.models.depth_processor = AutoImageProcessor.from_pretrained(DEPTH_MODEL_ID)
                        self.models.depth_model = AutoModelForDepthEstimation.from_pretrained(
                            DEPTH_MODEL_ID,
                            dtype=torch.float16 if torch.cuda.is_available() else torch.float32,
                        ).to(_device()).eval()
        return self.models.depth_processor, self.models.depth_model

    def _load_sam(self) -> tuple[Any, Any]:
        if self.models.sam_model is None:
            with self.lock:
                if self.models.sam_model is None:
                    with native_stage("load_sam2_model", model=SAM_MODEL_ID):
                        self.models.sam_processor = Sam2Processor.from_pretrained(SAM_MODEL_ID)
                        self.models.sam_model = Sam2Model.from_pretrained(
                            SAM_MODEL_ID,
                            dtype=torch.float16 if torch.cuda.is_available() else torch.float32,
                        ).to(_device()).eval()
        return self.models.sam_processor, self.models.sam_model

    def _load_detection(self) -> tuple[Any, Any]:
        if self.models.detection_model is None:
            with self.lock:
                if self.models.detection_model is None:
                    with native_stage("load_detr_model", model=DETECTION_MODEL_ID):
                        self.models.detection_processor = AutoImageProcessor.from_pretrained(DETECTION_MODEL_ID)
                        self.models.detection_model = AutoModelForObjectDetection.from_pretrained(
                            DETECTION_MODEL_ID,
                            dtype=torch.float32,
                        ).to(_device()).eval()
        return self.models.detection_processor, self.models.detection_model

    def depth(self, image: Image.Image, output_dir: Path) -> dict[str, Any]:
        with self.operation_lock, native_stage("depth_inference", width=image.width, height=image.height):
            started = _start_measurement()
            prediction = self._predict_relative_depth_locked(image)
        latency_ms, vram_mb = _finish_measurement(started)
        minimum, maximum = float(np.min(prediction)), float(np.max(prediction))
        normalized = ((prediction - minimum) / max(maximum - minimum, 1e-8) * 255).clip(0, 255).astype(np.uint8)
        depth_path = output_dir / "depth.png"
        Image.fromarray(normalized, mode="L").save(depth_path)
        return {
            "artifact": depth_path.name,
            "model": DEPTH_MODEL_ID,
            "license": MODEL_LICENSE,
            "device": str(_device()),
            "latency_ms": latency_ms,
            "peak_vram_mb": vram_mb,
            "relative_depth": {"min": round(minimum, 6), "max": round(maximum, 6), "mean": round(float(np.mean(prediction)), 6)},
            "dimensions": {"width": image.width, "height": image.height},
        }

    def predict_relative_depth(self, image: Image.Image) -> np.ndarray:
        """Return raw relative depth for calibrated fusion; values are not metric."""
        with self.operation_lock, native_stage("depth_array_inference", width=image.width, height=image.height):
            return self._predict_relative_depth_locked(image)

    def _predict_relative_depth_locked(self, image: Image.Image) -> np.ndarray:
        processor, model = self._load_depth()
        inputs = processor(images=image, return_tensors="pt")
        inputs = {key: value.to(_device()) for key, value in inputs.items()}
        if torch.cuda.is_available():
            inputs = {key: value.half() if value.is_floating_point() else value for key, value in inputs.items()}
        with torch.inference_mode():
            predicted = model(**inputs).predicted_depth
        return torch.nn.functional.interpolate(
            predicted.unsqueeze(1), size=(image.height, image.width), mode="bicubic", align_corners=False
        ).squeeze().float().cpu().numpy()

    def segment(self, image: Image.Image, output_dir: Path, points: list[Any] | None = None) -> dict[str, Any]:
        with self.operation_lock:
            return self._segment_locked(image, output_dir, points)

    def _segment_locked(self, image: Image.Image, output_dir: Path, points: list[Any] | None = None) -> dict[str, Any]:
        processor, model = self._load_sam()
        if not points:
            points = [{"x": image.width * 0.5, "y": image.height * 0.6, "label": 1}]
        safe_points: list[list[float]] = []
        point_labels: list[int] = []
        for point in points[:16]:
            if isinstance(point, dict):
                x, y, label = point.get("x"), point.get("y"), point.get("label", 1)
            else:
                x, y = point[:2]
                label = point[2] if len(point) > 2 else 1
            safe_points.append([
                max(0.0, min(float(x), image.width - 1)),
                max(0.0, min(float(y), image.height - 1)),
            ])
            point_labels.append(1 if int(label) > 0 else 0)
        started = _start_measurement()
        inputs = processor(
            images=image,
            input_points=[[[point] for point in safe_points]],
            input_labels=[[[label] for label in point_labels]],
            return_tensors="pt",
        ).to(_device())
        if torch.cuda.is_available():
            inputs["pixel_values"] = inputs["pixel_values"].half()
        with native_stage("sam2_inference", width=image.width, height=image.height), torch.inference_mode():
            outputs = model(**inputs, multimask_output=False)
        masks = processor.post_process_masks(outputs.pred_masks.float().cpu(), inputs["original_sizes"])[0]
        latency_ms, vram_mb = _finish_measurement(started)
        combined = masks.squeeze(1).any(dim=0).numpy().astype(np.uint8) * 255
        combined = self.refine_surface_mask(combined, safe_points, point_labels)
        mask_path = output_dir / "mask.png"
        Image.fromarray(combined, mode="L").save(mask_path)
        overlay = image.copy().convert("RGBA")
        tint = Image.new("RGBA", image.size, ImageColor.getrgb("#18c9d8") + (0,))
        alpha = Image.fromarray((combined * 0.42).astype(np.uint8), mode="L")
        tint.putalpha(alpha)
        overlay.alpha_composite(tint)
        overlay_path = output_dir / "segmentation-overlay.png"
        overlay.convert("RGB").save(overlay_path)
        coverage = float(np.count_nonzero(combined)) / float(combined.size)
        return {
            "artifact": mask_path.name,
            "overlay_artifact": overlay_path.name,
            "model": SAM_MODEL_ID,
            "license": MODEL_LICENSE,
            "device": str(_device()),
            "latency_ms": latency_ms,
            "peak_vram_mb": vram_mb,
            "prompt_points": safe_points,
            "prompt_labels": point_labels,
            "mask_count": int(masks.shape[0]),
            "coverage_ratio": round(coverage, 6),
            "dimensions": {"width": image.width, "height": image.height},
            "edge_refinement": "connected_positive_regions+morphological_close+antialiased_composition",
        }

    @staticmethod
    def refine_surface_mask(mask: np.ndarray, points: list[list[float]], labels: list[int]) -> np.ndarray:
        """Keep positive connected regions and remove isolated SAM leakage."""
        binary = (mask > 127).astype(np.uint8)
        _count, components = cv2.connectedComponents(binary)
        selected: set[int] = set()
        height, width = binary.shape
        for point, label in zip(points, labels):
            if label <= 0:
                continue
            x = max(0, min(width - 1, int(round(point[0]))))
            y = max(0, min(height - 1, int(round(point[1]))))
            component = int(components[y, x])
            if component > 0:
                selected.add(component)
        if selected:
            binary = np.isin(components, list(selected)).astype(np.uint8)
        binary = cv2.morphologyEx(binary, cv2.MORPH_CLOSE, np.ones((5, 5), dtype=np.uint8))
        binary = cv2.morphologyEx(binary, cv2.MORPH_OPEN, np.ones((3, 3), dtype=np.uint8))
        return binary * 255

    @staticmethod
    def automatic_exclusion_points(
        objects: list[dict[str, Any]],
        user_points: list[Any] | None,
        limit: int = 8,
    ) -> list[dict[str, float | int | str]]:
        """Turn foreground detections into negative SAM prompts without crude box masks."""
        excluded_labels = {
            "person", "couch", "chair", "dining table", "bed", "tv", "potted plant",
            "vase", "bottle", "book", "clock", "refrigerator", "oven", "microwave", "sink",
        }
        positives = [point for point in (user_points or []) if isinstance(point, dict) and int(point.get("label", 1)) > 0]
        exclusions: list[dict[str, float | int | str]] = []
        for obj in sorted(objects, key=lambda item: float(item.get("confidence", 0)), reverse=True):
            box = obj.get("box_xyxy")
            label = str(obj.get("label", "")).lower()
            if label not in excluded_labels or not isinstance(box, list) or len(box) != 4:
                continue
            if any(float(box[0]) <= float(point.get("x", -1)) <= float(box[2]) and float(box[1]) <= float(point.get("y", -1)) <= float(box[3]) for point in positives):
                continue
            exclusions.append({
                "x": round((float(box[0]) + float(box[2])) / 2, 2),
                "y": round((float(box[1]) + float(box[3])) / 2, 2),
                "label": 0,
                "source": f"detr:{label}",
            })
            if len(exclusions) >= limit:
                break
        return exclusions

    def detect(self, image: Image.Image, threshold: float = 0.72) -> dict[str, Any]:
        with self.operation_lock:
            return self._detect_locked(image, threshold)

    def _detect_locked(self, image: Image.Image, threshold: float = 0.72) -> dict[str, Any]:
        processor, model = self._load_detection()
        started = _start_measurement()
        inputs = processor(images=image, return_tensors="pt")
        inputs = {key: value.to(_device()) for key, value in inputs.items()}
        with native_stage("detr_inference", width=image.width, height=image.height), torch.inference_mode():
            outputs = model(**inputs)
        target_sizes = torch.tensor([[image.height, image.width]], device=outputs.logits.device)
        processed = processor.post_process_object_detection(outputs, threshold=threshold, target_sizes=target_sizes)[0]
        latency_ms, vram_mb = _finish_measurement(started)
        objects = []
        for score, label, box in zip(processed["scores"], processed["labels"], processed["boxes"]):
            objects.append({
                "label": model.config.id2label[int(label.item())],
                "confidence": round(float(score.item()), 6),
                "box_xyxy": [round(float(value), 2) for value in box.tolist()],
            })
        return {
            "model": DETECTION_MODEL_ID,
            "license": MODEL_LICENSE,
            "device": str(_device()),
            "threshold": threshold,
            "latency_ms": latency_ms,
            "peak_vram_mb": vram_mb,
            "objects": objects,
            "limitations": "Classes limitadas ao conjunto COCO; ausência de detecção não prova ausência do objeto.",
        }

    @staticmethod
    def validate_manual_strokes(strokes: Any) -> None:
        if not isinstance(strokes, list) or not 1 <= len(strokes) <= 64:
            raise ValueError("A máscara manual exige entre 1 e 64 traços.")

        def bounded_number(value: Any, minimum: float, maximum: float) -> bool:
            return (isinstance(value, (int, float)) and not isinstance(value, bool)
                    and minimum <= value <= maximum and math.isfinite(value))

        point_count = 0
        for stroke in strokes:
            if not isinstance(stroke, dict) or stroke.get("mode") not in ("paint", "erase"):
                raise ValueError("Modo de traço inválido; use paint ou erase.")
            if not bounded_number(stroke.get("radius"), 0.002, 0.15):
                raise ValueError("Raio normalizado deve estar entre 0.002 e 0.15.")
            points = stroke.get("points")
            if not isinstance(points, list) or not 1 <= len(points) <= 256:
                raise ValueError("Cada traço exige entre 1 e 256 pontos.")
            point_count += len(points)
            for point in points:
                if (not isinstance(point, dict) or not bounded_number(point.get("x"), 0, 1)
                        or not bounded_number(point.get("y"), 0, 1)):
                    raise ValueError("Coordenadas da máscara devem ser números finitos entre 0 e 1.")
        if point_count > 16384:
            raise ValueError("A máscara excede o limite de 16384 pontos.")

    @staticmethod
    def rasterize_manual_mask(size: tuple[int, int], strokes: Any) -> Image.Image:
        """Rasterize only user-painted pixels; no segmentation or surface inference."""
        VisionPipeline.validate_manual_strokes(strokes)
        width, height = size
        mask = Image.new("L", size, 0)
        draw = ImageDraw.Draw(mask)
        for stroke in strokes:
            fill = 255 if stroke["mode"] == "paint" else 0
            radius = max(0.5, float(stroke["radius"]) * min(width, height))
            points = [(float(point["x"]) * (width - 1), float(point["y"]) * (height - 1))
                      for point in stroke["points"]]
            if len(points) > 1:
                draw.line(points, fill=fill, width=max(1, round(radius * 2)), joint="curve")
            for x, y in points:
                draw.ellipse((x - radius, y - radius, x + radius, y + radius), fill=fill)
        if mask.getbbox() is None:
            raise ValueError("A máscara está vazia ou foi totalmente apagada. Pinte uma região antes de aplicar.")
        return mask

    @staticmethod
    def _save_manual_mask(image: Image.Image, mask: Image.Image, output_dir: Path) -> dict[str, Any]:
        mask.save(output_dir / "mask.png")
        tinted = image.convert("RGBA")
        tint = Image.new("RGBA", image.size, (24, 201, 216, 0))
        tint.putalpha(mask.point(lambda value: round(value * 0.42)))
        tinted.alpha_composite(tint)
        tinted.convert("RGB").save(output_dir / "segmentation-overlay.png")
        return {
            "artifact": "mask.png", "overlay_artifact": "segmentation-overlay.png",
            "provenance": "manual_user_mask", "model_used": False, "model": None,
            "device": "cpu", "mask_count": 1,
            "coverage_ratio": round(float(np.count_nonzero(np.asarray(mask))) / (image.width * image.height), 6),
            "dimensions": {"width": image.width, "height": image.height},
            "coordinate_system": "normalized_exif_oriented_image", "edge_refinement": "user_brush_only",
            "limitations": "Região desenhada pelo usuário; não identifica nem infere superfícies automaticamente.",
        }

    @staticmethod
    def compose_paint(
        image: Image.Image,
        mask: Image.Image,
        color: str,
        opacity: float = 0.72,
        material: str = "paint",
        *,
        refine_mask: bool = True,
    ) -> Image.Image:
        """Recolors a segmented surface while preserving source luminance and texture."""
        source = np.asarray(image.convert("RGB"), dtype=np.float32) / 255.0
        target = np.asarray(ImageColor.getrgb(color), dtype=np.float32) / 255.0
        luminance = source[..., 0] * 0.2126 + source[..., 1] * 0.7152 + source[..., 2] * 0.0722
        target_luminance = max(float(target[0] * 0.2126 + target[1] * 0.7152 + target[2] * 0.0722), 0.08)
        relight = np.clip(target.reshape(1, 1, 3) * (luminance[..., None] / target_luminance), 0.0, 1.0)

        if material == "wood":
            rows, columns = np.indices(luminance.shape)
            grain = 0.94 + 0.06 * np.sin(columns / 11.0 + rows / 43.0)
            joints = np.where((rows % 54) < 2, 0.84, 1.0)
            relight = np.clip(relight * grain[..., None] * joints[..., None], 0.0, 1.0)

        refined = (mask.convert("L").filter(ImageFilter.MedianFilter(size=5)).filter(ImageFilter.GaussianBlur(radius=2.2))
                   if refine_mask else mask.convert("L").filter(ImageFilter.GaussianBlur(radius=0.6)))
        original_mask = np.asarray(mask.convert("L"), dtype=np.float32) / 255.0
        feathered = np.asarray(refined, dtype=np.float32) / 255.0
        alpha = (feathered * (original_mask > 0.02) * max(0.0, min(float(opacity), 1.0)))[..., None]
        composed = source * (1.0 - alpha) + relight * alpha
        output = np.clip(composed * 255.0, 0, 255).astype(np.uint8)
        outside = alpha[..., 0] == 0
        output[outside] = np.asarray(image.convert("RGB"))[outside]
        return Image.fromarray(output, mode="RGB")

    def simulate(
        self,
        input_path: Path,
        color: str,
        points: list[Any] | None = None,
        opacity: float = 0.72,
        material: str = "paint",
        *,
        mask_mode: str = "sam",
        mask_strokes: list[dict[str, Any]] | None = None,
    ) -> dict[str, Any]:
        if mask_mode not in {"sam", "manual"}:
            raise ValueError("Modo de máscara inválido.")
        if mask_mode == "sam" and mask_strokes is not None:
            raise ValueError("Traços manuais exigem o modo manual.")
        if mask_mode == "manual" and points:
            raise ValueError("Pontos SAM não podem ser combinados com uma máscara manual.")
        with self.operation_lock, native_stage("simulate_photo", source=input_path.name):
            image = load_image(input_path)
            manual_mask = self.rasterize_manual_mask(image.size, mask_strokes) if mask_mode == "manual" else None
            job_id = str(uuid.uuid4())
            output_dir = _artifact_root() / job_id
            output_dir.mkdir(parents=True, exist_ok=False)
            original_path = output_dir / ("original.png" if mask_mode == "manual" else "original.jpg")
            if manual_mask is not None:
                image.save(original_path)
                detection = {"model_used": False, "model": None, "objects": [], "status": "not_requested"}
                automatic_exclusions = []
                segmentation = self._save_manual_mask(image, manual_mask, output_dir)
            else:
                image.save(original_path, quality=94)
                detection = self._detect_locked(image)
                automatic_exclusions = self.automatic_exclusion_points(detection["objects"], points, max(0, 16 - len(points or [])))
                refined_points = list(points or []) + automatic_exclusions
                segmentation = self._segment_locked(image, output_dir, refined_points)
        mask = Image.open(output_dir / segmentation["artifact"]).convert("L")
        edited = self.compose_paint(image, mask, color, opacity, material, refine_mask=mask_mode != "manual")
        simulation_path = output_dir / "simulation.png"
        edited.save(simulation_path, optimize=True)
        comparison = Image.new("RGB", (image.width * 2, image.height), "white")
        comparison.paste(image, (0, 0))
        comparison.paste(edited, (image.width, 0))
        comparison_path = output_dir / "comparison.png"
        comparison.save(comparison_path, optimize=True)
        result = {
            "job_id": job_id,
            "status": "completed",
            "kind": "photo_simulation",
            "original_artifact": original_path.name,
            "simulation_artifact": simulation_path.name,
            "comparison_artifact": comparison_path.name,
            "segmentation": segmentation,
            "detection": detection,
            "composition": {
                "color": color.lower(),
                "opacity": round(max(0.0, min(float(opacity), 1.0)), 3),
                "material": material,
                "mask_mode": mask_mode,
                "method": "luminance_preserving_masked_recolor",
                "preserves_geometry": True,
                "automatic_exclusions": automatic_exclusions,
                "limitations": "Simulação 2D guiada por máscara; reflexos complexos e materiais físicos não são reconstruídos.",
            },
        }
        (output_dir / "result.json").write_text(json.dumps(result, ensure_ascii=False, indent=2), encoding="utf-8")
        return result

    def analyze(self, input_path: Path, points: list[Any] | None = None) -> dict[str, Any]:
        with self.operation_lock, native_stage("analyze_image", source=input_path.name):
            job_id = str(uuid.uuid4())
            output_dir = _artifact_root() / job_id
            output_dir.mkdir(parents=True, exist_ok=False)
            image = load_image(input_path)
            original_path = output_dir / "original.jpg"
            image.save(original_path, quality=94)
            depth = self.depth(image, output_dir)
            segment = self._segment_locked(image, output_dir, points)
            detection = self._detect_locked(image)
        result = {
            "job_id": job_id,
            "status": "completed",
            "kind": "vision",
            "original_artifact": original_path.name,
            "depth": depth,
            "segmentation": segment,
            "detection": detection,
            "models": self.model_status(),
            "structured_result": {
                "surface_regions": [{"kind": "prompted_region", "coverage_ratio": segment["coverage_ratio"]}],
                "visible_elements": detection["objects"],
                "limitations": [
                    "A profundidade é relativa e monocular; não fornece escala métrica.",
                    "SAM 2 segmenta as regiões indicadas por pontos; não atribui classe semântica ao objeto.",
                    detection["limitations"],
                ],
            },
        }
        (output_dir / "result.json").write_text(json.dumps(result, ensure_ascii=False, indent=2), encoding="utf-8")
        return result

    def unload_models(self) -> None:
        with self.operation_lock, self.lock, native_stage("unload_vision_models"):
            self.models = LoadedModels()
            if torch.cuda.is_available():
                torch.cuda.synchronize()
                torch.cuda.empty_cache()
            record_event("vision_models_unloaded")


vision_pipeline = VisionPipeline()


def make_authorized_fixture(path: Path) -> None:
    """Creates a deterministic project-owned test image. It is not training data or a real property."""
    image = Image.new("RGB", (960, 640), "#ddd4c5")
    draw = ImageDraw.Draw(image)
    draw.rectangle((0, 390, 960, 640), fill="#8b6f51")
    draw.rectangle((90, 120, 360, 390), fill="#8db9d4", outline="#f8fafc", width=16)
    draw.rectangle((550, 285, 880, 500), fill="#385b72")
    draw.rectangle((585, 245, 845, 320), fill="#466d84")
    draw.ellipse((690, 155, 760, 225), fill="#f4d35e")
    draw.rectangle((190, 430, 510, 535), fill="#d2b48c")
    draw.rectangle((220, 535, 245, 610), fill="#4b3621")
    draw.rectangle((455, 535, 480, 610), fill="#4b3621")
    for x in range(0, 960, 80):
        draw.line((x, 390, 480 + x // 2, 640), fill="#735b46", width=2)
    path.parent.mkdir(parents=True, exist_ok=True)
    image.save(path, quality=95)
