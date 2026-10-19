<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\PropertyInUseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\PropriedadeIndexRequest;
use App\Http\Requests\PropriedadeRequest;
use App\Http\Resources\PropriedadeResource;
use App\Services\PropriedadeService;
use Illuminate\Http\JsonResponse;

class PropriedadeController extends Controller
{
    public function __construct(private readonly PropriedadeService $service) {}

    public function index(PropriedadeIndexRequest $request): JsonResponse
    {
        $filtros = $request->validated();
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
        if (! $propriedade) {
            return response()->json(['message' => 'Propriedade não encontrada'], 404);
        }

        return (new PropriedadeResource($propriedade))->response();
    }

    public function update(PropriedadeRequest $request, int $id): JsonResponse
    {
        $propriedade = $this->service->obter($id);
        if (! $propriedade) {
            return response()->json(['message' => 'Propriedade não encontrada'], 404);
        }
        $propriedade = $this->service->atualizar($propriedade, $request->validated());

        return (new PropriedadeResource($propriedade))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $propriedade = $this->service->obter($id);
        if (! $propriedade) {
            return response()->json(['message' => 'Propriedade não encontrada'], 404);
        }
        try {
            $this->service->excluir($propriedade);
        } catch (PropertyInUseException) {
            return response()->json(['message' => 'A propriedade possui registros ativos e não pode ser excluída.'], 409);
        }

        return response()->json(['message' => 'Propriedade excluída']);
    }
}
