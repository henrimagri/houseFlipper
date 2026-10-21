<?php

namespace App\Services\Visualization;

use Illuminate\Support\Str;

class SceneCommandInterpreter
{
    public function interpret(string $message, array $graph, ?string $selectedId = null): array
    {
        $text = Str::of($message)->replaceMatches('/[\x00-\x1F\x7F]+/u', ' ')->squish()->limit(500, '')->toString();
        $normalized = Str::of($text)->lower()->ascii()->toString();
        if ($text === '' || mb_strlen($text) < 3) {
            return $this->fail('Escreva o que deseja alterar neste ambiente.');
        }
        if (Str::contains($normalized, ['delete from', 'drop table', 'rm -', 'http://', 'https://', '<script'])) {
            return $this->fail('Este campo só altera a cena 3D. Comandos administrativos não são executados.');
        }

        $room = $this->currentRoom($graph);
        if (Str::contains($normalized, ['estimativa de custo', 'estimar custo', 'quanto custa', 'orcamento', 'orçamento'])) {
            $dimensions = $graph['room_dimensions'][$room] ?? [];
            $width = (float) ($dimensions['width'] ?? 0);
            $depth = (float) ($dimensions['depth'] ?? 0);
            if (($dimensions['source'] ?? null) !== 'user' || ! is_finite($width) || ! is_finite($depth) || $width <= 0 || $depth <= 0) {
                return $this->confirm('Informe as dimensões do ambiente no editor antes de calcular custos. As medidas aproximadas da cena não são medições do imóvel.');
            }
            if (! preg_match('/r\$\s*(\d+(?:\.\d{3})*(?:,\d{1,2})?)\s*(?:por|\/)\s*(?:m2|m\^2|metro quadrado)\b/u', $normalized, $match)) {
                return $this->confirm('Informe o custo unitário e seu escopo, por exemplo: R$ 500,00 por m². Não há preço de mercado verificado para estimar esta reforma.');
            }
            $unitPrice = (float) str_replace(',', '.', str_replace('.', '', $match[1]));
            $area = round($width * $depth, 2);
            $total = $area * $unitPrice;
            if (! is_finite($total) || $unitPrice <= 0) {
                return $this->confirm('Informe um custo por m² positivo e válido para realizar o cálculo.');
            }
            $brief = sprintf('Cálculo com premissas informadas: %s m² de piso × R$ %s/m² informado por você = R$ %s. Não é cotação de mercado; confirme o escopo do valor unitário, materiais, mão de obra e condições locais.', number_format($area, 2, ',', '.'), number_format($unitPrice, 2, ',', '.'), number_format($total, 2, ',', '.'));

            return $this->ok([['op' => 'set_project_brief', 'category' => 'cost_estimate', 'brief' => $brief]], $brief);
        }
        if (Str::contains($normalized, ['texto de apresentacao', 'texto de apresentação', 'apresentar o projeto'])) {
            $brief = 'Proposta visual de reforma com materiais, mobiliário e iluminação configurados nesta versão. As imagens são simulações e os custos dependem de validação técnica e comercial.';

            return $this->ok([['op' => 'set_project_brief', 'category' => 'presentation', 'brief' => $brief]], 'Texto de apresentação criado e salvo com o projeto.');
        }
        if (Str::contains($normalized, ['proposta de reforma', 'crie uma proposta', 'criar uma proposta'])) {
            $brief = 'Proposta preliminar: revisar medidas, definir acabamentos, validar mobiliário e confirmar orçamento antes da execução. A cena atual permanece uma simulação editável.';

            return $this->ok([['op' => 'set_project_brief', 'category' => 'renovation_proposal', 'brief' => $brief]], 'Proposta preliminar salva no projeto sem tratar a simulação como obra executiva.');
        }
        if (Str::contains($normalized, ['desfaca a ultima', 'desfaça a última', 'desfazer a ultima', 'desfazer a última'])) {
            if (! $selectedId || ! str_starts_with($selectedId, 'geometry-')) {
                return empty($graph['editor_history'])
                    ? $this->fail('Não há alteração da cena editável para desfazer.')
                    : $this->ok([['op' => 'undo_scene']], 'Última alteração da cena editável desfeita.');
            }
            if (empty($graph['reconstruction_history'])) {
                return $this->fail('Não há alteração da reconstrução para desfazer.');
            }

            return $this->ok([['op' => 'undo_reconstruction']], 'Última alteração da reconstrução desfeita.');
        }
        if (Str::contains($normalized, ['refaca a ultima', 'refaça a última', 'refazer a ultima', 'refazer a última'])) {
            if (! $selectedId || ! str_starts_with($selectedId, 'geometry-')) {
                return empty($graph['editor_redo'])
                    ? $this->fail('Não há alteração da cena editável para refazer.')
                    : $this->ok([['op' => 'redo_scene']], 'Última alteração da cena editável refeita.');
            }
            if (empty($graph['reconstruction_redo'])) {
                return $this->fail('Não há alteração da reconstrução para refazer.');
            }

            return $this->ok([['op' => 'redo_reconstruction']], 'Última alteração da reconstrução refeita.');
        }
        if (Str::contains($normalized, ['selecione', 'selecionar'])) {
            return $this->confirm('Selecione a superfície diretamente no modelo para confirmar o alvo. O comando não altera materiais enquanto a seleção não estiver confirmada.');
        }
        if ($selectedId && str_starts_with($selectedId, 'geometry-') && Str::contains($normalized, ['pinte', 'parede', 'piso', 'teto', 'material'])) {
            $expectedKind = $this->requestedSurfaceKind($normalized);
            $selectedKind = $this->selectedSurfaceKind($selectedId);
            if ($selectedKind === null) {
                return $this->fail('A região selecionada não possui uma classificação geométrica reconhecida. Selecione novamente no modelo.');
            }
            if ($expectedKind !== null && $selectedKind !== $expectedKind) {
                return $this->confirm(sprintf(
                    'A região selecionada é %s, mas o comando pede %s. Selecione a superfície correspondente ou confirme explicitamente a alteração na região atual.',
                    $this->surfaceKindLabel($selectedKind),
                    $this->surfaceKindLabel($expectedKind),
                ));
            }
            $color = $this->color($normalized);
            $material = $this->explicitMaterial($normalized);
            if ($material === null && Str::contains($normalized, ['material', 'troque', 'substitua'])) {
                return $this->confirm('Informe o material desejado para a região selecionada, por exemplo madeira ou porcelanato.');
            }
            $material ??= 'paint';
            if ($color === null && $material === 'paint') {
                return $this->confirm('Confirme a cor para a região destacada antes de alterar a reconstrução.');
            }
            $color ??= $this->materialColor($material);

            return $this->ok([[
                'op' => 'set_reconstruction_material', 'id' => $selectedId,
                'color' => $color, 'material' => $material, 'expected_kind' => $expectedKind,
            ]], 'Material aplicado somente à região selecionada da reconstrução.');
        }
        if (Str::contains($normalized, ['parede atras do sofa', 'parede atrás do sofá', 'parede do sofa', 'parede do sofá'])) {
            if (! $selectedId) {
                return $this->confirm('Selecione visualmente a parede atrás do sofá. A posição aproximada do móvel não comprova qual parede deve ser alterada.');
            }

            return $this->surfaceAction($graph, $room, $selectedId, 'wall', $this->color($normalized));
        }
        if (Str::contains($normalized, ['parede', 'paredes'])) {
            return $this->surfaceAction($graph, $room, $selectedId, 'wall', $this->color($normalized), null, Str::contains($normalized, ['todas as paredes', 'todas paredes']));
        }
        if (Str::contains($normalized, ['teto'])) {
            return $this->surfaceAction($graph, $room, $selectedId, 'ceiling', $this->color($normalized));
        }
        if (Str::contains($normalized, ['piso', 'chao', 'chão', 'porcelanato', 'ceramica', 'cerâmica', 'madeira', 'marmore', 'mármore', 'laminado'])) {
            $material = $this->explicitMaterial($normalized);
            $color = $this->color($normalized) ?? ($material ? $this->materialColor($material) : null);

            return $this->surfaceAction($graph, $room, $selectedId, 'floor', $color, $material);
        }
        if (Str::contains($normalized, ['retire', 'remover', 'remova', 'tire esse', 'tira esse'])) {
            if (! $selectedId) {
                return $this->fail('Selecione o móvel na cena antes de pedir para removê-lo.');
            }

            return $this->ok([['op' => 'remove_object', 'id' => $selectedId]], 'Objeto removido da simulação.');
        }
        if (Str::contains($normalized, ['substitua', 'substituir', 'troque o sofa', 'troque o sofá'])) {
            if (! $selectedId) {
                return $this->confirm('Selecione o móvel que será substituído e confirme o pedido para evitar trocar o elemento errado.');
            }
            $object = $this->selectedObject($graph, $selectedId);
            if ($object === null) {
                return $this->confirm('O móvel selecionado não existe mais. Selecione novamente antes de substituir.');
            }
            $replacement = Str::contains($normalized, ['cadeira']) ? 'chair' : (Str::contains($normalized, ['mesa']) ? 'table' : 'sofa');

            return $this->ok([
                ['op' => 'remove_object', 'id' => $selectedId],
                ['op' => 'add_object', 'kind' => $replacement, 'room' => $room],
            ], 'Móvel selecionado substituído no ambiente 3D.');
        }
        if (Str::contains($normalized, ['iluminacao', 'iluminação', 'luz '])) {
            $preset = match (true) {
                Str::contains($normalized, ['quente', 'aconchegante']) => 'warm',
                Str::contains($normalized, ['fria', 'frio']) => 'cool',
                Str::contains($normalized, ['suave', 'difusa']) => 'soft',
                Str::contains($normalized, ['entardecer', 'noturna', 'noite']) => 'evening',
                default => 'daylight',
            };

            return $this->ok([['op' => 'set_lighting', 'preset' => $preset]], 'Iluminação da simulação atualizada.');
        }
        if (Str::contains($normalized, ['gire', 'girar', 'rotacione', 'rotacionar', 'mova', 'mover', 'aumente', 'aumentar', 'diminua', 'diminuir'])) {
            $object = $this->selectedObject($graph, $selectedId);
            if ($object === null) {
                return $this->fail('Selecione um móvel na cena antes de mover, girar ou redimensionar.');
            }
            $position = $object['position'] ?? [0, 0, 0];
            $rotation = (float) ($object['rotation'] ?? 0);
            $scale = (float) ($object['scale'] ?? 1);
            if (Str::contains($normalized, ['gire', 'girar', 'rotacione', 'rotacionar'])) {
                $rotation += Str::contains($normalized, ['esquerda', 'anti-horario', 'anti horário']) ? -15 : 15;
            }
            if (Str::contains($normalized, ['mova', 'mover'])) {
                $position[0] = max(-2.1, min(2.1, (float) ($position[0] ?? 0) + (Str::contains($normalized, ['esquerda']) ? -0.25 : (Str::contains($normalized, ['direita']) ? 0.25 : 0))));
                $position[2] = max(-1.6, min(1.8, (float) ($position[2] ?? 0) + (Str::contains($normalized, ['frente']) ? -0.25 : (Str::contains($normalized, ['tras', 'trás', 'fundo']) ? 0.25 : 0))));
            }
            if (Str::contains($normalized, ['aumente', 'aumentar'])) {
                $scale = min(2.5, $scale + 0.1);
            } elseif (Str::contains($normalized, ['diminua', 'diminuir'])) {
                $scale = max(0.4, $scale - 0.1);
            }

            return $this->ok([['op' => 'transform', 'id' => $object['id'], 'position' => $position, 'rotation' => $rotation, 'scale' => $scale]], 'Objeto selecionado atualizado na simulação.');
        }
        if (Str::contains($normalized, ['sofa', 'sofá'])) {
            return $this->ok([['op' => 'add_object', 'kind' => 'sofa', 'room' => $this->currentRoom($graph)]], 'Sofá adicionado à simulação. Não veio da fotografia.');
        }
        if (Str::contains($normalized, ['cama'])) {
            return $this->ok([['op' => 'add_object', 'kind' => 'bed', 'room' => $this->currentRoom($graph)]], 'Cama adicionada à simulação.');
        }
        if (Str::contains($normalized, ['mesa'])) {
            return $this->ok([['op' => 'add_object', 'kind' => 'table', 'room' => $this->currentRoom($graph)]], 'Mesa adicionada à simulação.');
        }
        if (Str::contains($normalized, ['cadeira'])) {
            return $this->ok([['op' => 'add_object', 'kind' => 'chair', 'room' => $this->currentRoom($graph)]], 'Cadeira adicionada à simulação.');
        }
        if (Str::contains($normalized, ['minimalista'])) {
            return $this->ok([
                ['op' => 'paint_kind', 'kind' => 'wall', 'color' => '#ece8e1', 'room' => $room],
                ['op' => 'paint_kind', 'kind' => 'floor', 'color' => '#d7d2c8', 'material' => 'porcelain', 'room' => $room],
            ], 'Preset minimalista aplicado só na simulação.');
        }
        if (Str::contains($normalized, ['escandinavo', 'escandinava'])) {
            return $this->ok([
                ['op' => 'paint_kind', 'kind' => 'wall', 'color' => '#f2efe8', 'room' => $room],
                ['op' => 'paint_kind', 'kind' => 'floor', 'color' => '#c7a77a', 'material' => 'wood', 'room' => $room],
                ['op' => 'set_lighting', 'preset' => 'soft'],
            ], 'Estilo escandinavo aplicado à simulação com paleta clara, madeira e luz suave.');
        }
        if (Str::contains($normalized, ['luxo', 'luxuosa', 'luxuoso', 'alto padrao', 'alto padrão'])) {
            return $this->ok([
                ['op' => 'paint_kind', 'kind' => 'wall', 'color' => '#e7dcc8', 'room' => $room],
                ['op' => 'paint_kind', 'kind' => 'floor', 'color' => '#cfc6b8', 'material' => 'marble', 'room' => $room],
            ], 'Preset de luxo aplicado só na simulação.');
        }
        if (Str::contains($normalized, ['restaurar', 'original', 'desfazer tudo'])) {
            return $this->ok([['op' => 'restore_original']], 'Cena restaurada para a versão gerada das fotos/ficha.');
        }
        if (Str::contains($normalized, ['moderno', 'moderna'])) {
            return $this->ok([
                ['op' => 'paint_kind', 'kind' => 'wall', 'color' => '#e4e0d8', 'room' => $room],
                ['op' => 'paint_kind', 'kind' => 'floor', 'color' => '#8b7355', 'material' => 'wood', 'room' => $room],
            ], 'Preset moderno aplicado só na simulação.');
        }

        return $this->fail('Não executei a cena: o comando não mapeia para pintura, piso, móvel, preset ou restaurar.');
    }

