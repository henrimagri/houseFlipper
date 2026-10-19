<?php

namespace App\Services\Visualization;

class SceneGraphFactory
{
    public function fromBrief(array $brief, array $photoAssets, array $subject): array
    {
        $rooms = $brief['ambientes'] ?? [];
        $objects = [];
        $surfaces = [];
        $planes = [];
        foreach ($rooms as $room) {
            $key = (string) ($room['nome'] ?? 'living');
            $palette = $room['palette'][0]['hex'] ?? '#d9cbb8';
            $floor = $room['palette'][1]['hex'] ?? '#cbb79a';
            $surfaces[] = ['id' => $key.'-wall', 'kind' => 'wall', 'room' => $key, 'color' => $palette, 'material' => 'paint'];
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
            foreach ($room['elementos_visiveis'] ?? [] as $element) {
                $kind = $this->objectKind((string) $element);
                if ($kind === null) {
                    continue;
                }
                $objects[] = $this->makeObject($key.'-'.$kind.'-'.$slot, $kind, $key, 'evidence', $slot);
                $slot++;
            }
        }
        $hasPhotos = $planes !== [];
        $hasFacade = (bool) ($brief['fachada_disponivel'] ?? false);
        $fidelity = $hasPhotos ? 'photo_referenced' : ((float) ($brief['area_anunciada_m2'] ?? 0) > 0 ? 'parametric' : 'illustrative');

        return [
            'version' => 1,
            'fidelity' => $fidelity,
            'active_room' => $rooms[0]['nome'] ?? 'living',
            'disclaimer' => $hasPhotos
                ? 'Cores e plano fotográfico vêm das imagens deste registro. A geometria é aproximada. Não é fotogrametria nem planta fiel.'
                : 'Sem fotografia utilizável neste registro. Cena conceitual da ficha, identificada como simulação.',
            'rooms' => array_map(fn (array $room) => [
                'key' => $room['nome'] ?? 'living',
                'label' => $room['rotulo'] ?? $room['nome'] ?? 'Ambiente',
                'confidence' => $room['confianca_visual'] ?? 0,
                'unknowns' => $room['desconhecido'] ?? [],
            ], $rooms),
            'surfaces' => $surfaces,
            'objects' => $objects,
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
            'library' => ['sofa', 'bed', 'table', 'chair', 'sink', 'wardrobe', 'lamp', 'plant', 'partition'],
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
            }
        }

        return $graph;
    }

    private function makeObject(string $id, string $kind, string $room, string $origin, int $slot): array
    {
        $col = $slot % 3;
        $row = intdiv($slot, 3);

        return [
            'id' => $id,
            'kind' => $kind,
            'room' => $room,
            'origin' => $origin,
            'position' => [($col - 1) * 0.85, 0, 0.55 + $row * 0.75],
            'rotation' => 0,
            'scale' => 1,
            'color' => $this->defaultColor($kind),
            'hidden' => false,
        ];
    }

    private function objectKind(string $element): ?string
    {
        $normalized = mb_strtolower($element);

        return match (true) {
            str_contains($normalized, 'sofa') || str_contains($normalized, 'sofá') => 'sofa',
            str_contains($normalized, 'cama') => 'bed',
            str_contains($normalized, 'mesa') => 'table',
            str_contains($normalized, 'cadeira') => 'chair',
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
}
