<?php

namespace App\Services\Visualization;

use InvalidArgumentException;

class SceneGraphFactory
{
    public function fromBrief(array $brief, array $photoAssets, array $subject): array
    {
        $rooms = $brief['ambientes'] ?? [];
        $objects = [];
        $surfaces = [];
        $planes = [];
        $roomDimensions = [];
        foreach ($rooms as $room) {
            $key = (string) ($room['nome'] ?? 'living');
            $roomDimensions[$key] = ['width' => 5.2, 'depth' => 4.2, 'height' => 2.65, 'source' => 'estimated'];
            $palette = $room['palette'][0]['hex'] ?? '#d9cbb8';
            $floor = $room['palette'][1]['hex'] ?? '#cbb79a';
            foreach (['south', 'north', 'west', 'east'] as $side) {
                $surfaces[] = ['id' => $key.'-wall-'.$side, 'kind' => 'wall', 'side' => $side, 'room' => $key, 'color' => $palette, 'material' => 'paint'];
            }
            $surfaces[] = ['id' => $key.'-floor', 'kind' => 'floor', 'room' => $key, 'color' => $floor, 'material' => $this->floorMaterial($room)];
            $surfaces[] = ['id' => $key.'-ceiling', 'kind' => 'ceiling', 'room' => $key, 'color' => '#f4f1ea', 'material' => 'paint'];
            foreach ($room['imagens_referencia'] ?? [] as $index => $assetId) {
                $planes[] = [
                    'id' => $key.'-photo-'.$index,
                    'room' => $key,
                    'asset_id' => (int) $assetId,
                    'wall' => $key === 'facade' ? 'south' : 'south',
                ];
            }
            $slot = 0;
            $sceneElements = $room['elementos_estruturados'] ?? [];
            if ($sceneElements === []) {
                $sceneElements = array_map(fn ($label) => ['label' => $label], $room['elementos_visiveis'] ?? []);
            }
            foreach ($sceneElements as $element) {
                $element = is_array($element) ? $element : ['label' => (string) $element];
                $kind = $element['parametric_kind'] ?? $this->objectKind((string) ($element['label'] ?? ''));
                if ($kind === null) {
                    continue;
                }
                $objects[] = $this->makeObject($key.'-'.$kind.'-'.$slot, $kind, $key, 'evidence', $slot, $element);
                $slot++;
            }
            if ($key === 'living' && ! collect($objects)->contains(fn (array $object) => ($object['room'] ?? null) === $key && ($object['kind'] ?? null) === 'lamp')) {
                $lamp = $this->makeObject($key.'-lamp-template', 'lamp', $key, 'conceptual_template', $slot++);
                $lamp['position'] = [-2.0, 0, -1.35];
                $objects[] = $lamp;
            }
        }
        $hasPhotos = $planes !== [];
        $hasFacade = (bool) ($brief['fachada_disponivel'] ?? false);
        $fidelity = $hasPhotos ? 'photo_referenced' : ((float) ($brief['area_anunciada_m2'] ?? 0) > 0 ? 'parametric' : 'illustrative');
        $activeRoom = collect($rooms)
            ->filter(fn (array $room) => ($room['nome'] ?? 'unclassified') !== 'unclassified' && ($room['classificacao_ambiente'] ?? 'unclassified') !== 'unclassified')
            ->sortByDesc(fn (array $room) => (int) ($room['confianca_ambiente'] ?? 0))
            ->first() ?? ($rooms[0] ?? ['nome' => 'living']);

        return [
            'version' => 1,
            'fidelity' => $fidelity,
            'active_room' => $activeRoom['nome'] ?? 'living',
            'disclaimer' => $hasPhotos
                ? 'Cores e plano fotográfico vêm das imagens deste registro. A geometria é aproximada. Não é fotogrametria nem planta fiel.'
                : 'Sem fotografia utilizável neste registro. Cena conceitual da ficha, identificada como simulação.',
            'rooms' => array_map(fn (array $room) => [
                'key' => $room['nome'] ?? 'living',
                'label' => $room['rotulo'] ?? $room['nome'] ?? 'Ambiente',
                'confidence' => $room['confianca_visual'] ?? null,
                'room_confidence' => $room['confianca_ambiente'] ?? null,
                'detection_confidence' => $room['confianca_deteccao'] ?? null,
                'classification_status' => $room['classificacao_ambiente'] ?? null,
                'provenance' => $room['proveniencia_ambiente'] ?? [],
                'unknowns' => $room['desconhecido'] ?? [],
            ], $rooms),
            'surfaces' => $surfaces,
            'objects' => $objects,
            'lighting' => [
                'preset' => 'daylight',
                'ambient_intensity' => 0.7,
                'directional_intensity' => 1.15,
                'color' => '#fff4e5',
            ],
            'room_dimensions' => $roomDimensions,
            'photo_planes' => $planes,
            'exterior' => [
                'kind' => $hasFacade ? 'photo_referenced' : 'conceptual',
                'has_facade_photo' => $hasFacade,
                'label' => $hasFacade
                    ? 'Fachada a partir da fotografia deste registro'
                    : 'Volume externo conceitual. Sem fotografia de fachada neste anúncio.',
            ],
            'subject' => [
                'title' => $subject['title'] ?? 'Imóvel',
                'property_type' => $subject['property_type'] ?? null,
                'area_m2' => $subject['area_m2'] ?? $brief['area_anunciada_m2'] ?? null,
                'bedrooms' => $subject['bedrooms'] ?? null,
                'bathrooms' => $subject['bathrooms'] ?? null,
                'parking_spaces' => $subject['parking_spaces'] ?? null,
            ],
            'photo_assets' => $photoAssets,
            'library' => ['sofa', 'bed', 'table', 'chair', 'sink', 'wardrobe', 'lamp', 'plant', 'window', 'door', 'partition'],
        ];
    }