    private function color(string $normalized): ?string
    {
        return match (true) {
            Str::contains($normalized, ['branco', 'branca', 'brancas', 'brancos']) => '#f4f1ea',
            Str::contains($normalized, ['bege', 'beges']) => '#d9cbb8',
            Str::contains($normalized, ['azul', 'azuis']) => '#9bb7d4',
            Str::contains($normalized, ['cinza', 'cinzas']) => '#c5c7cc',
            Str::contains($normalized, ['preto', 'preta', 'pretas', 'pretos']) => '#2f3338',
            Str::contains($normalized, ['verde', 'verdes']) => '#9aaa8c',
            Str::contains($normalized, ['amarelo', 'amarela', 'amarelos', 'amarelas']) => '#e3d39a',
            default => null,
        };
    }

    private function explicitMaterial(string $normalized): ?string
    {
        return match (true) {
            Str::contains($normalized, ['porcelanato']) => 'porcelain',
            Str::contains($normalized, ['madeira']) => 'wood',
            Str::contains($normalized, ['laminado']) => 'laminate',
            Str::contains($normalized, ['marmore', 'mármore']) => 'marble',
            Str::contains($normalized, ['ceramica']) => 'ceramic',
            default => null,
        };
    }

    private function materialColor(string $material): string
    {
        return match ($material) {
            'porcelain' => '#e8e6e1',
            'wood' => '#8b7355',
            'laminate' => '#b08968',
            'marble' => '#d8d2c8',
            default => '#cbb79a',
        };
    }

