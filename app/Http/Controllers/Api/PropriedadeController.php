<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PropriedadeRequest;
use App\Http\Resources\PropriedadeResource;
use App\Models\Propriedade;
use App\Services\PropriedadeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropriedadeController extends Controller
{
    public function __construct(private readonly PropriedadeService $service) {}

    public function index(Request $request): JsonResponse
    {
        $filtros = $request->only(['status', 'busca', 'per_page']);
        $paginator = $this->service->listar($filtros);
        return response()->json(PropriedadeResource::collection($paginator)->response()->getData(true));
    }

    public function store(PropriedadeRequest $request): JsonResponse
    {
        $propriedade = $this->service->criar($request->validated());
        return (new PropriedadeResource($propriedade))->response()->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        $propriedade = $this->service->obter($id);
        if (!$propriedade) {
            return response()->json(['message' => 'Propriedade não encontrada'], 404);
        }
        return (new PropriedadeResource($propriedade))->response();
    }

    public function update(PropriedadeRequest $request, int $id): JsonResponse
    {
        $propriedade = $this->service->obter($id);
        if (!$propriedade) {
            return response()->json(['message' => 'Propriedade não encontrada'], 404);
        }
        $propriedade = $this->service->atualizar($propriedade, $request->validated());
        return (new PropriedadeResource($propriedade))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $propriedade = $this->service->obter($id);
        if (!$propriedade) {
            return response()->json(['message' => 'Propriedade não encontrada'], 404);
        }
        $this->service->excluir($propriedade);
        return response()->json(['message' => 'Propriedade excluída']);
    }
}
