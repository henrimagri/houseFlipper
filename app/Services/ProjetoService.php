<?php

namespace App\Services;

use App\Models\Despesa;
use App\Models\Projeto;
use App\Models\ProjetoEtapa;
use App\Models\Propriedade;
use App\Models\Venda;
use Illuminate\Support\Facades\DB;

class ProjetoService
{
    /**
     * Etapas padrão criadas automaticamente quando o usuário não envia nenhuma.
     * As chaves coincidem com `orcamento_categorias.*` para puxar valores estimados.
     */
    public const ETAPAS_PADRAO = [
        ['chave' => 'demolicao', 'nome' => 'Demolição', 'ordem' => 1],
        ['chave' => 'eletrica', 'nome' => 'Elétrica', 'ordem' => 2],
        ['chave' => 'hidraulica', 'nome' => 'Hidráulica', 'ordem' => 3],
        ['chave' => 'acabamento', 'nome' => 'Acabamento', 'ordem' => 4],
    ];

    /**
     * Cria categorias padrão de orçamento para uma nova obra.
     */
    public static function criarCategoriasOrcamentoPadrao(int $workId): void
    {
        foreach (['Demolição', 'Elétrica', 'Hidráulica', 'Acabamento'] as $cat) {
            DB::table('work_budgets')->insert([
                'work_id' => $workId,
                'category' => $cat,
                'estimated_cost' => 0.00,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function listar(array $filtros = [])
    {
        $query = Projeto::query()->ativos()->with('propriedade');

        if (!empty($filtros['propriedade_id'])) {
            $query->where('propriedade_id', $filtros['propriedade_id']);
        }
        if (!empty($filtros['status'])) {
            $query->where('status', $filtros['status']);
        }

        return $query->orderByDesc('data_inclusao')->paginate($filtros['per_page'] ?? 15);
    }

    public function obter(int $id): ?Projeto
    {
        return Projeto::ativos()->with(['propriedade', 'etapas', 'tarefas'])->find($id);
    }

    public function criar(array $dados): Projeto
    {
        return DB::transaction(function () use ($dados) {
            $etapasInput = $dados['etapas'] ?? null;
            $categorias = $dados['orcamento_categorias'] ?? null;
            unset($dados['etapas'], $dados['orcamento_categorias']);

            // Se orçamento_total não foi informado, somar categorias.
            if (empty($dados['orcamento_total']) && is_array($categorias)) {
                $dados['orcamento_total'] = array_sum(array_map('floatval', $categorias));
            }

            $projeto = Projeto::create($dados);
            // Cria categorias padrão de orçamento ao criar nova obra
            self::criarCategoriasOrcamentoPadrao($projeto->id);

            $etapas = $this->montarEtapas($etapasInput, $categorias);
            foreach ($etapas as $ordem => $etapa) {
                ProjetoEtapa::create(array_merge($etapa, [
                    'tenant_id' => $projeto->tenant_id,
                    'projeto_id' => $projeto->id,
                    'ordem' => $etapa['ordem'] ?? ($ordem + 1),
                ]));
            }

            return $projeto->load(['propriedade', 'etapas']);
        });
    }

    /**
     * Cria etapas padrão automaticamente para uma nova obra.
     * @param int $workId
     * @param int $tenantId
     * @param array|null $categorias
     */
    public function criarEtapasPadrao(int $projetoId, int $tenantId, ?array $categorias = null): void
    {
        $etapasPadrao = [
            ['nome' => 'Demolição', 'ordem' => 1],
            ['nome' => 'Elétrica', 'ordem' => 2],
            ['nome' => 'Hidráulica', 'ordem' => 3],
            ['nome' => 'Acabamento', 'ordem' => 4],
        ];
        foreach ($etapasPadrao as $etapa) {
            $custoEstimado = 0.00;
            if ($categorias && isset($categorias[$etapa['nome']])) {
                $custoEstimado = (float) $categorias[$etapa['nome']];
            }
            \App\Models\ProjetoEtapa::create([
                'tenant_id' => $tenantId,
                'projeto_id' => $projetoId,
                'nome' => $etapa['nome'],
                'ordem' => $etapa['ordem'],
                'status' => 'Pendente',
                'progresso_pct' => 0,
                'orcamento' => $custoEstimado,
                'custo_real' => 0.00,
            ]);
        }
    }

    public function atualizar(Projeto $projeto, array $dados): Projeto
    {
        unset($dados['etapas'], $dados['orcamento_categorias']);
        $projeto->update($dados);
        return $projeto->fresh(['propriedade', 'etapas']);
    }

    public function excluir(Projeto $projeto): void
    {
        $projeto->update(['deletado_flg' => true]);
    }

    /**
     * Recalcula custo_real de cada etapa e do projeto a partir das despesas.
     * Chamado pelo DespesaService após criar/atualizar/excluir despesa.
     */
    public function recalcularCustos(int $projetoId): void
    {
        DB::transaction(function () use ($projetoId) {
            $etapas = ProjetoEtapa::where('projeto_id', $projetoId)->get();
            foreach ($etapas as $etapa) {
                $total = (float) Despesa::where('etapa_id', $etapa->id)
                    ->where('deletado_flg', false)
                    ->sum('valor');
                $etapa->update(['custo_real' => $total]);
            }

            $totalProjeto = (float) Despesa::where('projeto_id', $projetoId)
                ->where('deletado_flg', false)
                ->sum('valor');
            Projeto::where('id', $projetoId)->update(['custo_real' => $totalProjeto]);
        });
    }

    /**
     * Resumo financeiro em tempo real para a tela de Obras.
     * Inclui economia (orçamento - real) e ROI projetado se a propriedade já tiver venda.
     */
    public function resumo(int $projetoId): array
    {
        $projeto = Projeto::ativos()->with('etapas')->findOrFail($projetoId);

        $this->recalcularCustos($projetoId);
        $projeto->refresh();
        $projeto->load('etapas');

        $orcamento = (float) $projeto->orcamento_total;
        $custoReal = (float) $projeto->custo_real;
        $economia = $orcamento - $custoReal;
        $consumidoPct = $orcamento > 0 ? round(($custoReal / $orcamento) * 100, 2) : 0;
        $progressoMedio = $projeto->etapas->avg('progresso_pct') ?? 0;

        $roi = $this->roiProjetado($projeto);

        return [
            'projeto_id' => $projeto->id,
            'orcamento_total' => $orcamento,
            'custo_real' => $custoReal,
            'economia' => round($economia, 2),
            'consumido_pct' => $consumidoPct,
            'progresso_pct' => round((float) $progressoMedio, 2),
            'etapas' => $projeto->etapas->map(fn ($e) => [
                'id' => $e->id,
                'nome' => $e->nome,
                'orcamento' => (float) $e->orcamento,
                'custo_real' => (float) $e->custo_real,
                'economia' => round((float) $e->orcamento - (float) $e->custo_real, 2),
                'status' => $e->status,
                'progresso_pct' => $e->progresso_pct,
            ])->all(),
            'roi' => $roi,
        ];
    }

    private function montarEtapas(?array $etapasInput, ?array $categorias): array
    {
        if (is_array($etapasInput) && count($etapasInput) > 0) {
            return $etapasInput;
        }

        $categorias = $categorias ?? [];
        return collect(self::ETAPAS_PADRAO)->map(fn (array $e) => [
            'nome' => $e['nome'],
            'ordem' => $e['ordem'],
            'orcamento' => (float) ($categorias[$e['chave']] ?? 0),
            'status' => 'pendente',
            'progresso_pct' => 0,
        ])->all();
    }

    private function roiProjetado(Projeto $projeto): array
    {
        $propriedadeId = $projeto->propriedade_id;
        $propriedade = Propriedade::ativos()->find($propriedadeId);
        $valorAquisicao = $propriedade ? (float) $propriedade->valor_aquisicao : 0;

        $totalDespesas = (float) Despesa::where('propriedade_id', $propriedadeId)
            ->where('deletado_flg', false)
            ->sum('valor');

        $venda = Venda::ativos()
            ->where('propriedade_id', $propriedadeId)
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
            'valor_aquisicao' => $valorAquisicao,
            'total_despesas' => $totalDespesas,
            'custo_total' => $custoTotal,
            'valor_liquido_venda' => $valorLiquido,
            'lucro' => round($lucro, 2),
            'roi_pct' => $roiPct,
            'tem_venda' => (bool) $venda,
        ];
    }
}
