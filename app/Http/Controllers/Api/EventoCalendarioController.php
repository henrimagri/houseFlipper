<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventoCalendarioRequest;
use App\Http\Resources\EventoCalendarioResource;
use App\Services\EventoCalendarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventoCalendarioController extends Controller
{
    public function __construct(private readonly EventoCalendarioService $service) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'data_inicio' => ['required', 'date', 'max:40'],
            'data_fim' => ['required', 'date', 'max:40', 'after_or_equal:data_inicio'],
            'categoria' => ['sometimes', 'nullable', 'in:visita,obra,compra,pagamento,entrega,outro'],
            'status' => ['sometimes', 'nullable', 'in:agendado,concluido,cancelado'],
            'projeto_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);
        $paginator = $this->service->listar($filters);

        return response()->json(EventoCalendarioResource::collection($paginator)->response()->getData(true));
    }

    public function store(EventoCalendarioRequest $request): JsonResponse
    {
        return (new EventoCalendarioResource($this->service->criar($request->validated(), $request->user())))
            ->response()->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        $event = $this->service->obter($id);
        if (! $event) {
            return response()->json(['message' => 'Evento não encontrado'], 404);
        }

        return (new EventoCalendarioResource($event))->response();
    }

    public function update(EventoCalendarioRequest $request, int $id): JsonResponse
    {
        $event = $this->service->obter($id);
        if (! $event) {
            return response()->json(['message' => 'Evento não encontrado'], 404);
        }

        return (new EventoCalendarioResource($this->service->atualizar($event, $request->validated())))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $event = $this->service->obter($id);
        if (! $event) {
            return response()->json(['message' => 'Evento não encontrado'], 404);
        }
        $this->service->excluir($event);

        return response()->json(['message' => 'Evento excluído']);
    }
}