    public function apply(array $graph, array $actions): array
    {
        foreach ($actions as $action) {
            $op = $action['op'] ?? '';
            if ($op === 'paint_kind') {
                foreach ($graph['surfaces'] as &$surface) {
                    if (($surface['kind'] ?? '') !== ($action['kind'] ?? '')) {
                        continue;
                    }
                    if (isset($action['room']) && ($surface['room'] ?? '') !== $action['room']) {
                        continue;
                    }
                    if (isset($action['color'])) {
                        $surface['color'] = $action['color'];
                    }
                    if (isset($action['material'])) {
                        $surface['material'] = $action['material'];
                    }
                }
                unset($surface);
            } elseif ($op === 'paint_surface') {
                foreach ($graph['surfaces'] as &$surface) {
                    if (($surface['id'] ?? '') !== ($action['id'] ?? '')) {
                        continue;
                    }
                    if (isset($action['color'])) {
                        $surface['color'] = $action['color'];
                    }
                    if (isset($action['material'])) {
                        $surface['material'] = $action['material'];
                    }
                }
                unset($surface);
            } elseif ($op === 'set_reconstruction_material') {
                $id = (string) ($action['id'] ?? '');
                $kind = str_starts_with($id, 'geometry-') ? substr($id, 9) : '';
                $allowed = ['wall_candidate', 'floor_candidate', 'ceiling_candidate', 'horizontal_surface', 'other_surface'];
                if (! in_array($kind, $allowed, true)) {
                    throw new InvalidArgumentException('A região da reconstrução não é editável.');
                }
                $expectedKind = $action['expected_kind'] ?? null;
                if ($expectedKind !== null && ($expectedKind !== $kind || ! in_array($expectedKind, $allowed, true))) {
                    throw new InvalidArgumentException('O tipo solicitado não corresponde à região selecionada.');
                }
                $previous = $graph['reconstruction_overrides'][$id] ?? null;
                $next = array_filter([
                    'color' => $action['color'] ?? null,
                    'material' => $action['material'] ?? null,
                    'source' => 'authorized_editor_action',
                ], fn ($value) => $value !== null);
                $graph['reconstruction_history'][] = ['id' => $id, 'previous' => $previous, 'next' => $next];
                $graph['reconstruction_history'] = array_slice($graph['reconstruction_history'], -20);
                $graph['reconstruction_redo'] = [];
                $graph['reconstruction_overrides'][$id] = $next;
            } elseif ($op === 'undo_reconstruction') {
                $history = $graph['reconstruction_history'] ?? [];
                $last = array_pop($history);
                if (is_array($last) && str_starts_with((string) ($last['id'] ?? ''), 'geometry-')) {
                    if (is_array($last['previous'] ?? null)) {
                        $graph['reconstruction_overrides'][$last['id']] = $last['previous'];
                    } else {
                        unset($graph['reconstruction_overrides'][$last['id']]);
                    }
                }
                $graph['reconstruction_history'] = $history;
                if (is_array($last)) {
                    $graph['reconstruction_redo'][] = $last;
                    $graph['reconstruction_redo'] = array_slice($graph['reconstruction_redo'], -20);
                }
            } elseif ($op === 'redo_reconstruction') {
                $redo = $graph['reconstruction_redo'] ?? [];
                $last = array_pop($redo);
                if (is_array($last) && str_starts_with((string) ($last['id'] ?? ''), 'geometry-') && is_array($last['next'] ?? null)) {
                    $graph['reconstruction_overrides'][$last['id']] = $last['next'];
                    $graph['reconstruction_history'][] = $last;
                    $graph['reconstruction_history'] = array_slice($graph['reconstruction_history'], -20);
                }
                $graph['reconstruction_redo'] = $redo;
            } elseif ($op === 'add_object') {
                $kind = $action['kind'] ?? 'chair';
                $room = $action['room'] ?? ($graph['active_room'] ?? 'living');
                $id = $kind.'-'.substr(md5(json_encode($graph['objects']).microtime(true)), 0, 6);
                $slot = count($graph['objects'] ?? []);
                $graph['objects'][] = $this->makeObject($id, $kind, $room, 'editor', $slot);
            } elseif ($op === 'remove_object') {
                $graph['objects'] = array_values(array_filter($graph['objects'], fn (array $item) => ($item['id'] ?? '') !== ($action['id'] ?? '')));
            } elseif ($op === 'duplicate') {
                foreach ($graph['objects'] as $object) {
                    if (($object['id'] ?? '') !== ($action['id'] ?? '')) {
                        continue;
                    }
                    $copy = $object;
                    $copy['id'] = ($object['kind'] ?? 'prop').'-'.substr(md5($object['id'].microtime(true)), 0, 6);
                    $copy['origin'] = 'editor';
                    $copy['position'] = [
                        ($object['position'][0] ?? 0) + 0.45,
                        $object['position'][1] ?? 0,
                        ($object['position'][2] ?? 0) + 0.2,
                    ];
                    $graph['objects'][] = $copy;
                    break;
                }
            } elseif ($op === 'set_object_color') {
                foreach ($graph['objects'] as &$object) {
                    if (($object['id'] ?? '') === ($action['id'] ?? '')) {
                        $object['color'] = $action['color'] ?? $object['color'];
                    }
                }
                unset($object);
            } elseif ($op === 'transform') {
                foreach ($graph['objects'] as &$object) {
                    if (($object['id'] ?? '') !== ($action['id'] ?? '')) {
                        continue;
                    }
                    foreach (['position', 'rotation', 'scale'] as $field) {
                        if (array_key_exists($field, $action)) {
                            $object[$field] = $action[$field];
                        }
                    }
                }
                unset($object);
            } elseif ($op === 'hide') {
                foreach ($graph['objects'] as &$object) {
                    if (($object['id'] ?? '') === ($action['id'] ?? '')) {
                        $object['hidden'] = (bool) ($action['hidden'] ?? true);
                    }
                }
                unset($object);
            } elseif ($op === 'upsert_object') {
                $object = $action['object'] ?? null;
                if (is_array($object) && isset($object['id'])) {
                    $graph['objects'] = array_values(array_filter($graph['objects'] ?? [], fn (array $item) => ($item['id'] ?? '') !== $object['id']));
                    $graph['objects'][] = $object;
                }
            } elseif ($op === 'set_active_room') {
                $graph['active_room'] = $action['room'] ?? $graph['active_room'];
            } elseif ($op === 'set_lighting') {
                $graph['lighting'] = $this->lightingPreset((string) ($action['preset'] ?? 'daylight'));
            } elseif ($op === 'set_room_dimensions') {
                $room = (string) ($action['room'] ?? $graph['active_room'] ?? 'living');
                $dimensions = is_array($action['dimensions'] ?? null) ? $action['dimensions'] : [];
                $graph['room_dimensions'][$room] = [
                    'width' => max(1.8, min(20, (float) ($dimensions['width'] ?? 5.2))),
                    'depth' => max(1.8, min(20, (float) ($dimensions['depth'] ?? 4.2))),
                    'height' => max(2, min(8, (float) ($dimensions['height'] ?? 2.65))),
                    'source' => 'user',
                ];
            }
        }

        return $graph;
    }

