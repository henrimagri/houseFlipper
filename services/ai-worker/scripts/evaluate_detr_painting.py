"""Bounded offline DETR diagnostic; never changes production thresholds."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import time

os.environ['HF_HUB_OFFLINE'] = '1'
os.environ['TRANSFORMERS_OFFLINE'] = '1'

import torch
from PIL import Image, ImageOps
from transformers import AutoImageProcessor, AutoModelForObjectDetection


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--images', nargs='+', type=Path, required=True)
    parser.add_argument('--output', type=Path, required=True)
    args = parser.parse_args()
    if not 1 <= len(args.images) <= 8 or args.output.exists():
        parser.error('Use 1..8 photos and a new report path.')
    torch.set_num_threads(4)
    model_id = 'facebook/detr-resnet-50'
    processor = AutoImageProcessor.from_pretrained(model_id, local_files_only=True)
    model = AutoModelForObjectDetection.from_pretrained(model_id, local_files_only=True).eval()
    report = {'model': model_id, 'device': 'cpu', 'threads': 4, 'thresholds': [.3, .5, .72],
              'production_threshold_changed': False, 'ground_truth_available': False,
              'classes': model.config.id2label, 'images': [],
              'limitations': ['Confidence is model score, not measured precision.',
                             'No semantic class for walls, curtains, doors or windows in this COCO detector.',
                             'RAM not sampled; no CUDA allocations in this CPU experiment.']}
    for path in args.images:
        with Image.open(path) as opened:
            if opened.width * opened.height > 16_000_000:
                raise ValueError('Photo exceeds 16 megapixels.')
            image = ImageOps.exif_transpose(opened).convert('RGB')
        image.thumbnail((2048, 2048))
        inputs = processor(images=image, return_tensors='pt')
        started = time.perf_counter()
        with torch.inference_mode():
            outputs = model(**inputs)
        seconds = time.perf_counter() - started
        found = processor.post_process_object_detection(outputs, threshold=.3,
                    target_sizes=torch.tensor([[image.height, image.width]]))[0]
        objects = [{'label': model.config.id2label[int(label)], 'confidence': round(float(score), 6),
                    'box_xyxy': [round(float(x), 2) for x in box]}
                   for score, label, box in zip(found['scores'], found['labels'], found['boxes'])]
        report['images'].append({'name': path.name, 'sha256': hashlib.sha256(path.read_bytes()).hexdigest(),
              'image_size': list(image.size), 'tensor_shape': list(inputs['pixel_values'].shape),
              'inference_seconds': round(seconds, 4), 'detections': objects,
              'counts': {str(t): sum(o['confidence'] >= t for o in objects) for t in report['thresholds']}})
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(report, indent=2, ensure_ascii=False), encoding='utf-8')
    print(json.dumps([{'image': i['name'], 'counts': i['counts'], 'seconds': i['inference_seconds']} for i in report['images']]))


if __name__ == '__main__':
    main()
