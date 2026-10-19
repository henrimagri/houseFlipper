<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Despesa;
use App\Models\Projeto;
use App\Models\Propriedade;
use App\Models\Venda;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $expenses = Despesa::ativos()->where('status_pagamento', '!=', 'cancelado');
        $acquisition = (float) Propriedade::ativos()->comAquisicao()->sum('valor_aquisicao');
        $expenseTotal = (float) (clone $expenses)->sum('valor');
        $sales = Venda::ativos()->whereIn('status', ['fechada', 'escriturada'])
            ->whereHas('propriedade', fn ($q) => $q->ativos())->with('propriedade')
            ->orderByDesc('data_fechamento')->orderByDesc('id')->get()->unique('propriedade_id');
        $soldIds = $sales->pluck('propriedade_id');
        $soldCosts = (float) Propriedade::ativos()->whereIn('id', $soldIds)->sum('valor_aquisicao')
            + (float) (clone $expenses)->whereIn('propriedade_id', $soldIds)->sum('valor');
        $revenue = (float) $sales->sum('valor_venda');
        $net = (float) $sales->sum(fn ($sale) => $sale->valorLiquido());
        $profit = $net - $soldCosts;
        $performance = [];
        for ($offset = 5; $offset >= 0; $offset--) {
            $start = now()->startOfMonth()->subMonths($offset);
            $end = $start->copy()->endOfMonth();
            $investment = (float) Propriedade::ativos()->whereBetween('data_aquisicao', [$start->toDateString(), $end->toDateString()])->sum('valor_aquisicao')
                + (float) (clone $expenses)->whereBetween('data_despesa', [$start->toDateString(), $end->toDateString()])->sum('valor');
            $monthSales = $sales->filter(fn ($sale) => $sale->data_fechamento?->betweenIncluded($start, $end));
            $performance[] = ['mes' => $start->format('m/Y'), 'investimento' => round($investment, 2), 'faturamento' => (float) $monthSales->sum('valor_venda'), 'lucro' => round((float) $monthSales->sum(fn ($sale) => $sale->valorLiquido()) - $investment, 2)];
        }
        $projects = Projeto::ativos()->where('status', 'em_andamento')->with('propriedade.endereco')->latest('data_inclusao')->limit(5)->get()->map(function ($project) use ($expenses) {
            $cost = (float) $project->propriedade?->valor_aquisicao + (float) (clone $expenses)->where('propriedade_id', $project->propriedade_id)->sum('valor');
            $target = (float) $project->propriedade?->valor_venda_alvo;

            return [
                'id' => (string) $project->id, 'nome' => $project->nome, 'numero' => '#'.$project->id,
                'cidade' => $project->propriedade?->endereco?->cidade ?? 'Não informada',
                'investido' => (float) $project->custo_real, 'estimado' => (float) $project->propriedade?->valor_venda_alvo,
                'status' => 'Em andamento', 'statusColor' => 'bg-blue-100 text-blue-700', 'roi' => $cost > 0 && $target > 0 ? round(($target - $cost) / $cost * 100, 2) : null,
            ];
        });

        return response()->json([
            'kpis' => ['active_projects' => Projeto::ativos()->where('status', 'em_andamento')->count(), 'total_projects' => Projeto::ativos()->count(), 'realized_profit' => round($profit, 2), 'sales_revenue' => round($revenue, 2), 'sold_properties' => $sales->count(), 'realized_roi' => $soldCosts > 0 ? round($profit / $soldCosts * 100, 2) : 0, 'total_invested' => round($acquisition + $expenseTotal, 2)],
            'performance' => $performance,
            'composition' => [['name' => 'Aquisição de imóveis', 'valor' => $acquisition, 'color' => '#3b82f6'], ['name' => 'Despesas não canceladas', 'valor' => $expenseTotal, 'color' => '#10b981']],
            'projects' => $projects,
            'activities' => (clone $expenses)->latest('data_inclusao')->limit(5)->get()->map(fn ($expense) => ['titulo' => 'Despesa registrada', 'desc' => $expense->descricao, 'tempo' => $expense->data_despesa?->format('d/m/Y')]),
        ]);
    }
}
