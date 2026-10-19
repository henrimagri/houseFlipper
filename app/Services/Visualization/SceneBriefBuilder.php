<?php

namespace App\Services\Visualization;

class SceneBriefBuilder
{
    public function build(array $analyses, array $subject, array $photoAssets): array
    {
        $grouped = [];
        foreach ($analyses as $row) {
            $room = $row['room_guess'] ?: 'unknown';
            $grouped[$room][] = $row;
        }
        $ambientes = [];
        foreach ($grouped as $room => $rows) {
            if ($room === 'unknown') {
                continue;
            }
            $elements = [];
            $palette = [];
            $ids = [];
            foreach ($rows as $row) {
                foreach ($row['elements'] ?? [] as $element) {
                    $label = is_array($element) ? ($element['label'] ?? null) : $element;
                    if ($label) {
                        $elements[] = $label;
                    }
                }
                if (! empty($row['palette'])) {
                    $palette = $row['palette'];
                }
                if (! empty($row['asset_id'])) {
                    $ids[] = $row['asset_id'];
                } elseif (! empty($row['source_photo_id'])) {
                    foreach ($photoAssets as $asset) {
                        if ((int) ($asset['source_photo_id'] ?? 0) === (int) $row['source_photo_id']) {
                            $ids[] = $asset['id'];
                        }
                    }
                }
            }
            $confidence = (int) collect($rows)->avg('confidence');
            $ambientes[] = [
                'nome' => $room,
                'rotulo' => $this->label($room),
                'imagens_referencia' => array_values(array_unique($ids)),
                'elementos_visiveis' => array_values(array_unique($elements)),
                'dimensoes_confirmadas' => null,
                'confianca_visual' => round($confidence / 100, 2),
                'palette' => $palette,
                'desconhecido' => $ids === [] ? ['sem_foto_deste_ambiente_no_armazenamento'] : [],
            ];
        }
        if ($ambientes === []) {
            $ambientes[] = [
                'nome' => 'living',
                'rotulo' => 'Sala',
                'imagens_referencia' => array_column($photoAssets, 'id'),
                'elementos_visiveis' => [],
                'dimensoes_confirmadas' => null,
                'confianca_visual' => 0.15,
                'palette' => [],
                'desconhecido' => ['ambiente_nao_identificado_nas_fotografias'],
            ];
        }

        return [
            'tipo' => $subject['property_type'] ?? null,
            'area_anunciada_m2' => $subject['area_m2'] ?? null,
            'fachada_disponivel' => collect($analyses)->contains(fn (array $row) => ($row['room_guess'] ?? '') === 'facade'),
            'planta_disponivel' => collect($photoAssets)->contains(fn (array $asset) => ($asset['kind'] ?? '') === 'floorplan'),
            'ambientes' => $ambientes,
            'limitations' => [
                'A confiança é estimativa do classificador, não prova de exatidão.',
                'Nenhuma medida métrica foi extraída da fotografia.',
                'Uma foto de um cômodo não gera a planta do imóvel inteiro.',
            ],
        ];
    }

    private function label(string $room): string
    {
        return match ($room) {
            'living' => 'Sala',
            'kitchen' => 'Cozinha',
            'bedroom' => 'Quarto',
            'bathroom' => 'Banheiro',
            'balcony' => 'Varanda',
            'garage' => 'Garagem',
            'facade' => 'Fachada',
            'outdoor' => 'Área externa',
            default => $room,
        };
    }
}