    private function makeObject(string $id, string $kind, string $room, string $origin, int $slot, array $evidence = []): array
    {
        $col = $slot % 3;
        $row = intdiv($slot, 3);
        $box = $evidence['box_normalized'] ?? null;
        $position = [($col - 1) * 0.85, 0, 0.55 + $row * 0.75];
        $scale = 1.0;
        if (is_array($box) && count($box) === 4) {
            $centerX = ((float) $box[0] + (float) $box[2]) / 2;
            $centerY = ((float) $box[1] + (float) $box[3]) / 2;
            $boxWidth = max(0.0, (float) $box[2] - (float) $box[0]);
            $position = [round(($centerX - 0.5) * 3.2, 3), 0, round(0.35 + (1 - $centerY) * 1.6, 3)];
            $scale = round(max(0.65, min(1.5, 0.65 + $boxWidth * 1.5)), 3);
        }

        return array_filter([
            'id' => $id,
            'kind' => $kind,
            'room' => $room,
            'origin' => $origin,
            'position' => $position,
            'rotation' => 0,
            'scale' => $scale,
            'color' => $this->defaultColor($kind),
            'hidden' => false,
            'source_label' => $evidence['label'] ?? null,
            'detection_confidence' => $evidence['confidence'] ?? null,
            'bounding_box' => $evidence['box_xyxy'] ?? null,
            'bounding_box_normalized' => $box,
            'approximate_from_2d' => $box !== null,
            'provenance' => $evidence['provenance'] ?? null,
        ], fn ($value) => $value !== null);
    }

