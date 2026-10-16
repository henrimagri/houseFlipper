<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\VendaRequest;
use App\Http\Resources\VendaResource;
use App\Services\VendaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendaController extends Controller
{
    public function __construct(private readonly VendaService $service) {}

    public function index(Request $request): JsonResponse
    {
        $filtros = $request->only(['propriedade_id', 'status', 'per_page']);
        $paginator = $this->service->listar($filtros);
        return response()->json(VendaResource::collection($paginator)->response()->getData(true));
    }

    public function store(VendaRequest $request): JsonResponse
    {
        $venda = $this->service->criar($request->validated());
        return (new VendaResource($venda))->response()->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        $venda = $this->service->obter($id);
        if (!$venda) {
            return response()->json(['message' => 'Venda não encontrada'], 404);
        }
        return (new VendaResource($venda))->response();
    }

    public function update(VendaRequest $request, int $id): JsonResponse
    {
        $venda = $this->service->obter($id);
        if (!$venda) {
            return response()->json(['message' => 'Venda não encontrada'], 404);
        }
        $venda = $this->service->atualizar($venda, $request->validated());
        return (new VendaResource($venda))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $venda = $this->service->obter($id);
        if (!$venda) {
            return response()->json(['message' => 'Venda não encontrada'], 404);
        }
        $this->service->excluir($venda);
        return response()->json(['message' => 'Venda excluída']);
    }

    public function roi(int $propriedadeId): JsonResponse
    {
        return response()->json($this->service->roiPropriedade($propriedadeId));
    }
}
