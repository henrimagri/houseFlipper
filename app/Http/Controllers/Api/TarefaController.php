<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TarefaIndexRequest;
use App\Http\Requests\TarefaRequest;
use App\Http\Resources\TarefaResource;
use App\Services\TarefaService;
use Illuminate\Http\JsonResponse;

class TarefaController extends Controller
{
    public function __construct(private readonly TarefaService $service) {}

    public function index(TarefaIndexRequest $request): JsonResponse
    {
        return response()->json(TarefaResource::collection($this->service->listar($request->validated()))->response()->getData(true));
    }

    public function store(TarefaRequest $request): JsonResponse
    {
        return (new TarefaResource($this->service->criar($request->validated())))->response()->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        $task = $this->service->obter($id);
        if (! $task) {
            return response()->json(['message' => 'Tarefa não encontrada'], 404);
        }

        return (new TarefaResource($task))->response();
    }

    public function update(TarefaRequest $request, int $id): JsonResponse
    {
        $task = $this->service->obter($id);
        if (! $task) {
            return response()->json(['message' => 'Tarefa não encontrada'], 404);
        }

        return (new TarefaResource($this->service->atualizar($task, $request->validated())))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $task = $this->service->obter($id);
        if (! $task) {
            return response()->json(['message' => 'Tarefa não encontrada'], 404);
        }
        $this->service->excluir($task);

        return response()->json(['message' => 'Tarefa excluída']);
    }
}
