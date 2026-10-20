<?php

namespace App\Http\Controllers\Api;

use App\Enums\BrokerSiteStatus;
use App\Enums\DealStage;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\AgencySite;
use App\Models\Branch;
use App\Models\Commission;
use App\Models\CommissionRule;
use App\Models\CrmDeal;
use App\Models\CrmProposal;
use App\Models\CrmTicket;
use App\Models\LeadAssignment;
use App\Models\LeadRouletteState;
use App\Models\OrganizationMembership;
use App\Models\Scopes\TenantScope;
use App\Services\Ai\ProductEntitlement;
use App\Services\Workspace\AgencyCrmService;
use App\Services\Workspace\CommissionService;
use App\Services\Workspace\LeadRouletteService;
use App\Services\Workspace\OrganizationService;
use App\Support\BrokerThemeSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgencyWorkspaceController extends Controller
{
    public function __construct(
        private readonly ProductEntitlement $entitlement,
        private readonly OrganizationService $organizations,
        private readonly AgencyCrmService $crm,
        private readonly LeadRouletteService $roulette,
        private readonly CommissionService $commissions,
    ) {}

    public function organization(Request $request): JsonResponse
    {
        $this->entitlement->assertProduct($request->user(), 'imobiliaria');
        $org = $this->organizations->ensureForTenant($request->user());

        return response()->json([
            'data' => $org,
            'membership' => $this->organizations->membership($request->user()),
            'members' => OrganizationMembership::query()->where('organization_id', $org->id)->with('user:id,name,email')->get(),
            'branches' => Branch::query()->where('organization_id', $org->id)->get(),
        ]);
    }

    public function storeMember(Request $request): JsonResponse
    {
        $this->entitlement->assert($request->user(), 'teams', 'imobiliaria');
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'role' => ['nullable', 'in:owner,manager,agent'],
            'status' => ['nullable', 'in:active,inactive'],
            'branch_id' => ['nullable', 'integer'],
            'specialties' => ['nullable', 'array'],
        ]);

        return response()->json(['data' => $this->organizations->addMember($request->user(), $data)]);
    }

    public function storeBranch(Request $request): JsonResponse
    {
        $this->entitlement->assert($request->user(), 'branches', 'imobiliaria');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'size:2'],
            'neighborhood' => ['nullable', 'string', 'max:120'],
        ]);

        return response()->json(['data' => $this->organizations->createBranch($request->user(), $data)], 201);
    }

    public function deals(Request $request): JsonResponse
    {
        $this->entitlement->assert($request->user(), 'crm_pipeline', 'imobiliaria');
        $deals = $this->crm->scopedDeals($request->user())
            ->with(['contact:id,name', 'listing:id,title', 'assignedUser:id,name'])
            ->latest('id')
            ->get();

        return response()->json(['data' => $deals, 'funnel' => $this->crm->funnel($request->user())]);
    }

    public function storeDeal(Request $request): JsonResponse
    {
        $this->entitlement->assert($request->user(), 'crm_pipeline', 'imobiliaria');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'crm_contact_id' => ['nullable', 'integer'],
            'commercial_listing_id' => ['nullable', 'integer'],
            'assigned_user_id' => ['nullable', 'integer'],
            'branch_id' => ['nullable', 'integer'],
            'estimated_value_cents' => ['nullable', 'integer', 'min:0'],
            'priority' => ['nullable', 'in:baixa,normal,alta'],
        ]);

        return response()->json(['data' => $this->crm->createDeal($request->user(), $data)], 201);
    }

    public function showDeal(Request $request, CrmDeal $deal): JsonResponse
    {
        $this->entitlement->assert($request->user(), 'crm_pipeline', 'imobiliaria');
        $this->crm->assertDeal($request->user(), $deal);

        return response()->json([
            'data' => $deal->load(['contact', 'listing:id,title,slug,city', 'assignedUser:id,name', 'activities.user:id,name', 'proposals']),
        ]);
    }

    public function moveDeal(Request $request, CrmDeal $deal): JsonResponse
    {
        $this->entitlement->assert($request->user(), 'crm_pipeline', 'imobiliaria');
        $data = $request->validate([
            'stage' => ['required', 'in:'.implode(',', DealStage::values())],
            'lock_version' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json(['data' => $this->crm->moveDeal($request->user(), $deal, $data['stage'], isset($data['lock_version']) ? (int) $data['lock_version'] : null)]);
    }

    public function updateDeal(Request $request, CrmDeal $deal): JsonResponse
    {
        $this->entitlement->assert($request->user(), 'crm_pipeline', 'imobiliaria');
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:180'],
            'crm_contact_id' => ['nullable', 'integer'],
            'commercial_listing_id' => ['nullable', 'integer'],
            'assigned_user_id' => ['nullable', 'integer'],
            'branch_id' => ['nullable', 'integer'],
            'estimated_value_cents' => ['nullable', 'integer', 'min:0'],
            'priority' => ['nullable', 'in:baixa,normal,alta'],
            'next_activity_at' => ['nullable', 'date'],
        ]);

        return response()->json(['data' => $this->crm->updateDeal($request->user(), $deal, $data)]);
    }

    public function archiveDeal(Request $request, CrmDeal $deal): JsonResponse
    {
        $this->entitlement->assert($request->user(), 'crm_pipeline', 'imobiliaria');
        $this->crm->archiveDeal($request->user(), $deal);

        return response()->json(['message' => 'Negócio arquivado']);
    }

    public function tickets(Request $request): JsonResponse
    {
        $this->entitlement->assert($request->user(), 'crm_inbox', 'imobiliaria');
        $status = $request->query('status');
        $query = $this->crm->scopedTickets($request->user())->with(['contact:id,name,phone', 'listing:id,title', 'assignedUser:id,name'])->latest('id');
        if (is_string($status) && in_array($status, TicketStatus::values(), true)) {
            $query->where('status', $status);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function storeTicket(Request $request): JsonResponse
    {
        $this->entitlement->assert($request->user(), 'crm_inbox', 'imobiliaria');
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:180'],
            'crm_contact_id' => ['nullable', 'integer'],
            'commercial_listing_id' => ['nullable', 'integer'],
            'assigned_user_id' => ['nullable', 'integer'],
            'branch_id' => ['nullable', 'integer'],
            'priority' => ['nullable', 'in:baixa,normal,alta'],
            'channel' => ['nullable', 'in:internal,whatsapp'],
            'return_at' => ['nullable', 'date'],
        ]);

        return response()->json(['data' => $this->crm->createTicket($request->user(), $data)], 201);
    }

    public function showTicket(Request $request, CrmTicket $ticket): JsonResponse
    {
        $this->entitlement->assert($request->user(), 'crm_inbox', 'imobiliaria');
        $this->crm->assertTicket($request->user(), $ticket);

        return response()->json(['data' => $ticket->load(['messages.user:id,name', 'contact', 'listing:id,title', 'assignedUser:id,name'])]);
    }

    public function messageTicket(Request $request, CrmTicket $ticket): JsonResponse
    {
        $this->entitlement->assert($request->user(), 'crm_inbox', 'imobiliaria');
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'kind' => ['nullable', 'in:message,note'],
        ]);

        return response()->json(['data' => $this->crm->addMessage($request->user(), $ticket, $data['body'], $data['kind'] ?? 'message')]);
    }

    public function updateTicket(Request $request, CrmTicket $ticket): JsonResponse
    {
        $this->entitlement->assert($request->user(), 'crm_inbox', 'imobiliaria');
        $this->crm->assertTicket($request->user(), $ticket);
        $data = $request->validate([
            'status' => ['nullable', 'in:'.implode(',', TicketStatus::values())],
            'priority' => ['nullable', 'in:baixa,normal,alta'],
            'assigned_user_id' => ['nullable', 'integer'],
            'return_at' => ['nullable', 'date'],
        ]);
        $membership = $this->organizations->requireMembership($request->user());
        $this->crm->validateReferences($request->user(), $membership, $data);
        $ticket->fill($data)->save();

        return response()->json(['data' => $ticket->fresh()]);
    }

    public function convertTicket(Request $request, CrmTicket $ticket): JsonResponse
    {
        $this->entitlement->assert($request->user(), 'crm_pipeline', 'imobiliaria');

        return response()->json(['data' => $this->crm->convertTicket($request->user(), $ticket)]);
    }

    public function roulette(Request $request): JsonResponse
    {
        $this->entitlement->assert($request->user(), 'lead_roulette', 'imobiliaria');
        $data = $request->validate([
            'strategy' => ['nullable', 'in:round_robin,team,branch,neighborhood,availability,specialty'],
            'crm_contact_id' => ['nullable', 'integer'],
            'crm_deal_id' => ['nullable', 'integer'],
            'branch_id' => ['nullable', 'integer'],
            'specialty' => ['nullable', 'string', 'max:80'],
        ]);

        return response()->json(['data' => $this->roulette->assign($request->user(), $data)]);
    }

    public function assignments(Request $request): JsonResponse
    {
        $this->entitlement->assert($request->user(), 'lead_roulette', 'imobiliaria');
        $membership = $this->organizations->requireMembership($request->user());

        return response()->json([
            'data' => LeadAssignment::query()->where('organization_id', $membership->organization_id)->latest('id')->limit(50)->get(),
        ]);
    }

    public function commissionRules(Request $request): JsonResponse
    {
        $this->entitlement->assert($request->user(), 'commissions', 'imobiliaria');
        $membership = $this->organizations->requireMembership($request->user());

        return response()->json([
            'rules' => CommissionRule::query()->where('organization_id', $membership->organization_id)->get(),
            'items' => Commission::query()->where('organization_id', $membership->organization_id)->latest('id')->get(),
        ]);
    }

    public function storeCommissionRule(Request $request): JsonResponse
    {
        $this->entitlement->assert($request->user(), 'commissions', 'imobiliaria');
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'broker_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'agency_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'branch_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        return response()->json(['data' => $this->commissions->upsertRule($request->user(), $data)]);
    }

    public function projectCommission(Request $request, CrmDeal $deal): JsonResponse
    {
        $this->entitlement->assert($request->user(), 'commissions', 'imobiliaria');

        return response()->json($this->commissions->projectForDeal($request->user(), $deal));
    }

    public function updateCommission(Request $request, Commission $commission): JsonResponse
    {
        $this->entitlement->assert($request->user(), 'commissions', 'imobiliaria');
        $data = $request->validate(['status' => ['required', 'in:projected,approved,awaiting_payment,partially_paid,paid,cancelled']]);

        return response()->json(['data' => $this->commissions->updateStatus($request->user(), $commission, $data['status'])]);
    }

    public function rouletteConfig(Request $request): JsonResponse
    {
        $this->entitlement->assert($request->user(), 'lead_roulette', 'imobiliaria');
        $membership = $this->organizations->requireMembership($request->user());
        $state = LeadRouletteState::query()->firstOrCreate(
            ['organization_id' => $membership->organization_id, 'branch_id' => null, 'strategy' => 'round_robin'],
            ['cursor' => 0, 'config' => ['strategy' => 'round_robin']],
        );

        return response()->json([
            'data' => $state,
            'assignments' => LeadAssignment::query()->where('organization_id', $membership->organization_id)->latest('id')->limit(30)->get(),
        ]);
    }

    public function updateRouletteConfig(Request $request): JsonResponse
    {
        $this->entitlement->assert($request->user(), 'lead_roulette', 'imobiliaria');
        $membership = $this->organizations->requireManager($request->user());
        $data = $request->validate([
            'strategy' => ['required', 'in:round_robin,team,branch,neighborhood,availability,specialty'],
            'branch_id' => ['nullable', 'integer'],
        ]);
        $state = LeadRouletteState::query()->updateOrCreate(
            ['organization_id' => $membership->organization_id, 'branch_id' => $data['branch_id'] ?? null, 'strategy' => $data['strategy']],
            ['config' => $data, 'cursor' => 0],
        );

        return response()->json(['data' => $state]);
    }

    public function proposals(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->entitlement->assert($user, 'broker_proposals');
        $query = CrmProposal::query()->where('user_id', $user->id);
        $membership = $this->organizations->membership($user);
        if ($membership && $this->entitlement->canAccessProduct($user, 'imobiliaria')) {
            $query = CrmProposal::query()->where(function ($inner) use ($user, $membership) {
                $inner->where('user_id', $user->id)->orWhere('organization_id', $membership->organization_id);
            });
        }

        return response()->json(['data' => $query->latest('id')->get()]);
    }

    public function storeProposal(Request $request): JsonResponse
    {
        $this->entitlement->assert($request->user(), 'broker_proposals');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'amount_cents' => ['nullable', 'integer', 'min:0'],
            'crm_contact_id' => ['nullable', 'integer'],
            'commercial_listing_id' => ['nullable', 'integer'],
            'crm_deal_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        return response()->json(['data' => $this->crm->createProposal($request->user(), $data)], 201);
    }

    public function agencySite(Request $request): JsonResponse
    {
        $this->entitlement->assert($request->user(), 'agency_site', 'imobiliaria');
        $org = $this->organizations->ensureForTenant($request->user());
        $site = AgencySite::query()->firstOrCreate(
            ['organization_id' => $org->id],
            [
                'tenant_id' => $request->user()->tenant_id,
                'slug' => Str::slug($org->slug).'-imob',
                'status' => $this->entitlement->canUseFeature($request->user(), 'public_listing', 'imobiliaria')
                    ? BrokerSiteStatus::Trial
                    : BrokerSiteStatus::Suspended,
                'theme_json' => BrokerThemeSanitizer::clean(['display_name' => $org->name]),
            ],
        );

        return response()->json(['data' => $site, 'public_path' => '/i/'.$site->slug]);
    }

    public function updateAgencySite(Request $request): JsonResponse
    {
        $this->entitlement->assert($request->user(), 'agency_site', 'imobiliaria');
        $org = $this->organizations->ensureForTenant($request->user());
        $site = AgencySite::query()->where('organization_id', $org->id)->firstOrFail();
        $data = $request->validate([
            'slug' => ['nullable', 'string', 'max:80'],
            'theme' => ['nullable', 'array'],
            'publish' => ['nullable', 'boolean'],
        ]);
        if (isset($data['slug'])) {
            $slug = Str::slug($data['slug']);
            $taken = AgencySite::withoutGlobalScope(TenantScope::class)->where('slug', $slug)->where('id', '!=', $site->id)->exists();
            abort_if($taken, 422, 'Endereço já em uso.');
            $site->slug = $slug;
        }
        if (isset($data['theme'])) {
            $theme = BrokerThemeSanitizer::clean($data['theme']);
            if (array_key_exists('publish', $data)) {
                abort_if($data['publish'] && ! $this->entitlement->canUseFeature($request->user(), 'public_listing', 'imobiliaria'), 403, 'Publicação exige assinatura ativa deste produto.');
                $theme['published'] = (bool) $data['publish'];
            }
            $site->theme_json = $theme;
        }
        $site->save();

        return response()->json(['data' => $site]);
    }
}
