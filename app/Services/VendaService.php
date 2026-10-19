<?php

namespace App\Services;

use App\Models\Propriedade;
use App\Models\Venda;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendaService
{
    public function listar(array $filtros = [])
    {
        $query = Venda::query()->ativos()->with('propriedade');

        if (! empty($filtros['propriedade_id'])) {
            $query->where('propriedade_id', $filtros['propriedade_id']);
        }
        if (! empty($filtros['status'])) {
            $query->where('status', $filtros['status']);
        }

        return $query->orderByDesc('data_inclusao')->paginate($filtros['per_page'] ?? 15);
    }

    public function obter(int $id): ?Venda
    {
        return Venda::ativos()->with('propriedade')->find($id);
    }

    public function criar(array $dados): Venda
    {
        return DB::transaction(function () use ($dados) {
            $propertyId = (int) $dados['propriedade_id'];
            Propriedade::ativos()->lockForUpdate()->findOrFail($propertyId);
            $this->assertFinalSaleAvailable($propertyId, null, (string) ($dados['status'] ?? 'proposta'));
            $venda = Venda::create($dados);

            $this->sincronizarStatusPropriedade($venda->propriedade_id);

            return $venda->load('propriedade');
        });
    }

    public function atualizar(Venda $venda, array $dados): Venda
    {
        return DB::transaction(function () use ($venda, $dados) {
            $propriedadeAnterior = $venda->propriedade_id;
            $newPropertyId = (int) ($dados['propriedade_id'] ?? $venda->propriedade_id);
            $propertyIds = array_values(array_unique([(int) $propriedadeAnterior, $newPropertyId]));
            sort($propertyIds);
            Propriedade::ativos()->whereIn('id', $propertyIds)->orderBy('id')->lockForUpdate()->get();
            $this->assertFinalSaleAvailable($newPropertyId, $venda->id, (string) ($dados['status'] ?? $venda->status));
            $venda->update($dados);

            foreach (array_unique([$propriedadeAnterior, $venda->propriedade_id]) as $id) {
                $this->sincronizarStatusPropriedade($id);
            }

            return $venda->fresh('propriedade');
        });
    }

    public function excluir(Venda $venda): void
    {
        DB::transaction(function () use ($venda) {
            $venda->update(['deletado_flg' => true]);
            $this->sincronizarStatusPropriedade($venda->propriedade_id);
        });
    }

    private function sincronizarStatusPropriedade(int $id): void
    {
        $propriedade = Propriedade::ativos()->lockForUpdate()->find($id);
        if (! $propriedade) {
            return;
        }
        $vendida = Venda::ativos()->where('propriedade_id', $id)->whereIn('status', ['fechada', 'escriturada'])->exists();
        if ($vendida) {
            $propriedade->update(['status' => 'vendida']);
        } elseif ($propriedade->status === 'vendida') {
            $propriedade->update(['status' => 'a_venda']);
        }
    }

    private function assertFinalSaleAvailable(int $propertyId, ?int $exceptSaleId, string $status): void
    {
        if (! in_array($status, ['fechada', 'escriturada'], true)) {
            return;
        }

        $query = Venda::ativos()
            ->where('propriedade_id', $propertyId)
            ->whereIn('status', ['fechada', 'escriturada']);
        if ($exceptSaleId !== null) {
            $query->whereKeyNot($exceptSaleId);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Este imóvel já possui uma venda final ativa.',
            ]);
        }
    }

    /**
     * C\u00e1lculo de ROI por propriedade considerando venda + despesas + aquisi\u00e7\u00e3o.
     */
    public function roiPropriedade(int $propriedadeId): array
    {
        $propriedade = Propriedade::ativos()->findOrFail($propriedadeId);

        $totalDespesas = (float) DB::table('despesas')
            ->where('status_pagamento', '!=', 'cancelado')
            ->where('propriedade_id', $propriedadeId)
            ->where('deletado_flg', false)
            ->sum('valor');

        $valorAquisicao = (float) $propriedade->valor_aquisicao;

        $venda = Venda::ativos()
            ->where('propriedade_id', $propriedadeId)
            ->whereIn('status', ['fechada', 'escriturada'])
            ->orderByDesc('data_fechamento')
            ->first();

        $valorVenda = $venda ? (float) $venda->valor_venda : 0;
        $deducoes = $venda
            ? ((float) $venda->valor_comissao + (float) $venda->valor_impostos + (float) $venda->outras_taxas)
            : 0;
        $valorLiquido = $valorVenda - $deducoes;

        $custoTotal = $valorAquisicao + $totalDespesas;
        $lucro = $valorLiquido - $custoTotal;
        $roiPct = $custoTotal > 0 ? round(($lucro / $custoTotal) * 100, 2) : 0;

        return [
            'propriedade_id' => $propriedadeId,
            'valor_aquisicao' => $valorAquisicao,
            'total_despesas' => $totalDespesas,
            'custo_total' => $custoTotal,
            'valor_venda' => $valorVenda,
            'deducoes_venda' => $deducoes,
            'valor_liquido_venda' => $valorLiquido,
            'lucro' => $lucro,
            'roi_pct' => $roiPct,
        ];
    }
}