    private function currentRoom(array $graph): string
    {
        return $graph['active_room'] ?? $graph['rooms'][0]['key'] ?? 'living';
    }

    private function requestedSurfaceKind(string $normalized): ?string
    {
        return match (true) {
            Str::contains($normalized, ['parede', 'paredes']) => 'wall_candidate',
            Str::contains($normalized, ['piso', 'chao', 'chão']) => 'floor_candidate',
            Str::contains($normalized, ['teto']) => 'ceiling_candidate',
            default => null,
        };
    }

    private function selectedSurfaceKind(string $selectedId): ?string
    {
        $kind = str_starts_with($selectedId, 'geometry-') ? substr($selectedId, 9) : '';

        return in_array($kind, ['wall_candidate', 'floor_candidate', 'ceiling_candidate', 'horizontal_surface', 'other_surface'], true)
            ? $kind
            : null;
    }

    private function surfaceKindLabel(string $kind): string
    {
        return match ($kind) {
            'wall_candidate' => 'uma parede candidata',
            'floor_candidate' => 'um piso candidato',
            'ceiling_candidate' => 'um teto candidato',
            'horizontal_surface' => 'uma superfície horizontal',
            default => 'outra superfície',
        };
    }

    private function selectedObject(array $graph, ?string $selectedId): ?array
    {
        if ($selectedId === null || $selectedId === '') {
            return null;
        }
        foreach ($graph['objects'] ?? [] as $object) {
            if (($object['id'] ?? null) === $selectedId) {
                return $object;
            }
        }

        return null;
    }

