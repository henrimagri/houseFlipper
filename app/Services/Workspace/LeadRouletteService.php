<?php

namespace App\Services\Workspace;

use App\Models\CrmContact;
use App\Models\CrmDeal;
use App\Models\LeadAssignment;
use App\Models\LeadRouletteState;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LeadRouletteService
{
    private string $lastReason = '';

    public function __construct(private readonly OrganizationService $organizations) {}

    public function assign(User $actor, array $data): LeadAssignment
    {
        $membership = $this->organizations->requireMembership($actor);
        $strategy = $data['strategy'] ?? 'round_robin';
        $allowed = ['round_robin', 'team', 'branch', 'neighborhood', 'availability', 'specialty'];
        if (! in_array($strategy, $allowed, true)) {
            abort(422, 'Estratégia de roleta inválida.');
        }

        $contact = null;
        $deal = null;
        if (! empty($data['crm_contact_id'])) {
            $contact = CrmContact::query()->where('tenant_id', $actor->tenant_id)->whereKey($data['crm_contact_id'])->firstOrFail();
        }
        if (! empty($data['crm_deal_id'])) {
            $deal = CrmDeal::query()->where('organization_id', $membership->organization_id)->whereKey($data['crm_deal_id'])->firstOrFail();
        }

        $assignee = $this->pick($membership, $strategy, $data, $contact);
        abort_if(! $assignee, 422, 'Nenhum corretor ativo elegível para distribuição.');

        return DB::transaction(function () use ($actor, $membership, $contact, $deal, $assignee, $strategy) {
            if ($contact) {
                $contact->assigned_user_id = $assignee->id;
                $contact->save();
            }
            if ($deal) {
                $deal->assigned_user_id = $assignee->id;
                $deal->save();
            }

            return LeadAssignment::query()->create([
                'tenant_id' => $actor->tenant_id,
                'organization_id' => $membership->organization_id,
                'branch_id' => $deal?->branch_id ?? $contact?->branch_id ?? $membership->branch_id,
                'crm_contact_id' => $contact?->id,
                'crm_deal_id' => $deal?->id,
                'from_user_id' => $actor->id,
                'to_user_id' => $assignee->id,
                'strategy' => $strategy,
                'reason' => $this->lastReason ?: 'Distribuição automática',
            ]);
        });
    }

    private function pick(OrganizationMembership $membership, string $strategy, array $data, ?CrmContact $contact): ?User
    {
        $query = OrganizationMembership::query()
            ->where('organization_id', $membership->organization_id)
            ->where('status', 'active')
            ->whereHas('user', fn ($user) => $user->where('ativo_flg', true)->where('deletado_flg', false));

        $branchId = $data['branch_id'] ?? $contact?->branch_id;
        if (in_array($strategy, ['branch', 'round_robin', 'availability'], true) && $branchId) {
            $query->where(function ($inner) use ($branchId) {
                $inner->where('branch_id', $branchId)->orWhereNull('branch_id');
            });
        }
        if ($strategy === 'neighborhood' && filled($contact?->city ?? null)) {
            // Neighborhood filter uses branch city when present; otherwise falls back to org-wide active agents.
            $query->where(function ($inner) use ($contact) {
                $inner->whereNull('branch_id')->orWhereHas('branch', fn ($branch) => $branch->where('city', $contact->city));
            });
        }
        if ($strategy === 'specialty' && filled($data['specialty'] ?? null)) {
            $specialty = $data['specialty'];
            $query->whereJsonContains('specialties', $specialty);
        }

        $candidates = $query->with('user:id,name')->orderBy('id')->get();
        if ($candidates->isEmpty()) {
            return null;
        }

        $state = LeadRouletteState::query()->firstOrCreate(
            [
                'organization_id' => $membership->organization_id,
                'branch_id' => $branchId,
                'strategy' => $strategy,
            ],
            ['cursor' => 0, 'config' => []],
        );
        $index = $state->cursor % $candidates->count();
        $chosen = $candidates->values()->get($index);
        $state->cursor = $state->cursor + 1;
        $state->save();
        $this->lastReason = sprintf(
            'Estratégia %s. %d corretores ativos elegíveis. Cursor %d → %s. Sem usuários inativos e restrito à organização %d.',
            $strategy,
            $candidates->count(),
            $index + 1,
            $chosen?->user?->name ?? 'corretor',
            $membership->organization_id,
        );

        return $chosen?->user;
    }

    public function lastReason(): string
    {
        return $this->lastReason;
    }
}
