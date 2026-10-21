<?php

namespace App\Services\Visualization;

use Illuminate\Support\Str;

class NeuralVisionEvidenceMapper
{
    private const PARAMETRIC_KINDS = [
        'couch' => 'sofa',
        'sofa' => 'sofa',
        'dining table' => 'table',
        'table' => 'table',
        'chair' => 'chair',
        'potted plant' => 'plant',
        'plant' => 'plant',
        'bed' => 'bed',
        'sink' => 'sink',
    ];

    private const ROOM_EVIDENCE = [
        'living' => ['couch' => 0.92, 'tv' => 0.55, 'potted plant' => 0.18, 'chair' => 0.12],
        'dining' => ['dining table' => 0.88, 'chair' => 0.18],
        'kitchen' => ['refrigerator' => 0.92, 'oven' => 0.82, 'microwave' => 0.72, 'sink' => 0.38],
        'bedroom' => ['bed' => 0.96],
        'bathroom' => ['toilet' => 0.96, 'sink' => 0.24],
    ];

    public function map(array $result): array
    {
        $objects = data_get($result, 'detection.objects', []);
        $dimensions = data_get($result, 'segmentation.dimensions') ?? data_get($result, 'depth.dimensions');
        $model = data_get($result, 'detection.model');
        $elements = collect(is_array($objects) ? $objects : [])
            ->map(fn ($object) => $this->normalizeObject(is_array($object) ? $object : [], $dimensions, $model))
            ->filter()
            ->values()
            ->all();
        $room = $this->inferRoom($elements, $model);
        $detectionConfidence = collect($elements)->pluck('confidence')->filter(fn ($value) => $value !== null)->max();

        return [
            'elements' => $elements,
            'detection_confidence' => $detectionConfidence === null ? null : (int) round($detectionConfidence * 100),
            'room_guess' => $room['room'],
            'room_confidence' => $room['confidence'],
            'room_provenance' => $room['provenance'],
            'limitations' => [$room['room'] === null
                ? 'Ambiente não classificado: os objetos detectados não sustentam uma inferência de cômodo acima do limite mínimo.'
                : 'O ambiente foi inferido por regras explícitas sobre objetos detectados; não foi produzido por um classificador neural de cômodos.'],
        ];
    }

    private function normalizeObject(array $object, ?array $dimensions, ?string $model): ?array
    {
        $label = Str::of((string) ($object['label'] ?? ''))->lower()->squish()->toString();
        if ($label === '') {
            return null;
        }
        $confidence = is_numeric($object['confidence'] ?? null)
            ? max(0.0, min(1.0, (float) $object['confidence']))
            : null;
        $box = $this->box($object['box_xyxy'] ?? $object['bbox'] ?? null);
        $normalized = $this->normalizedBox($box, $dimensions);

        return array_filter([
            'label' => $label,
            'parametric_kind' => self::PARAMETRIC_KINDS[$label] ?? null,
            'confidence' => $confidence,
            'box_xyxy' => $box,
            'box_normalized' => $normalized,
            'origin' => 'neural_object_detection',
            'model' => $model,
            'provenance' => [
                'source' => 'worker_detection',
                'model' => $model,
                'bounding_box' => $box === null ? 'unavailable' : 'detected_xyxy',
            ],
        ], fn ($value) => $value !== null);
    }

    private function inferRoom(array $elements, ?string $model): array
    {
        $strongestByLabel = collect($elements)
            ->filter(fn (array $element) => isset($element['label'], $element['confidence']))
            ->groupBy('label')
            ->map(fn ($matches) => $matches->sortByDesc('confidence')->first())
            ->values()
            ->all();
        $candidates = [];
        $evidence = [];
        foreach (self::ROOM_EVIDENCE as $room => $weights) {
            $score = 0.0;
            foreach ($strongestByLabel as $element) {
                $label = $element['label'];
                $weight = $weights[$label] ?? null;
                if ($weight === null || ($element['confidence'] ?? null) === null) {
                    continue;
                }
                $contribution = (float) $element['confidence'] * $weight;
                $score = 1 - ((1 - $score) * (1 - $contribution));
                $evidence[$room][] = [
                    'label' => $label,
                    'confidence' => $element['confidence'],
                    'weight' => $weight,
                    'contribution' => round($contribution, 4),
                    'box_xyxy' => $element['box_xyxy'] ?? null,
                ];
            }
            $candidates[$room] = round($score, 4);
        }
        arsort($candidates);
        $rooms = array_keys($candidates);
        $winner = $rooms[0] ?? null;
        $best = $winner === null ? 0.0 : $candidates[$winner];
        $second = isset($rooms[1]) ? $candidates[$rooms[1]] : 0.0;
        $classified = $winner !== null && $best >= 0.50 && ($best - $second) >= 0.05;

        return [
            'room' => $classified ? $winner : null,
            'confidence' => $classified ? (int) round($best * 100) : null,
            'provenance' => [
                'method' => 'object_evidence_rules_v1',
                'source' => 'neural_object_detection',
                'detection_model' => $model,
                'is_neural_room_classifier' => false,
                'classification_status' => $classified ? 'classified' : 'unclassified',
                'minimum_score' => 0.50,
                'minimum_margin' => 0.05,
                'candidate_scores' => $candidates,
                'evidence' => $classified ? ($evidence[$winner] ?? []) : array_values(array_merge(...array_values($evidence ?: [[]]))),
            ],
        ];
    }

    private function box(mixed $value): ?array
    {
        if (! is_array($value) || count($value) !== 4) {
            return null;
        }
        $box = array_map(fn ($coordinate) => is_numeric($coordinate) ? round((float) $coordinate, 2) : null, array_values($value));

        return in_array(null, $box, true) || $box[2] <= $box[0] || $box[3] <= $box[1] ? null : $box;
    }

    private function normalizedBox(?array $box, ?array $dimensions): ?array
    {
        $width = (float) ($dimensions['width'] ?? 0);
        $height = (float) ($dimensions['height'] ?? 0);
        if ($box === null || $width <= 0 || $height <= 0) {
            return null;
        }

        return [
            round(max(0, min(1, $box[0] / $width)), 5),
            round(max(0, min(1, $box[1] / $height)), 5),
            round(max(0, min(1, $box[2] / $width)), 5),
            round(max(0, min(1, $box[3] / $height)), 5),
        ];
    }
}
