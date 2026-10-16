<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DespesaRequest;
use App\Http\Resources\DespesaResource;
use App\Services\DespesaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DespesaController extends Controller
{
    public function __construct(private readonly DespesaService $service) {}

    public function index(Request $request): JsonResponse
    {
        $filtros = $request->only([
            'propriedade_id', 'projeto_id', 'etapa_id', 'tarefa_id',
            'categoria_despesa_id', 'status_pagamento', 'data_inicio', 'data_fim', 'per_page',
        ]);
        $paginator = $this->service->listar($filtros);
        return response()->json(DespesaResource::collection($paginator)->response()->getData(true));
    }

    public function store(DespesaRequest $request): JsonResponse
    {
        $despesa = $this->service->criar($request->validated());
        return (new DespesaResource($despesa->load('categoria')))->response()->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        $despesa = $this->service->obter($id);
        if (!$despesa) {
            return response()->json(['message' => 'Despesa não encontrada'], 404);
        }
        return (new DespesaResource($despesa))->response();
    }

    public function update(DespesaRequest $request, int $id): JsonResponse
    {
        $despesa = $this->service->obter($id);
        if (!$despesa) {
            return response()->json(['message' => 'Despesa não encontrada'], 404);
        }
        $despesa = $this->service->atualizar($despesa, $request->validated());
        return (new DespesaResource($despesa))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $despesa = $this->service->obter($id);
        if (!$despesa) {
            return response()->json(['message' => 'Despesa não encontrada'], 404);
        }
        $this->service->excluir($despesa);
        return response()->json(['message' => 'Despesa excluída']);
    }
}
