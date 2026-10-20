<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgencyContract;
use App\Services\Ai\ProductEntitlement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgencyContractController extends Controller
{
    public function __construct(private readonly ProductEntitlement $entitlement) {}

    public function index(Request $request): JsonResponse
    {
        $this->entitlement->assert($request->user(), 'agency_contracts');
        $query = AgencyContract::query();
        if ($status = $request->query('status')) $query->where('status', $status);
        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(fn ($inner) => $inner->where('title', 'like', '%'.$search.'%')->orWhere('party_name', 'like', '%'.$search.'%'));
        }
        $items = $query->orderByRaw('ends_at IS NULL')->orderBy('ends_at')->latest('id')->paginate(min(100, max(1, $request->integer('per_page', 25))));

        return response()->json(['data' => $items->items(), 'meta' => ['total' => $items->total(), 'current_page' => $items->currentPage(), 'last_page' => $items->lastPage()]]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->entitlement->assert($request->user(), 'agency_contracts');
        $data = $this->validated($request);
        $contract = AgencyContract::query()->create([...$data, 'tenant_id' => $request->user()->tenant_id, 'user_id' => $request->user()->id]);

        return response()->json(['data' => $contract], 201);
    }

    public function update(Request $request, int $contract): JsonResponse
    {
        $this->entitlement->assert($request->user(), 'agency_contracts');
        $model = AgencyContract::query()->withoutGlobalScopes()->where('tenant_id', $request->user()->tenant_id)->findOrFail($contract);
        $model->fill($this->validated($request))->save();

        return response()->json(['data' => $model->fresh()]);
    }

    public function destroy(Request $request, int $contract): JsonResponse
    {
        $this->entitlement->assert($request->user(), 'agency_contracts');
        $model = AgencyContract::query()->withoutGlobalScopes()->where('tenant_id', $request->user()->tenant_id)->findOrFail($contract);
        $model->delete();

        return response()->json(['message' => 'Contrato excluído.']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'party_name' => ['required', 'string', 'max:180'],
            'type' => ['required', 'in:sale,rental,management,capture,service'],
            'status' => ['required', 'in:draft,active,expiring,completed,cancelled'],
            'amount_cents' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