    private function surfaceAction(array $graph, string $room, ?string $selectedId, string $kind, ?string $color, ?string $material = null, bool $all = false): array
    {
        $surfaces = array_values(array_filter($graph['surfaces'] ?? [], fn (array $surface) => ($surface['room'] ?? null) === $room && ($surface['kind'] ?? null) === $kind));
        if ($color === null) {
            return $this->confirm('Informe a cor ou o material desejado antes de alterar a superfície.');
        }
        if ($surfaces === []) {
            return $this->confirm('Não encontrei essa superfície no ambiente ativo. Selecione o ambiente e a superfície no editor.');
        }
        if ($all) {
            return $this->ok([['op' => 'paint_kind', 'kind' => $kind, 'color' => $color, 'room' => $room]], 'Paredes do ambiente ativo pintadas na simulação.');
        }
        if (! $selectedId && $kind !== 'wall' && count($surfaces) === 1) {
            $selectedId = $surfaces[0]['id'];
        }
        $surface = collect($surfaces)->firstWhere('id', $selectedId);
        if (! $selectedId || $surface === null) {
            return $this->confirm('Selecione a superfície correspondente no ambiente ativo. Para alterar todas as paredes, peça explicitamente todas as paredes.');
        }
        $action = ['op' => 'paint_surface', 'id' => $surface['id'], 'color' => $color];
        if ($material !== null) {
            $action['material'] = $material;
        }

        return $this->ok([$action], 'Somente a superfície selecionada foi alterada na simulação.');
    }

    private function ok(array $actions, string $message): array
    {
        return ['ok' => true, 'actions' => $actions, 'message' => $message];
    }

    private function fail(string $message): array
    {
        return ['ok' => false, 'actions' => [], 'message' => $message];
    }

    private function confirm(string $message): array
    {
        return ['ok' => false, 'requires_confirmation' => true, 'actions' => [], 'message' => $message];
    }
}
