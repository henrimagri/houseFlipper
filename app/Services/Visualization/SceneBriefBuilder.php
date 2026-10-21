<?php

namespace App\Services\Visualization;

class SceneBriefBuilder
{
    public function build(array $analyses, array $subject, array $photoAssets): array
    {
        $grouped = [];
        foreach ($analyses as $row) {
            $room = $row['room_guess'] ?: 'unclassified';
            $grouped[$room][] = $row;
        }
        $ambientes = [];
        foreach ($grouped as $room => $rows) {
            $elements = [];
            $structuredElements = [];
            $palette = [];
            $ids = [];
            $provenance = [];
            foreach ($rows as $row) {
                foreach ($row['elements'] ?? [] as $element) {
                    $label = is_array($element) ? ($element['label'] ?? null) : $element;
                    if ($label) {
                        $elements[] = $label;
                    }
                    if (is_array($element)) {
                        $structuredElements[] = $element;
                    }
                }
                if (! empty($row['room_provenance'])) {
                    $provenance[] = $row['room_provenance'];
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
            $roomConfidences = collect($rows)
                ->map(fn (array $row) => $row['room_confidence'] ?? (($row['room_guess'] ?? null) ? ($row['confidence'] ?? null) : null))
                ->filter(fn ($value) => $value !== null);
            $detectionConfidences = collect($rows)->pluck('detection_confidence')->filter(fn ($value) => $value !== null);
            $ambientes[] = [
                'nome' => $room,
                'rotulo' => $this->label($room),
                'imagens_referencia' => array_values(array_unique($ids)),
                'elementos_visiveis' => array_values(array_unique($elements)),
                'elementos_estruturados' => $this->uniqueElements($structuredElements),
                'dimensoes_confirmadas' => null,
                'confianca_ambiente' => $roomConfidences->isEmpty() ? null : round(((float) $roomConfidences->avg()) / 100, 2),
                'confianca_deteccao' => $detectionConfidences->isEmpty() ? null : round(((float) $detectionConfidences->avg()) / 100, 2),
                'confianca_visual' => $roomConfidences->isEmpty() ? null : round(((float) $roomConfidences->avg()) / 100, 2),
                'classificacao_ambiente' => $room === 'unclassified' ? 'unclassified' : 'classified',
                'proveniencia_ambiente' => $provenance,
                'palette' => $palette,
                'desconhecido' => array_values(array_filter([
                    $ids === [] ? 'sem_foto_deste_ambiente_no_armazenamento' : null,
                    $room === 'unclassified' ? 'ambiente_nao_classificado_pelas_evidencias' : null,
                ])),
            ];
        }
        if ($ambientes === []) {
            $ambientes[] = [
                'nome' => 'unclassified',
                'rotulo' => 'Ambiente não classificado',
                'imagens_referencia' => array_column($photoAssets, 'id'),
                'elementos_visiveis' => [],
                'elementos_estruturados' => [],
                'dimensoes_confirmadas' => null,
                'confianca_ambiente' => null,
                'confianca_deteccao' => null,
                'confianca_visual' => null,
                'classificacao_ambiente' => 'unclassified',
                'proveniencia_ambiente' => [],
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
                'A identificação de ambiente usa regras auditáveis sobre objetos detectados ou heurísticas declaradas; não é um classificador neural de cômodos.',
                'Confiança de detecção de objetos e confiança da inferência de ambiente são medidas distintas.',
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
            'dining' => 'Sala de jantar',
            'unclassified' => 'Ambiente não classificado',
            default => $room,
        };
    }

    private function uniqueElements(array $elements): array
    {
        return collect($elements)
            ->unique(fn (array $element) => implode('|', [
                $element['label'] ?? '',
                json_encode($element['box_xyxy'] ?? []),
                $element['model'] ?? '',
            ]))
            ->values()
            ->all();
    }
}
