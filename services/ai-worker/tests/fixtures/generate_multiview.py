from __future__ import annotations

import json
import math
from pathlib import Path

import cv2
import numpy as np


def look_at(position: np.ndarray, target: np.ndarray) -> np.ndarray:
    forward = target - position
    forward /= np.linalg.norm(forward)
    right = np.cross(forward, np.array([0.0, 1.0, 0.0]))
    right /= np.linalg.norm(right)
    up = np.cross(right, forward)
    return np.stack([right, -up, forward])


def texture(seed: int, label: str, size: int = 1000) -> np.ndarray:
    rng = np.random.default_rng(seed)
    base = rng.integers(35, 220, (size, size, 3), dtype=np.uint8)
    base = cv2.GaussianBlur(base, (3, 3), 0)
    for index in range(180):
        center = tuple(rng.integers(20, size - 20, 2).tolist())
        color = tuple(int(value) for value in rng.integers(10, 245, 3))
        radius = int(rng.integers(4, 26))
        cv2.circle(base, center, radius, color, -1)
        cv2.putText(base, str(index % 10), center, cv2.FONT_HERSHEY_SIMPLEX, 0.35, (255, 255, 255), 1)
    cv2.putText(base, label, (60, 500), cv2.FONT_HERSHEY_DUPLEX, 3.0, (255, 255, 255), 7)
    return base


def render_dataset(output: Path, views: int = 18) -> dict:
    output.mkdir(parents=True, exist_ok=True)
    width, height, focal = 960, 720, 820.0
    intrinsic = np.array([[focal, 0, width / 2], [0, focal, height / 2], [0, 0, 1]], dtype=np.float64)
    surfaces = [
        (texture(11, "BACK"), np.array([[-3, -2, 0], [3, -2, 0], [3, 2, 0], [-3, 2, 0]], dtype=np.float64)),
        (texture(22, "FLOOR"), np.array([[-3, 2, 0], [3, 2, 0], [3, 2, 5], [-3, 2, 5]], dtype=np.float64)),
        (texture(33, "SIDE"), np.array([[-3, -2, 0], [-3, -2, 5], [-3, 2, 5], [-3, 2, 0]], dtype=np.float64)),
    ]
    source = np.array([[0, 0], [999, 0], [999, 999], [0, 999]], dtype=np.float32)
    cameras = []
    for index in range(views):
        angle = -0.36 + index * (0.72 / max(views - 1, 1))
        position = np.array([math.sin(angle) * 2.0, -0.35 + 0.5 * math.sin(index * 0.7), -6.6 + 0.25 * math.cos(index * 0.5)])
        rotation = look_at(position, np.array([0.0, 0.1, 1.7]))
        canvas = np.full((height, width, 3), (24, 30, 42), dtype=np.uint8)
        for tex, corners in surfaces:
            camera_points = (rotation @ (corners - position).T).T
            projected = (intrinsic @ camera_points.T).T
            projected = (projected[:, :2] / projected[:, 2:3]).astype(np.float32)
            transform = cv2.getPerspectiveTransform(source, projected)
            warped = cv2.warpPerspective(tex, transform, (width, height))
            mask = cv2.warpPerspective(np.full((1000, 1000), 255, np.uint8), transform, (width, height))
            canvas[mask > 0] = warped[mask > 0]
        file = output / f"view-{index:03d}.jpg"
        cv2.imwrite(str(file), canvas, [cv2.IMWRITE_JPEG_QUALITY, 95])
        cameras.append({"file": file.name, "position": position.round(6).tolist()})
    manifest = {
        "license": "Project-owned deterministic synthetic fixture; commercial use permitted.",
        "purpose": "Technical multiview pipeline validation only; not a real property.",
        "views": views,
        "width": width,
        "height": height,
        "cameras": cameras,
    }
    (output / "manifest.json").write_text(json.dumps(manifest, indent=2), encoding="utf-8")
    return manifest


if __name__ == "__main__":
    render_dataset(Path(__file__).resolve().parent / "multiview-room")
