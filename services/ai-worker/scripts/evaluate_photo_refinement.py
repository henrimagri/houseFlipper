"""Run real offline SAM and conservative refinements on explicitly licensed QA photos."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import sys
import time

os.environ['HF_HUB_OFFLINE'] = '1'
os.environ['TRANSFORMERS_OFFLINE'] = '1'
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

import numpy as np
import torch
from PIL import Image
from app.vision import VisionPipeline, load_image


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--cases', type=Path, required=True, help='JSON array of photo paths and pixel prompts')
    parser.add_argument('--output', type=Path, required=True, help='New private artifact directory')
    args = parser.parse_args()
    cases = json.loads(args.cases.read_text(encoding='utf-8'))
    if not isinstance(cases, list) or not 1 <= len(cases) <= 8 or args.output.exists():
        parser.error('Use 1..8 cases and a new output directory.')
    args.output.mkdir(parents=True)
    os.environ['AI_WORKER_ARTIFACT_ROOT'] = str(args.output.resolve())
    torch.set_num_threads(4)
    pipeline = VisionPipeline()
    report = {'device': 'cuda' if torch.cuda.is_available() else 'cpu', 'threads': 4, 'ground_truth_available': False,
              'automatic_quality_approved': False, 'cases': []}
    for case in cases:
        path = Path(case['image'])
        source = load_image(path)
        started = time.perf_counter()
        selection = pipeline.segment_image(path, case['points'])
        base_path = args.output / selection['job_id'] / 'mask.png'
        base_hash = hashlib.sha256(base_path.read_bytes()).hexdigest()
        center = next(p for p in case['points'] if p['label'] == 1)
        # Controlled manual hole plus small added patch; this tests editing, not mask accuracy.
        x, y = center['x'] / (source.width - 1), center['y'] / (source.height - 1)
        strokes = [{'mode': 'erase', 'radius': .015, 'points': [{'x': x, 'y': y}]},
                   {'mode': 'paint', 'radius': .008, 'points': [{'x': min(.99, x + .035), 'y': y}]}]
        outputs = []
        for finish in ('matte', 'satin', 'semigloss'):
            result = pipeline.simulate(path, '#6288a0', mask_mode='refined', mask_strokes=strokes,
                                       base_mask_path=base_path, finish=finish, opacity=.82)
            folder = args.output / result['job_id']
            mask = np.asarray(Image.open(folder/'mask.png')) > 0
            rendered = np.asarray(Image.open(folder/'simulation.png').convert('RGB'))
            changed = np.any(np.asarray(source) != rendered, axis=2)
            outputs.append({'finish':finish,'job_id':result['job_id'], 'selected_pixels':int(mask.sum()),
                            'changed_outside_mask':int((changed & ~mask).sum()),
                            'model_used':result['segmentation']['model_used'],
                            'center_erased':not bool(mask[round(center['y']),round(center['x'])])})
        report['cases'].append({'name':path.name,'sha256':hashlib.sha256(path.read_bytes()).hexdigest(),
            'selection':selection, 'strokes':strokes,'results':outputs,
            'base_unchanged':hashlib.sha256(base_path.read_bytes()).hexdigest()==base_hash,
            'total_seconds':round(time.perf_counter()-started,4)})
    (args.output/'report.json').write_text(json.dumps(report,indent=2),encoding='utf-8')
    print(json.dumps([{'name':c['name'],'seconds':c['total_seconds'],'base_unchanged':c['base_unchanged'],
          'outside_pixels':[r['changed_outside_mask'] for r in c['results']]} for c in report['cases']]))


if __name__ == '__main__':
    main()