    private function objectKind(string $element): ?string
    {
        $normalized = mb_strtolower($element);

        return match (true) {
            str_contains($normalized, 'sofa') || str_contains($normalized, 'sofá') => 'sofa',
            str_contains($normalized, 'cama') => 'bed',
            str_contains($normalized, 'mesa') => 'table',
            str_contains($normalized, 'dining table') => 'table',
            str_contains($normalized, 'cadeira') => 'chair',
            str_contains($normalized, 'chair') => 'chair',
            str_contains($normalized, 'couch') => 'sofa',
            str_contains($normalized, 'potted plant') => 'plant',
            $normalized === 'plant' => 'plant',
            $normalized === 'bed' => 'bed',
            $normalized === 'sink' => 'sink',
            str_contains($normalized, 'pia') => 'sink',
            str_contains($normalized, 'guarda') || str_contains($normalized, 'armario') || str_contains($normalized, 'armário') => 'wardrobe',
            str_contains($normalized, 'janela') => 'window',
            str_contains($normalized, 'porta') => 'door',
            default => null,
        };
    }

    private function floorMaterial(array $room): string
    {
        $haystack = mb_strtolower(implode(' ', $room['elementos_visiveis'] ?? []));

        return match (true) {
            str_contains($haystack, 'porcelanato') => 'porcelain',
            str_contains($haystack, 'madeira') => 'wood',
            str_contains($haystack, 'marmore') || str_contains($haystack, 'mármore') => 'marble',
            default => 'ceramic',
        };
    }

    private function defaultColor(string $kind): string
    {
        return match ($kind) {
            'sofa' => '#6b5e55',
            'bed' => '#d6c7b5',
            'table' => '#8b7355',
            'chair' => '#7c6a58',
            'sink' => '#d9d6d0',
            'wardrobe' => '#5c534a',
            'window' => '#9ec5d8',
            'door' => '#8a6244',
            'lamp' => '#e7d9a7',
            'plant' => '#4f7a4a',
            'partition' => '#d9cbb8',
            default => '#78716c',
        };
    }

    private function lightingPreset(string $preset): array
    {
        return match ($preset) {
            'warm' => ['preset' => 'warm', 'ambient_intensity' => 0.62, 'directional_intensity' => 0.95, 'color' => '#ffd6a3'],
            'cool' => ['preset' => 'cool', 'ambient_intensity' => 0.76, 'directional_intensity' => 1.05, 'color' => '#dbeafe'],
            'soft' => ['preset' => 'soft', 'ambient_intensity' => 0.82, 'directional_intensity' => 0.58, 'color' => '#fff7ed'],
            'evening' => ['preset' => 'evening', 'ambient_intensity' => 0.36, 'directional_intensity' => 0.72, 'color' => '#f59e7a'],
            default => ['preset' => 'daylight', 'ambient_intensity' => 0.7, 'directional_intensity' => 1.15, 'color' => '#fff4e5'],
        };
    }
}
