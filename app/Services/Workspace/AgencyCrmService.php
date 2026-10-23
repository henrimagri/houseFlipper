<?php

namespace App\Services\Workspace;

use App\Enums\DealStage;
use App\Enums\TicketStatus;
use App\Models\CommercialListing;
use App\Models\CrmContact;
use App\Models\CrmDeal;
use App\Models\CrmProposal;
use App\Models\CrmTicket;
use App\Models\OrganizationMembership;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AgencyCrmService
{
    public function __construct(private readonly OrganizationService $organizations) {}

    public function scopedDeals(User $user): Builder
    {
        $membership = $this->organizations->requireMembership($user);
        $query = CrmDeal::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $user->tenant_id)->where('organization_id', $membership->organization_id);
        $branch = $this->organizations->visibleBranchId($membership);
        if ($branch) {
            $query->where('branch_id', $branch);
        }

        return $query;
    }

    public function scopedTickets(User $user): Builder
    {
        $membership = $this->organizations->requireMembership($user);
        $query = CrmTicket::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $user->tenant_id)->where('organization_id', $membership->organization_id);
        $branch = $this->organizations->visibleBranchId($membership);
        if ($branch) {
            $query->where('branch_id', $branch);
        }

        return $query;
    }

    public function createDeal(User $user, array $data): CrmDeal
    {
        $membership = $this->organizations->requireMembership($user);
        $branchId = $data['branch_id'] ?? $membership->branch_id;
        $this->organizations->assertBranchAccess($membership, $branchId ? (int) $branchId : null);
        $this->validateReferences($user, $membership, $data);

        return DB::transaction(function () use ($user, $membership, $data, $branchId) {
            $deal = CrmDeal::query()->create([
                'tenant_id' => $user->tenant_id,
                'organization_id' => $membership->organization_id,
                'branch_id' => $branchId,
                'user_id' => $user->id,
                'assigned_user_id' => $data['assigned_user_id'] ?? $user->id,
                'crm_contact_id' => $data['crm_contact_id'] ?? null,
                'commercial_listing_id' => $data['commercial_listing_id'] ?? null,
                'title' => $data['title'],
                'stage' => DealStage::Novo,
                'estimated_value_cents' => $data['estimated_value_cents'] ?? null,
                'priority' => $data['priority'] ?? 'normal',
                'lock_version' => 1,
                'last_interaction_at' => now(),
            ]);
            $deal->activities()->create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'kind' => 'created',
                'to_stage' => DealStage::Novo->value,
                'body' => 'Negócio criado',
            ]);

            return $deal;
        }, 3);
    }

    public function updateDeal(User $user, CrmDeal $deal, array $data): CrmDeal
    {
        $membership = $this->assertDeal($user, $deal);
        $branchId = array_key_exists('branch_id', $data) ? $data['branch_id'] : $deal->branch_id;
        $this->organizations->assertBranchAccess($membership, $branchId ? (int) $branchId : null);
        $this->validateReferences($user, $membership, $data);
        $deal->fill(collect($data)->only([
            'title', 'crm_contact_id', 'commercial_listing_id', 'assigned_user_id',
            'branch_id', 'estimated_value_cents', 'priority', 'next_activity_at',
        ])->all());
        $deal->lock_version = ((int) $deal->lock_version) + 1;
        $deal->last_interaction_at = now();
        $deal->save();
        $deal->activities()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'kind' => 'updated',
            'body' => 'Dados do negócio atualizados',
        ]);

        return $deal->fresh(['contact', 'listing', 'assignedUser', 'activities', 'proposals']);
    }

    public function archiveDeal(User $user, CrmDeal $deal): void
    {
        $this->assertDeal($user, $deal);
        $deal->activities()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'kind' => 'archived',
            'body' => 'Negócio arquivado',
        ]);
        $deal->delete();
    }

    public function moveDeal(User $user, CrmDeal $deal, string $stage, ?int $lockVersion = null): CrmDeal
    {
        $this->assertDeal($user, $deal);

        return DB::transaction(function () use ($user, $deal, $stage, $lockVersion) {
            /** @var CrmDeal $fresh */
            $fresh = CrmDeal::query()->whereKey($deal->id)->lockForUpdate()->firstOrFail();
            if ($lockVersion !== null && (int) $fresh->lock_version !== $lockVersion) {
                abort(409, 'O negócio foi alterado por outro usuário. Recarregue o quadro.');
            }
            $from = $fresh->stage?->value;
            $fresh->stage = DealStage::from($stage);
            $fresh->last_interaction_at = now();
            $fresh->lock_version = ((int) $fresh->lock_version) + 1;
            $fresh->save();
            $fresh->activities()->create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'kind' => 'stage_changed',
                'from_stage' => $from,
                'to_stage' => $stage,
                'body' => 'Etapa alterada de '.$from.' para '.$stage,
            ]);

            return $fresh->fresh(['contact', 'listing', 'assignedUser', 'activities']);
        });
    }

    public function createTicket(User $user, array $data): CrmTicket
    {
        $membership = $this->organizations->requireMembership($user);
        $branchId = $data['branch_id'] ?? $membership->branch_id;
        $this->organizations->assertBranchAccess($membership, $branchId ? (int) $branchId : null);
        $this->validateReferences($user, $membership, $data);

        return CrmTicket::query()->create([
            'tenant_id' => $user->tenant_id,
            'organization_id' => $membership->organization_id,
            'branch_id' => $branchId,
            'user_id' => $user->id,
            'assigned_user_id' => $data['assigned_user_id'] ?? $user->id,
            'crm_contact_id' => $data['crm_contact_id'] ?? null,
            'commercial_listing_id' => $data['commercial_listing_id'] ?? null,
            'subject' => $data['subject'],
            'status' => TicketStatus::Novo,
            'priority' => in_array($data['priority'] ?? null, ['baixa', 'normal', 'alta'], true) ? $data['priority'] : 'normal',
            'channel' => $data['channel'] ?? 'internal',
            'return_at' => $data['return_at'] ?? null,
        ]);
    }

    public function addMessage(User $user, CrmTicket $ticket, string $body, string $kind = 'message'): CrmTicket
    {
        $this->assertTicket($user, $ticket);
        $channel = $kind === 'note' ? 'internal' : $ticket->channel;
        $delivery = ['status' => 'recorded'];
        if ($channel === 'whatsapp') {
            $delivery = ['status' => 'not_wired', 'reason' => 'WhatsApp Business Platform não configurada.'];
        }
        $ticket->messages()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'kind' => $kind,
            'body' => $body,
            'channel' => $channel,
            'delivery' => $delivery,
        ]);
        if ($ticket->status === TicketStatus::Novo) {
            $ticket->status = TicketStatus::EmAndamento;
        }
        $ticket->save();

        return $ticket->fresh(['messages', 'contact', 'listing', 'assignedUser']);
    }

    public function convertTicket(User $user, CrmTicket $ticket): CrmDeal
    {
        $this->assertTicket($user, $ticket);

        return DB::transaction(function () use ($user, $ticket) {
            // Reload under lock: a stale route-bound model must not create another deal.
            $fresh = $this->scopedTickets($user)->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $this->assertTicket($user, $fresh);
            if ($fresh->crm_deal_id) {
                $existing = $this->scopedDeals($user)->whereKey($fresh->crm_deal_id)->first();
                abort_unless($existing, 409, 'O negócio vinculado não está disponível. Revise o atendimento antes de converter novamente.');
                $this->assertDeal($user, $existing);

                return $existing;
            }
            $deal = $this->createDeal($user, [
                'title' => $fresh->subject,
                'branch_id' => $fresh->branch_id,
                'assigned_user_id' => $fresh->assigned_user_id,
                'crm_contact_id' => $fresh->crm_contact_id,
                'commercial_listing_id' => $fresh->commercial_listing_id,
                'priority' => $fresh->priority,
            ]);
            $fresh->crm_deal_id = $deal->id;
            $fresh->save();

            return $deal;
        }, 3);
    }

    public function createProposal(User $user, array $data): CrmProposal
    {
        $membership = $this->organizations->membership($user);
        if ($membership) {
            $this->validateReferences($user, $membership, $data);
        }

        return CrmProposal::query()->create([
            'tenant_id' => $user->tenant_id,
            'organization_id' => $membership?->organization_id,
            'user_id' => $user->id,
            'crm_contact_id' => $data['crm_contact_id'] ?? null,
            'commercial_listing_id' => $data['commercial_listing_id'] ?? null,
            'crm_deal_id' => $data['crm_deal_id'] ?? null,
            'title' => $data['title'],
            'amount_cents' => $data['amount_cents'] ?? null,
            'status' => 'draft',
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function funnel(User $user): array
    {
        $counts = $this->scopedDeals($user)
            ->selectRaw('stage, count(*) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage');

        return collect(DealStage::values())->map(fn (string $stage) => [
            'stage' => $stage,
            'count' => (int) ($counts[$stage] ?? 0),
        ])->all();
    }

    public function assertDeal(User $user, CrmDeal $deal): OrganizationMembership
    {
        $membership = $this->organizations->requireMembership($user);
        abort_if((int) $deal->tenant_id !== (int) $user->tenant_id || (int) $deal->organization_id !== (int) $membership->organization_id, 404);
        $this->organizations->assertBranchAccess($membership, $deal->branch_id ? (int) $deal->branch_id : null);

        return $membership;
    }

    public function assertTicket(User $user, CrmTicket $ticket): OrganizationMembership
    {
        $membership = $this->organizations->requireMembership($user);
        abort_if((int) $ticket->tenant_id !== (int) $user->tenant_id || (int) $ticket->organization_id !== (int) $membership->organization_id, 404);
        $this->organizations->assertBranchAccess($membership, $ticket->branch_id ? (int) $ticket->branch_id : null);

        return $membership;
    }

    public function validateReferences(User $user, OrganizationMembership $membership, array $data): void
    {
        $branch = $this->organizations->visibleBranchId($membership);
        if (! empty($data['assigned_user_id']) && ! OrganizationMembership::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $user->tenant_id)
            ->where('organization_id', $membership->organization_id)
            ->where('user_id', $data['assigned_user_id'])
            ->when($branch, fn ($query) => $query->where('branch_id', $branch))
            ->whereHas('user', fn ($query) => $query->where('tenant_id', $user->tenant_id)->where('ativo_flg', true)->where('deletado_flg', false))
            ->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['assigned_user_id' => 'Responsável não pertence à imobiliária.']);
        }
        foreach (['crm_contact_id' => CrmContact::class, 'commercial_listing_id' => CommercialListing::class] as $field => $model) {
            if (empty($data[$field])) {
                continue;
            }
            // Remove only tenant scope; archived listings must remain unavailable.
            $record = $model::withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $user->tenant_id)
                ->whereKey($data[$field])
                ->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $membership->organization_id))
                ->first();
            if (! $record || ($branch && $record->branch_id && (int) $record->branch_id !== $branch) || ! Gate::forUser($user)->allows('view', $record)) {
                throw ValidationException::withMessages([$field => 'Registro não disponível para este usuário e imobiliária.']);
            }
        }
    }
}
