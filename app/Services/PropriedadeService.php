<?php

namespace App\Services;

use App\Models\Propriedade;
use Illuminate\Support\Facades\DB;

class PropriedadeService
{
    public function listar(array $filtros = [])
    {
        $query = Propriedade::query()
            ->ativos()
            ->with('endereco');

        if (!empty($filtros['status'])) {
            $query->where('status', $filtros['status']);
        }

        if (!empty($filtros['busca'])) {
            $query->where('apelido', 'like', '%' . $filtros['busca'] . '%');
        }

        return $query->orderByDesc('data_inclusao')->paginate($filtros['per_page'] ?? 15);
    }

    public function obter(int $id): ?Propriedade
    {
        return Propriedade::ativos()->with('endereco')->find($id);
    }

    public function criar(array $dados): Propriedade
    {
        return DB::transaction(function () use ($dados) {
            $endereco = $dados['endereco'] ?? null;
            unset($dados['endereco']);

            $propriedade = Propriedade::create($dados);

            if ($endereco) {
                $propriedade->endereco()->create($endereco);
            }

            return $propriedade->load('endereco');
        });
    }

    public function atualizar(Propriedade $propriedade, array $dados): Propriedade
    {
        return DB::transaction(function () use ($propriedade, $dados) {
            $endereco = $dados['endereco'] ?? null;
            unset($dados['endereco']);

            $propriedade->update($dados);

            if ($endereco) {
                if ($propriedade->endereco) {
                    $propriedade->endereco->update($endereco);
                } else {
                    $propriedade->endereco()->create($endereco);
                }
            }

            return $propriedade->fresh('endereco');
        });
    }

    public function excluir(Propriedade $propriedade): void
    {
        $propriedade->update(['deletado_flg' => true]);
    }
}
