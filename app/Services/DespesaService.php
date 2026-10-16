<?php

namespace App\Services;

use App\Models\Despesa;
use Illuminate\Support\Facades\DB;

class DespesaService
{
    public function __construct(private readonly ProjetoService $projetoService) {}

    public function listar(array $filtros = [])
    {
        $query = Despesa::query()->ativos()->with(['categoria', 'propriedade', 'projeto']);

        foreach (['propriedade_id', 'projeto_id', 'etapa_id', 'tarefa_id', 'categoria_despesa_id', 'status_pagamento'] as $f) {
            if (!empty($filtros[$f])) {
                $query->where($f, $filtros[$f]);
            }
        }

        if (!empty($filtros['data_inicio'])) {
            $query->where('data_despesa', '>=', $filtros['data_inicio']);
        }
        if (!empty($filtros['data_fim'])) {
            $query->where('data_despesa', '<=', $filtros['data_fim']);
        }

        return $query->orderByDesc('data_despesa')->paginate($filtros['per_page'] ?? 25);
    }

    public function obter(int $id): ?Despesa
    {
        return Despesa::ativos()->with(['categoria', 'propriedade', 'projeto'])->find($id);
    }

    public function criar(array $dados): Despesa
    {
        return DB::transaction(function () use ($dados) {
            $despesa = Despesa::create($dados);
            $this->recalcular($despesa);
            return $despesa->load(['categoria', 'propriedade', 'projeto']);
        });
    }

    public function atualizar(Despesa $despesa, array $dados): Despesa
    {
        return DB::transaction(function () use ($despesa, $dados) {
            $projetoAntigo = $despesa->projeto_id;
            $despesa->update($dados);
            $this->recalcular($despesa, $projetoAntigo);
            return $despesa->fresh(['categoria', 'propriedade', 'projeto']);
        });
    }

    public function excluir(Despesa $despesa): void
    {
        DB::transaction(function () use ($despesa) {
            $despesa->update(['deletado_flg' => true]);
            $this->recalcular($despesa);
        });
    }

    /**
     * Após qualquer mutação dispara recálculo de custo_real da etapa, projeto e ROI.
     * Trata o caso de a despesa ter migrado de projeto.
     */
    private function recalcular(Despesa $despesa, ?int $projetoAntigo = null): void
    {
        $ids = array_unique(array_filter([$despesa->projeto_id, $projetoAntigo]));
        foreach ($ids as $projetoId) {
            $this->projetoService->recalcularCustos((int) $projetoId);
        }
    }
}
