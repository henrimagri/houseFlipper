<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('property_photo_analyses')
            ->whereIn('origin', ['neural', 'neural_object_detection'])
            ->orderBy('id')
            ->get(['id', 'room_guess', 'confidence', 'elements'])
            ->each(function (object $row): void {
                $elements = is_string($row->elements) ? json_decode($row->elements, true) : $row->elements;
                $elements = is_array($elements) ? $elements : [];
                $labels = collect($elements)->pluck('label')->filter()->map(fn ($label) => strtolower((string) $label));
                $detectionConfidence = collect($elements)->max(fn ($element) => is_array($element) ? (float) ($element['confidence'] ?? 0) : 0);
                $room = $row->room_guess;
                $roomConfidence = $room ? (int) $row->confidence : null;
                $evidence = [];
                if (! $room && $labels->contains('bed')) {
                    [$room, $roomConfidence, $evidence] = ['bedroom', 95, ['bed']];
                } elseif (! $room && $labels->contains('couch')) {
                    [$room, $roomConfidence, $evidence] = ['living', 90, ['couch']];
                } elseif (! $room && $labels->contains('dining table') && $labels->contains('chair')) {
                    [$room, $roomConfidence, $evidence] = ['living', 82, ['dining table', 'chair']];
                }

                DB::table('property_photo_analyses')->where('id', $row->id)->update([
                    'room_guess' => $room,
                    'confidence' => $roomConfidence,
                    'detection_confidence' => $detectionConfidence > 0 ? (int) round($detectionConfidence * 100) : null,
                    'room_confidence' => $roomConfidence,
                    'room_provenance' => json_encode([
                        'method' => $room ? 'object_evidence_rules_v1_backfill' : 'unclassified',
                        'evidence' => $evidence,
                        'is_neural_room_classifier' => false,
                        'classification_status' => $room ? 'classified' : 'unclassified',
                    ], JSON_THROW_ON_ERROR),
                ]);
            });
    }

    public function down(): void
    {
        // Evidence backfill is intentionally non-destructive on rollback.
    }
};
