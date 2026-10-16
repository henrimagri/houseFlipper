<?php

namespace App\Services;

use App\Models\Propriedade;
use App\Models\Venda;
use Illuminate\Support\Facades\DB;

class VendaService
{
    public function listar(array $filtros = [])
    {
        $query = Venda::query()->ativos()->with('propriedade');

        if (!empty($filtros['propriedade_id'])) {
            $query->where('propriedade_id', $filtros['propriedade_id']);
        }
        if (!empty($filtros['status'])) {
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
            $venda = Venda::create($dados);

            if (in_array($venda->status, ['fechada', 'escriturada'])) {
                Propriedade::where('id', $venda->propriedade_id)->update(['status' => 'vendida']);
            }

            return $venda->load('propriedade');
        });
    }

    public function atualizar(Venda $venda, array $dados): Venda
    {
        return DB::transaction(function () use ($venda, $dados) {
            $venda->update($dados);

            if (in_array($venda->status, ['fechada', 'escriturada'])) {
                Propriedade::where('id', $venda->propriedade_id)->update(['status' => 'vendida']);
            }

            return $venda->fresh('propriedade');
        });
    }

    public function excluir(Venda $venda): void
    {
        $venda->update(['deletado_flg' => true]);
    }

    /**
     * C\u00e1lculo de ROI por propriedade considerando venda + despesas + aquisi\u00e7\u00e3o.
     */
    public function roiPropriedade(int $propriedadeId): array
    {
        $propriedade = Propriedade::ativos()->findOrFail($propriedadeId);

        $totalDespesas = (float) DB::table('despesas')
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
