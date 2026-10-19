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
        if (Str::contains($normalized, ['parede', 'paredes'])) {
            $color = $this->color($normalized) ?? '#d9cbb8';
            return $this->ok([['op' => 'paint_kind', 'kind' => 'wall', 'color' => $color, 'room' => $room]], 'Paredes pintadas na pré-visualização. Não altera a foto original.');
        }
        if (Str::contains($normalized, ['teto'])) {
            $color = $this->color($normalized) ?? '#f4f1ea';
            return $this->ok([['op' => 'paint_kind', 'kind' => 'ceiling', 'color' => $color, 'room' => $room]], 'Teto atualizado na simulação.');
        }
        if (Str::contains($normalized, ['piso', 'chao', 'chão', 'porcelanato', 'ceramica', 'cerâmica', 'madeira', 'marmore', 'mármore', 'laminado'])) {
            $material = $this->material($normalized);
            $color = $this->color($normalized) ?? $this->materialColor($material);
            return $this->ok([['op' => 'paint_kind', 'kind' => 'floor', 'color' => $color, 'material' => $material, 'room' => $room]], 'Piso da simulação atualizado. Acabamento real do imóvel não muda.');
        }
        if (Str::contains($normalized, ['retire', 'remover', 'remova', 'tire esse', 'tira esse'])) {
            if (! $selectedId) {
                return $this->fail('Selecione o móvel na cena antes de pedir para removê-lo.');
            }
            return $this->ok([['op' => 'remove_object', 'id' => $selectedId]], 'Objeto removido da simulação.');
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
                ['op' => 'paint_kind', 'kind' => 'wall', 'color' => '#ece8e1'],
                ['op' => 'paint_kind', 'kind' => 'floor', 'color' => '#d7d2c8', 'material' => 'porcelain'],
            ], 'Preset minimalista aplicado só na simulação.');
        }
        if (Str::contains($normalized, ['luxo', 'luxuosa', 'luxuoso'])) {
            return $this->ok([
                ['op' => 'paint_kind', 'kind' => 'wall', 'color' => '#e7dcc8'],
                ['op' => 'paint_kind', 'kind' => 'floor', 'color' => '#cfc6b8', 'material' => 'marble'],
            ], 'Preset de luxo aplicado só na simulação.');
        }
        if (Str::contains($normalized, ['restaurar', 'original', 'desfazer tudo'])) {
            return $this->ok([['op' => 'restore_original']], 'Cena restaurada para a versão gerada das fotos/ficha.');
        }
        if (Str::contains($normalized, ['moderno', 'moderna'])) {
            return $this->ok([
                ['op' => 'paint_kind', 'kind' => 'wall', 'color' => '#e4e0d8'],
                ['op' => 'paint_kind', 'kind' => 'floor', 'color' => '#8b7355', 'material' => 'wood'],
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

    private function material(string $normalized): string
    {
        return match (true) {
            Str::contains($normalized, ['porcelanato']) => 'porcelain',
            Str::contains($normalized, ['madeira']) => 'wood',
            Str::contains($normalized, ['laminado']) => 'laminate',
            Str::contains($normalized, ['marmore', 'mármore']) => 'marble',
            default => 'ceramic',
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

    private function ok(array $actions, string $message): array
    {
        return ['ok' => true, 'actions' => $actions, 'message' => $message];
    }

    private function fail(string $message): array
    {
        return ['ok' => false, 'actions' => [], 'message' => $message];
    }
}
