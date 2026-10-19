<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ProjectInUseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProjetoRequest;
use App\Http\Resources\ProjetoEtapaResource;
use App\Http\Resources\ProjetoResource;
use App\Models\Projeto;
use App\Models\ProjetoEtapa;
use App\Services\InvestmentAnalysisCalculatorService;
use App\Services\ProjetoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        if (! $projeto) {
            return response()->json(['message' => 'Projeto não encontrado'], 404);
        }

        return (new ProjetoResource($projeto))->response();
    }

    public function update(ProjetoRequest $request, int $id): JsonResponse
    {
        $projeto = $this->service->obter($id);
        if (! $projeto) {
            return response()->json(['message' => 'Projeto não encontrado'], 404);
        }
        $projeto = $this->service->atualizar($projeto, $request->validated());

        return (new ProjetoResource($projeto))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $projeto = $this->service->obter($id);
        if (! $projeto) {
            return response()->json(['message' => 'Projeto não encontrado'], 404);
        }
        try {
            $this->service->excluir($projeto);
        } catch (ProjectInUseException) {
            return response()->json([
                'message' => 'O projeto possui registros financeiros ou documentos ativos e não pode ser excluído.',
            ], 409);
        }

        return response()->json(['message' => 'Projeto excluído']);
    }

    public function resumo(int $id): JsonResponse
    {
        $projeto = $this->service->obter($id);
        if (! $projeto) {
            return response()->json(['message' => 'Projeto não encontrado'], 404);
        }

        return response()->json($this->service->resumo($id));
    }

    public function updateEtapa(Request $request, int $id): JsonResponse
    {
        $etapa = ProjetoEtapa::ativos()->whereHas('projeto', fn ($query) => $query->ativos())->where('tenant_id', $request->user()->tenant_id)->find($id);
        if (! $etapa) {
            return response()->json(['message' => 'Etapa não encontrada'], 404);
        }

        $data = $request->validate([
            'orcamento' => ['sometimes', 'numeric', 'min:0', 'max:999999999999'],
            'status' => ['sometimes', 'in:pendente,em_andamento,concluida,bloqueada'],
            'progresso_pct' => ['sometimes', 'integer', 'min:0', 'max:100'],
        ]);

        return DB::transaction(function () use ($etapa, $data) {
            $projeto = Projeto::ativos()->lockForUpdate()->findOrFail($etapa->projeto_id);
            $etapa->refresh();
            $data = $this->service->normalizeStageState($data, $etapa);
            $etapa->update($data);
            if (array_key_exists('orcamento', $data)) {
                $total = (float) $projeto->etapas()->ativos()->sum('orcamento');
                app(InvestmentAnalysisCalculatorService::class)->validateForStorage(['orcamento_total' => $total]);
                $projeto->update(['orcamento_total' => $total]);
            }

            return response()->json((new ProjetoEtapaResource($etapa->fresh()))->resolve());
        });
    }

    public function storeEtapa(Request $request, int $id): JsonResponse
    {
        $projeto = Projeto::ativos()->where('tenant_id', $request->user()->tenant_id)->find($id);
        if (! $projeto) {
            return response()->json(['message' => 'Projeto não encontrado'], 404);
        }

        $data = $request->validate([
            'nome' => ['required', 'string', 'max:150'],
            'orcamento' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'status' => ['nullable', 'in:pendente,em_andamento,concluida,bloqueada'],
        ]);
        $data = $this->service->normalizeStageState($data);

        return DB::transaction(function () use ($projeto, $data) {
            $projeto = Projeto::ativos()->lockForUpdate()->findOrFail($projeto->id);
            $etapa = $projeto->etapas()->create([
                'tenant_id' => $projeto->tenant_id,
                'nome' => $data['nome'],
                'ordem' => ((int) $projeto->etapas()->max('ordem')) + 1,
                'orcamento' => (float) ($data['orcamento'] ?? 0),
                'custo_real' => 0,
                'status' => $data['status'] ?? 'pendente',
                'progresso_pct' => $data['progresso_pct'] ?? 0,
            ]);
            $total = (float) $projeto->etapas()->ativos()->sum('orcamento');
            app(InvestmentAnalysisCalculatorService::class)->validateForStorage(['orcamento_total' => $total]);
            $projeto->update(['orcamento_total' => $total]);

            return response()->json((new ProjetoEtapaResource($etapa))->resolve(), 201);
        });
    }
}
