<?php

namespace App\Http\Controllers\Api;

use App\Enums\CommercialListingStatus;
use App\Enums\DealStage;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CommercialListing;
use App\Models\Commission;
use App\Models\CrmContact;
use App\Models\CrmDeal;
use App\Models\CrmProposal;
use App\Models\OrganizationMembership;
use App\Services\Ai\ProductEntitlement;
use App\Services\Workspace\AgencyCrmService;
use App\Services\Workspace\OrganizationService;
use App\Support\MetricPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgencyDashboardController extends Controller
{
    public function __construct(
        private readonly ProductEntitlement $entitlement,
        private readonly OrganizationService $organizations,
        private readonly AgencyCrmService $crm,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->entitlement->assertProduct($user, 'imobiliaria');
        $org = $this->organizations->ensureForTenant($user);
        $membership = $this->organizations->requireMembership($user);
        $forcedBranch = $this->organizations->visibleBranchId($membership);
        [$from, $to] = MetricPeriod::fromRequest($request);
        $branchId = $forcedBranch ?: ($request->integer('branch_id') ?: null);
        $agentId = $request->integer('assigned_user_id') ?: null;

        $memberIds = OrganizationMembership::query()->where('organization_id', $org->id)->pluck('user_id');
        $listings = CommercialListing::query()->where(function ($query) use ($org, $memberIds) {
            $query->where('organization_id', $org->id)->orWhereIn('user_id', $memberIds);
        });
        $contacts = CrmContact::query()->where(function ($query) use ($org, $memberIds) {
            $query->where('organization_id', $org->id)->orWhereIn('user_id', $memberIds);
        });
        $deals = $this->crm->scopedDeals($user);
        $tickets = $this->crm->scopedTickets($user);
        if ($branchId) {
            $listings->where(function ($query) use ($branchId) {
                $query->where('branch_id', $branchId)->orWhereNull('branch_id');
            });
            $contacts->where(function ($query) use ($branchId) {
                $query->where('branch_id', $branchId)->orWhereNull('branch_id');
            });
            $deals->where('branch_id', $branchId);
            $tickets->where('branch_id', $branchId);
        }
        if ($agentId) {
            $listings->where('user_id', $agentId);
            $contacts->where(function ($query) use ($agentId) {
                $query->where('assigned_user_id', $agentId)->orWhere('user_id', $agentId);
            });
            $deals->where('assigned_user_id', $agentId);
            $tickets->where('assigned_user_id', $agentId);
        }

        $closed = (clone $deals)->where('stage', DealStage::Fechamento->value)->whereBetween('updated_at', [$from, $to])->count();
        $eligible = (clone $deals)->whereBetween('created_at', [$from, $to])->count();

        return response()->json([
            'organization' => ['id' => $org->id, 'name' => $org->name, 'slug' => $org->slug],
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'branch_id' => $branchId,
                'assigned_user_id' => $agentId,
                'locked_branch' => (bool) $forcedBranch,
            ],
            'definitions' => [
                'listings' => 'Imóveis da organização ou dos membros ativos.',
                'new_leads' => 'Contatos criados no intervalo filtrado.',
                'unanswered' => 'Contatos com status new no momento da consulta.',
                'open_tickets' => 'Atendimentos cujo status não é encerrado.',
                'closed_deals' => 'Negócios em fechamento atualizados no intervalo.',
                'conversion' => 'Fechamentos do intervalo / negócios criados no mesmo intervalo.',
                'commissions' => 'Soma de comissões com status projected. Não significa pagamento.',
            ],
            'kpis' => [
                'listings' => (clone $listings)->count(),
                'available' => (clone $listings)->whereIn('status', [CommercialListingStatus::Draft->value, CommercialListingStatus::Ready->value, CommercialListingStatus::Published->value])->count(),
                'published' => (clone $listings)->where('status', CommercialListingStatus::Published->value)->count(),
                'pending_captures' => 0,
                'contacts' => (clone $contacts)->count(),
                'new_leads' => (clone $contacts)->whereBetween('created_at', [$from, $to])->count(),
                'unanswered' => (clone $contacts)->where('status', 'new')->count(),
                'open_tickets' => (clone $tickets)->where('status', '!=', TicketStatus::Encerrado->value)->count(),
                'open_deals' => (clone $deals)->whereNotIn('stage', [DealStage::Fechamento->value, DealStage::Perdido->value])->count(),
                'open_proposals' => CrmProposal::query()->where('organization_id', $org->id)->whereNotIn('status', ['won', 'lost', 'cancelled'])->count(),
                'closed_deals' => $closed,
                'conversion_pct' => $eligible > 0 ? round($closed / $eligible * 100, 2) : 0,
                'projected_commissions_cents' => (int) Commission::query()->where('organization_id', $org->id)->where('status', 'projected')->sum('amount_cents'),
                'teams' => OrganizationMembership::query()->where('organization_id', $org->id)->where('status', 'active')->count(),
                'branches' => Branch::query()->where('organization_id', $org->id)->count(),
            ],
            'funnel' => $this->crm->funnel($user),
            'lead_sources' => (clone $contacts)->selectRaw('source, count(*) as total')->groupBy('source')->get(),
            'by_agent' => CrmDeal::query()
                ->where('organization_id', $org->id)
                ->selectRaw('assigned_user_id, count(*) as deals, coalesce(sum(estimated_value_cents),0) as value_cents')
                ->groupBy('assigned_user_id')
                ->with('assignedUser:id,name')
                ->get(),
            'urgent' => [
                'unassigned_tickets' => (clone $tickets)->whereNull('assigned_user_id')->where('status', '!=', TicketStatus::Encerrado->value)->limit(8)->get(['id', 'subject', 'status', 'priority', 'created_at']),
                'overdue_returns' => (clone $tickets)->whereNotNull('return_at')->where('return_at', '<', now())->where('status', '!=', TicketStatus::Encerrado->value)->limit(8)->get(['id', 'subject', 'return_at', 'status']),
                'waiting_proposals' => CrmProposal::query()->where('organization_id', $org->id)->where('status', 'draft')->latest('id')->limit(8)->get(['id', 'title', 'status', 'amount_cents']),
            ],
            'branches_index' => Branch::query()->where('organization_id', $org->id)->get(['id', 'name', 'city']),
            'members_index' => OrganizationMembership::query()->where('organization_id', $org->id)->where('status', 'active')->with('user:id,name')->get(),
        ]);
    }
}
