from types import SimpleNamespace

import numpy as np
import pytest
import torch
from PIL import Image
from transformers import Sam2ImageProcessor, Sam2Processor

from app import vision


def test_sam_positive_and_negative_points_share_one_object(tmp_path, monkeypatch):
    # Use the installed processor itself without from_pretrained or model weights.
    processor = Sam2Processor(Sam2ImageProcessor(size={"height": 64, "width": 64}))
    captured = {}

    def model(**inputs):
        captured.update(inputs)
        assert tuple(inputs["input_points"].shape) == (1, 1, 3, 2)
        assert tuple(inputs["input_labels"].shape) == (1, 1, 3)
        assert inputs["input_labels"].tolist() == [[[1, 1, 0]]]
        assert inputs["multimask_output"] is False
        logits = torch.full((1, 1, 1, 64, 64), -10.0)
        logits[:, :, :, 5:59, 5:59] = 10.0
        logits[:, :, :, 24:40, 24:40] = -10.0
        return SimpleNamespace(pred_masks=logits)

    pipeline = vision.VisionPipeline()
    monkeypatch.setattr(pipeline, "_load_sam", lambda: (processor, model))
    monkeypatch.setattr(vision.torch.cuda, "is_available", lambda: False)
    result = pipeline.segment(Image.new("RGB", (64, 64), "gray"), tmp_path, [
        {"x": 10, "y": 10, "label": 1},
        {"x": 50, "y": 50, "label": 1},
        {"x": 32, "y": 32, "label": 0},
    ])
    assert captured["input_points"].tolist() == [[[[10, 10], [50, 50], [32, 32]]]]
    mask = np.asarray(Image.open(tmp_path / result["artifact"]))
    assert mask[10, 10] == 255
    assert mask[32, 32] == 0
    assert result["mask_count"] == 1
    assert result["prompt_labels"] == [1, 1, 0]


def test_sam_rejects_unexpected_multiple_object_masks(tmp_path, monkeypatch):
    processor = Sam2Processor(Sam2ImageProcessor(size={"height": 64, "width": 64}))
    pipeline = vision.VisionPipeline()
    monkeypatch.setattr(pipeline, "_load_sam", lambda: (
        processor, lambda **inputs: SimpleNamespace(pred_masks=torch.ones((1, 2, 1, 64, 64))),
    ))
    monkeypatch.setattr(vision.torch.cuda, "is_available", lambda: False)
    with pytest.raises(ValueError, match="único objeto"):
        pipeline.segment(Image.new("RGB", (64, 64), "gray"), tmp_path, [{"x": 20, "y": 20, "label": 1}])
