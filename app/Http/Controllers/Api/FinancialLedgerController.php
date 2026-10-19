<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FinancialLedgerRequest;
use App\Models\Despesa;
use App\Models\Projeto;
use App\Models\Propriedade;
use App\Models\Venda;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class FinancialLedgerController extends Controller
{
    public function __invoke(FinancialLedgerRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $selectedProject = filled($filters['projeto_id'] ?? null)
            ? Projeto::ativos()->findOrFail((int) $filters['projeto_id'])
            : null;
        $propertyId = $selectedProject?->propriedade_id;
        $start = $filters['data_inicio'] ?? null;
        $end = $filters['data_fim'] ?? null;

        $properties = Propriedade::ativos()->when($propertyId, fn ($query) => $query->whereKey($propertyId))
            ->when($start, fn ($query) => $query->whereDate('data_aquisicao', '>=', $start))
            ->when($end, fn ($query) => $query->whereDate('data_aquisicao', '<=', $end))->get();
        $expenses = Despesa::ativos()->where('status_pagamento', '!=', 'cancelado')
            ->when($propertyId, fn ($query) => $query->where(fn ($nested) => $nested->where('projeto_id', $selectedProject->id)->orWhere('propriedade_id', $propertyId)))
            ->when($start, fn ($query) => $query->whereDate('data_despesa', '>=', $start))
            ->when($end, fn ($query) => $query->whereDate('data_despesa', '<=', $end))->with('projeto')->get();
        $sales = Venda::ativos()->whereIn('status', ['fechada', 'escriturada'])
            ->whereHas('propriedade', fn ($query) => $query->ativos())
            ->when($propertyId, fn ($query) => $query->where('propriedade_id', $propertyId))
            ->when($start, fn ($query) => $query->whereDate('data_fechamento', '>=', $start))
            ->when($end, fn ($query) => $query->whereDate('data_fechamento', '<=', $end))->with('propriedade')->get();

        $projectNameByProperty = $this->projectNames(
            $properties->pluck('id')->merge($sales->pluck('propriedade_id'))->unique()->filter()
        );
        $entries = collect()
            ->concat($properties->filter(fn ($item) => $item->data_aquisicao)->map(fn ($item) => [
                'id' => 'property-'.$item->id,
                'date' => $item->data_aquisicao->toDateString(),
                'type' => 'acquisition',
                'description' => 'Aquisição: '.$item->apelido,
                'project' => $selectedProject?->nome ?? $projectNameByProperty->get($item->id),
                'amount' => -round((float) $item->valor_aquisicao, 2),
            ]))
            ->concat($expenses->map(fn ($item) => [
                'id' => 'expense-'.$item->id,
                'date' => $item->data_despesa->toDateString(),
                'type' => 'expense',
                'description' => $item->descricao,
                'project' => $item->projeto?->nome ?? $selectedProject?->nome,
                'amount' => -round((float) $item->valor, 2),
            ]))
            ->concat($sales->filter(fn ($item) => $item->data_fechamento)->map(fn ($item) => [
                'id' => 'sale-'.$item->id,
                'date' => $item->data_fechamento->toDateString(),
                'type' => 'sale',
                'description' => 'Venda: '.($item->propriedade?->apelido ?? '#'.$item->propriedade_id),
                'project' => $selectedProject?->nome ?? $projectNameByProperty->get($item->propriedade_id),
                'amount' => round($item->valorLiquido(), 2),
            ]))->sortByDesc(fn (array $item) => $item['date'].'-'.$item['id'])->values();

        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 25);
        $paginator = new LengthAwarePaginator($entries->forPage($page, $perPage)->values(), $entries->count(), $perPage, $page);

        return response()->json([
            'data' => array_values($paginator->items()),
            'meta' => [
                'current_page' => $page,
                'last_page' => $paginator->lastPage(),
                'per_page' => $perPage,
                'total' => $entries->count(),
            ],
            'filters' => [
                'data_inicio' => $start,
                'data_fim' => $end,
                'projeto_id' => $selectedProject?->id,
            ],
            'totals' => [
                'inflows' => round((float) $entries->where('amount', '>', 0)->sum('amount'), 2),
                'outflows' => round(abs((float) $entries->where('amount', '<', 0)->sum('amount')), 2),
                'balance' => round((float) $entries->sum('amount'), 2),
            ],
        ]);
    }

    private function projectNames(Collection $propertyIds): Collection
    {
        if ($propertyIds->isEmpty()) {
            return collect();
        }

        return Projeto::ativos()->whereIn('propriedade_id', $propertyIds)->orderBy('id')
            ->get()->groupBy('propriedade_id')->map(fn (Collection $projects) => $projects->first()?->nome);
    }
}
