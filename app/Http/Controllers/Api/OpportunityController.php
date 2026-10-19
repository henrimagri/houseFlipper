<?php

namespace App\Http\Controllers\Api;

use App\Enums\OpportunitySource;
use App\Enums\OpportunityStatus;
use App\Enums\PropertyType;
use App\Exceptions\OpportunityPhotoStorageException;
use App\Http\Controllers\Controller;
use App\Http\Requests\OpportunityIndexRequest;
use App\Http\Requests\StoreOpportunityRequest;
use App\Http\Requests\UpdateOpportunityRequest;
use App\Http\Resources\OpportunityResource;
use App\Models\Opportunity;
use App\Services\OpportunityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class OpportunityController extends Controller
{
    public function __construct(private readonly OpportunityService $service) {}

    public function index(OpportunityIndexRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $query = $request->user()->opportunities()->with('coverPhoto');
        $query->when($request->filled('search'), fn ($q) => $q->where(fn ($sub) => $sub->where('title', 'like', '%'.$filters['search'].'%')->orWhere('address', 'like', '%'.$filters['search'].'%')));
        foreach (['status', 'city', 'neighborhood', 'property_type', 'source'] as $filter) {
            $query->when($request->filled($filter), fn ($q) => $q->where($filter, $filters[$filter]));
        }
        $query->when($request->filled('favorite'), fn ($q) => $q->where('is_favorite', $request->boolean('favorite')));
        $query->when($request->filled('archived'), fn ($q) => $q->where('is_archived', $request->boolean('archived')), fn ($q) => $q->where('is_archived', false));
        $query->when($request->filled('min_price'), fn ($q) => $q->where('asking_price', '>=', $request->min_price));
        $query->when($request->filled('max_price'), fn ($q) => $q->where('asking_price', '<=', $request->max_price));

        $base = $request->user()->opportunities();
        $stats = [
            'total' => (clone $base)->where('is_archived', false)->count(),
            'analyzing' => (clone $base)->where('status', OpportunityStatus::Analyzing)->count(),
            'favorites' => (clone $base)->where('is_favorite', true)->count(),
            'discarded' => (clone $base)->where('status', OpportunityStatus::Discarded)->count(),
        ];
        $paginator = $query->latest()->paginate($filters['per_page'] ?? 10);

        return response()->json([
            'data' => OpportunityResource::collection($paginator->items()),
            'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total(), 'from' => $paginator->firstItem(), 'to' => $paginator->lastItem()],
            'stats' => $stats,
            'options' => ['statuses' => OpportunityStatus::options(), 'property_types' => PropertyType::options(), 'sources' => OpportunitySource::options()],
        ]);
    }

    public function store(StoreOpportunityRequest $request): JsonResponse
    {
        $opportunity = $this->service->create($request->user(), $request->validated());

        return (new OpportunityResource($opportunity->load(['coverPhoto', 'photos', 'statusHistories.user'])))->response()->setStatusCode(201);
    }

    public function show(Opportunity $opportunity): OpportunityResource
    {
        $this->authorize('view', $opportunity);

        return new OpportunityResource($opportunity->load(['coverPhoto', 'photos', 'statusHistories.user']));
    }

    public function update(UpdateOpportunityRequest $request, Opportunity $opportunity): OpportunityResource
    {
        return new OpportunityResource($this->service->update($opportunity, $request->user(), $request->validated())->load(['coverPhoto', 'photos', 'statusHistories.user']));
    }

    public function destroy(Opportunity $opportunity): JsonResponse
    {
        $this->authorize('delete', $opportunity);
        try {
            $this->service->delete($opportunity);
        } catch (OpportunityPhotoStorageException $exception) {
            Log::warning('Falha ao remover fotos públicas ao excluir oportunidade', [
                'opportunity_id' => $opportunity->id,
                'exception_type' => $exception::class,
            ]);

            return response()->json(['message' => 'Não foi possível remover as fotos públicas. Tente novamente.'], 503);
        }

        return response()->json(['message' => 'Oportunidade excluída.']);
    }

    public function favorite(Opportunity $opportunity): JsonResponse
    {
        $this->authorize('update', $opportunity);
        $opportunity->update(['is_favorite' => ! $opportunity->is_favorite]);

        return response()->json(['is_favorite' => $opportunity->is_favorite]);
    }

    public function archive(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorize('update', $opportunity);
        $this->service->archive($opportunity, $request->user());

        return response()->json(['message' => 'Oportunidade arquivada.']);
    }

    public function discard(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorize('update', $opportunity);
        $data = $request->validate(['discarded_reason' => ['required', 'string', 'max:2000']]);
        $this->service->discard($opportunity, $request->user(), $data['discarded_reason']);

        return response()->json(['message' => 'Oportunidade descartada.']);
    }

    public function changeStatus(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorize('update', $opportunity);
        $data = $request->validate(['status' => ['required', Rule::enum(OpportunityStatus::class)], 'notes' => ['nullable', 'string', 'max:2000']]);
        $this->service->changeStatus($opportunity, $request->user(), OpportunityStatus::from($data['status']), $data['notes'] ?? null);

        return response()->json(['message' => 'Status atualizado.']);
    }
}
