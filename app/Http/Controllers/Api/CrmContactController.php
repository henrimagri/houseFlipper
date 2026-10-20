<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmContact;
use App\Services\Ai\ProductEntitlement;
use App\Services\Workspace\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmContactController extends Controller
{
    public function __construct(
        private readonly ProductEntitlement $entitlement,
        private readonly OrganizationService $organizations,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->entitlement->assert($request->user(), 'crm_contacts');
        $query = $this->scoped($request)->with(['listing:id,title,slug', 'assignedUser:id,name']);
        if ($kind = $request->query('kind')) {
            $query->where('kind', $kind);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($inner) use ($search) {
                $inner->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }
        $paginator = $query->latest()->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => collect($paginator->items())->map(fn (CrmContact $contact) => $this->payload($contact)),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->entitlement->assert($request->user(), 'crm_contacts');
        if (is_string($request->input('phone'))) {
            $request->merge(['phone' => preg_replace('/\D/', '', $request->input('phone'))]);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'email' => ['nullable', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'regex:/^\d{10,11}$/'],
            'message' => ['nullable', 'string', 'max:5000'],
            'kind' => ['nullable', 'in:lead,client,owner'],
            'status' => ['nullable', 'in:new,contacted,qualified,visit,proposal,won,lost'],
            'source' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'priority' => ['nullable', 'in:baixa,normal,alta'],
            'commercial_listing_id' => ['nullable', 'integer'],
            'next_action_at' => ['nullable', 'date'],
            'preferences' => ['nullable', 'array'],
        ]);
        $user = $request->user();
        $membership = $this->organizations->membership($user);
        $contact = CrmContact::query()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'assigned_user_id' => $user->id,
            'organization_id' => $membership?->organization_id,
            'branch_id' => $membership?->branch_id,
            'commercial_listing_id' => $data['commercial_listing_id'] ?? null,
            'kind' => $data['kind'] ?? 'client',
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'message' => $data['message'] ?? null,
            'consent' => true,
            'source' => $data['source'] ?? 'manual',
            'status' => $data['status'] ?? 'new',
            'notes' => $data['notes'] ?? null,
            'priority' => $data['priority'] ?? 'normal',
            'next_action_at' => $data['next_action_at'] ?? null,
            'preferences' => $data['preferences'] ?? null,
        ]);

        return response()->json(['data' => $this->payload($contact->load(['listing:id,title,slug', 'assignedUser:id,name']))], 201);
    }

    public function show(Request $request, CrmContact $crmContact): JsonResponse
    {
        $this->authorize('view', $crmContact);

        return response()->json(['data' => $this->payload($crmContact->load(['listing:id,title,slug', 'assignedUser:id,name']))]);
    }

    public function update(Request $request, CrmContact $crmContact): JsonResponse
    {
        $this->authorize('update', $crmContact);
        if (is_string($request->input('phone'))) {
            $request->merge(['phone' => preg_replace('/\D/', '', $request->input('phone'))]);
        }
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:180'],
            'email' => ['nullable', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'regex:/^\d{10,11}$/'],
            'message' => ['nullable', 'string', 'max:5000'],
            'kind' => ['nullable', 'in:lead,client,owner'],
            'status' => ['sometimes', 'in:new,contacted,qualified,visit,proposal,won,lost'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'priority' => ['nullable', 'in:baixa,normal,alta'],
            'commercial_listing_id' => ['nullable', 'integer'],
            'assigned_user_id' => ['nullable', 'integer'],
            'next_action_at' => ['nullable', 'date'],
            'preferences' => ['nullable', 'array'],
        ]);
        $crmContact->fill($data)->save();

        return response()->json(['data' => $this->payload($crmContact->fresh(['listing:id,title,slug', 'assignedUser:id,name']))]);
    }

    private function scoped(Request $request)
    {
        $user = $request->user();
        $query = CrmContact::query()->where(function ($inner) use ($user) {
            $inner->where('user_id', $user->id)->orWhere('assigned_user_id', $user->id);
        });
        $membership = $this->organizations->membership($user);
        if ($membership && $this->entitlement->canAccessProduct($user, 'imobiliaria')) {
            $query = CrmContact::query()->where(function ($inner) use ($user, $membership) {
                $inner->where('user_id', $user->id)
                    ->orWhere('assigned_user_id', $user->id)
                    ->orWhere('organization_id', $membership->organization_id);
            });
            $branch = $this->organizations->visibleBranchId($membership);
            if ($branch) {
                $query->where(function ($inner) use ($branch) {
                    $inner->where('branch_id', $branch)->orWhereNull('branch_id');
                });
            }
        }

        return $query;
    }

    private function payload(CrmContact $contact): array
    {
        return [
            'id' => $contact->id,
            'name' => $contact->name,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'message' => $contact->message,
            'notes' => $contact->notes,
            'kind' => $contact->kind,
            'status' => $contact->status,
            'source' => $contact->source,
            'priority' => $contact->priority ?? 'normal',
            'preferences' => $contact->preferences,
            'next_action_at' => $contact->next_action_at?->toIso8601String(),
            'assigned_user' => $contact->assignedUser ? ['id' => $contact->assignedUser->id, 'name' => $contact->assignedUser->name] : null,
            'listing' => $contact->listing ? [
                'id' => $contact->listing->id,
                'title' => $contact->listing->title,
                'slug' => $contact->listing->slug,
            ] : null,
            'created_at' => $contact->created_at?->toIso8601String(),
            'updated_at' => $contact->updated_at?->toIso8601String(),
        ];
    }
}
