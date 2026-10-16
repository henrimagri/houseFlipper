<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProjetoRequest;
use App\Http\Resources\ProjetoResource;
use App\Models\Projeto;
use App\Models\ProjetoEtapa;
use App\Services\ProjetoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjetoController extends Controller
{
    public function __construct(private readonly ProjetoService $service) {}

    public function index(Request $request): JsonResponse
    {
        $filtros = $request->only(['propriedade_id', 'status', 'per_page']);
        $paginator = $this->service->listar($filtros);
        return response()->json(ProjetoResource::collection($paginator)->response()->getData(true));
    }

    public function store(ProjetoRequest $request): JsonResponse
    {
        $projeto = $this->service->criar($request->validated());
        return (new ProjetoResource($projeto->load('propriedade')))->response()->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        $projeto = $this->service->obter($id);
        if (!$projeto) {
            return response()->json(['message' => 'Projeto não encontrado'], 404);
        }
        return (new ProjetoResource($projeto))->response();
    }

    public function update(ProjetoRequest $request, int $id): JsonResponse
    {
        $projeto = $this->service->obter($id);
        if (!$projeto) {
            return response()->json(['message' => 'Projeto não encontrado'], 404);
        }
        $projeto = $this->service->atualizar($projeto, $request->validated());
        return (new ProjetoResource($projeto))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $projeto = $this->service->obter($id);
        if (!$projeto) {
            return response()->json(['message' => 'Projeto não encontrado'], 404);
        }
        $this->service->excluir($projeto);
        return response()->json(['message' => 'Projeto excluído']);
    }

    public function resumo(int $id): JsonResponse
    {
        $projeto = $this->service->obter($id);
        if (!$projeto) {
            return response()->json(['message' => 'Projeto não encontrado'], 404);
        }
        return response()->json($this->service->resumo($id));
    }

    public function updateEtapa(Request $request, int $id): JsonResponse
    {
        $etapa = ProjetoEtapa::where('tenant_id', $request->user()->tenant_id)->find($id);
        if (!$etapa) {
            return response()->json(['message' => 'Etapa não encontrada'], 404);
        }

        $data = $request->validate([
            'orcamento' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', 'string', 'max:40'],
            'progresso_pct' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ]);
        $etapa->update($data);
        if (array_key_exists('orcamento', $data)) {
            $etapa->projeto()->update([
                'orcamento_total' => $etapa->projeto->etapas()->sum('orcamento'),
            ]);
        }

        return response()->json($etapa->fresh());
    }

    public function storeEtapa(Request $request, int $id): JsonResponse
    {
        $projeto = Projeto::where('tenant_id', $request->user()->tenant_id)->find($id);
        if (!$projeto) {
            return response()->json(['message' => 'Projeto não encontrado'], 404);
        }

        $data = $request->validate([
            'nome' => ['required', 'string', 'max:150'],
            'orcamento' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'max:40'],
        ]);

        $etapa = $projeto->etapas()->create([
            'tenant_id' => $projeto->tenant_id,
            'nome' => $data['nome'],
            'ordem' => ((int) $projeto->etapas()->max('ordem')) + 1,
            'orcamento' => (float) ($data['orcamento'] ?? 0),
            'custo_real' => 0,
            'status' => $data['status'] ?? 'pendente',
            'progresso_pct' => 0,
        ]);
        $projeto->update(['orcamento_total' => $projeto->etapas()->sum('orcamento')]);

        return response()->json($etapa, 201);
    }
}
