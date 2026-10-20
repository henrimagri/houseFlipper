<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Opportunity;
use App\Models\Projeto;
use App\Models\Propriedade;
use App\Models\Tarefa;
use App\Services\Ai\ProductEntitlement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FlipperDashboardController extends Controller
{
    public function __construct(
        private readonly ProductEntitlement $entitlement,
        private readonly DashboardController $legacy,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->entitlement->assertProduct($user, 'house_flipper');
        $legacy = $this->legacy->__invoke()->getData(true);
        $properties = Propriedade::ativos()->comAquisicao();
        $estimatedAcquisition = (float) (clone $properties)->sum('valor_aquisicao');
        $estimatedResale = (float) (clone $properties)->sum('valor_venda_alvo');
        $budget = (float) Projeto::ativos()->sum('orcamento_total');
        $realizedCost = (float) Projeto::ativos()->sum('custo_real');
        $overruns = Projeto::ativos()
            ->where('orcamento_total', '>', 0)
            ->whereColumn('custo_real', '>', 'orcamento_total')
            ->get(['id', 'nome', 'orcamento_total', 'custo_real', 'status']);
        $overdue = Tarefa::query()
            ->where('deletado_flg', false)
            ->whereNotNull('data_prevista')
            ->whereDate('data_prevista', '<', now()->toDateString())
            ->whereNotIn('status', ['concluida', 'cancelada'])
            ->orderBy('data_prevista')
            ->limit(8)
            ->get(['id', 'titulo', 'data_prevista', 'status', 'projeto_id']);

        return response()->json([
            'kpis' => array_merge($legacy['kpis'] ?? [], [
                'opportunities' => Opportunity::query()->where('user_id', $user->id)->where('is_archived', false)->count(),
                'favorites' => Opportunity::query()->where('user_id', $user->id)->where('is_favorite', true)->count(),
                'active_projects' => Projeto::ativos()->where('status', 'em_andamento')->count(),
                'completed_projects' => Projeto::ativos()->where('status', 'concluido')->count(),
            ]),
            'estimated' => [
                'acquisition' => round($estimatedAcquisition, 2),
                'renovation_budget' => round($budget, 2),
                'resale_target' => round($estimatedResale, 2),
                'projected_profit' => round($estimatedResale - $estimatedAcquisition - $budget, 2),
                'projected_roi_pct' => ($estimatedAcquisition + $budget) > 0
                    ? round(($estimatedResale - $estimatedAcquisition - $budget) / ($estimatedAcquisition + $budget) * 100, 2)
                    : 0,
            ],
            'realized' => [
                'invested' => $legacy['kpis']['total_invested'] ?? 0,
                'profit' => $legacy['kpis']['realized_profit'] ?? 0,
                'roi_pct' => $legacy['kpis']['realized_roi'] ?? 0,
                'project_cost' => round($realizedCost, 2),
            ],
            'performance' => $legacy['performance'] ?? [],
            'composition' => $legacy['composition'] ?? [],
            'projects' => $legacy['projects'] ?? [],
            'activities' => $legacy['activities'] ?? [],
            'alerts' => [
                'budget_overruns' => $overruns,
                'overdue_tasks' => $overdue,
            ],
            'recent_opportunities' => Opportunity::query()->where('user_id', $user->id)->latest('id')->limit(6)->get(['id', 'title', 'city', 'status', 'asking_price']),
            'disclaimer' => 'Projeções usam premissas cadastradas (aquisição, orçamento e valor alvo). Lucro e ROI realizados usam vendas fechadas. Não há promessa de rentabilidade.',
        ]);
    }
}
