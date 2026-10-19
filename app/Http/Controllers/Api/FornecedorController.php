<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FornecedorRequest;
use App\Http\Resources\FornecedorResource;
use App\Services\FornecedorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FornecedorController extends Controller
{
    public function __construct(private readonly FornecedorService $service) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->listar($request->only(['busca', 'categoria', 'status', 'per_page']));

        return response()->json(FornecedorResource::collection($paginator)->response()->getData(true));
    }

    public function store(FornecedorRequest $request): JsonResponse
    {
        return (new FornecedorResource($this->service->criar($request->validated())))->response()->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        $fornecedor = $this->service->obter($id);
        if (! $fornecedor) {
            return response()->json(['message' => 'Fornecedor não encontrado'], 404);
        }

        return (new FornecedorResource($fornecedor))->response();
    }

    public function update(FornecedorRequest $request, int $id): JsonResponse
    {
        $fornecedor = $this->service->obter($id);
        if (! $fornecedor) {
            return response()->json(['message' => 'Fornecedor não encontrado'], 404);
        }

        return (new FornecedorResource($this->service->atualizar($fornecedor, $request->validated())))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $fornecedor = $this->service->obter($id);
        if (! $fornecedor) {
            return response()->json(['message' => 'Fornecedor não encontrado'], 404);
        }
        $this->service->excluir($fornecedor);

        return response()->json(['message' => 'Fornecedor excluído']);
    }
}
