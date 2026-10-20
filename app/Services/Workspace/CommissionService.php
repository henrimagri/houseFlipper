<?php

namespace App\Services\Workspace;

use App\Enums\DealStage;
use App\Models\Commission;
use App\Models\CommissionRule;
use App\Models\CrmDeal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CommissionService
{
    public function __construct(private readonly OrganizationService $organizations) {}

    public function upsertRule(User $actor, array $data): CommissionRule
    {
        $membership = $this->organizations->requireManager($actor);

        return CommissionRule::query()->updateOrCreate(
            [
                'organization_id' => $membership->organization_id,
                'branch_id' => $data['branch_id'] ?? null,
            ],
            [
                'tenant_id' => $actor->tenant_id,
                'broker_percent' => $data['broker_percent'] ?? 0,
                'agency_percent' => $data['agency_percent'] ?? 0,
                'branch_percent' => $data['branch_percent'] ?? 0,
                'active' => true,
            ],
        );
    }

    public function projectForDeal(User $actor, CrmDeal $deal): array
    {
        $membership = $this->organizations->requireMembership($actor);
        abort_if((int) $deal->organization_id !== (int) $membership->organization_id, 404);
        $this->organizations->assertBranchAccess($membership, $deal->branch_id ? (int) $deal->branch_id : null);

        if ($deal->stage !== DealStage::Fechamento || ! $deal->estimated_value_cents) {
            return ['projected' => false, 'reason' => 'Comissão só é projetada em fechamento com valor informado.', 'items' => []];
        }

        $rule = CommissionRule::query()
            ->where('organization_id', $deal->organization_id)
            ->where('active', true)
            ->orderByRaw('branch_id is not null desc')
            ->when($deal->branch_id, fn ($q) => $q->where(fn ($inner) => $inner->where('branch_id', $deal->branch_id)->orWhereNull('branch_id')))
            ->first();
        if (! $rule) {
            return ['projected' => false, 'reason' => 'Nenhuma regra de comissão configurada.', 'items' => []];
        }

        return DB::transaction(function () use ($deal, $rule) {
            $rows = [];
            $parts = [
                ['kind' => 'broker', 'percent' => (float) $rule->broker_percent, 'user_id' => $deal->assigned_user_id],
                ['kind' => 'agency', 'percent' => (float) $rule->agency_percent, 'user_id' => null],
                ['kind' => 'branch', 'percent' => (float) $rule->branch_percent, 'user_id' => null],
            ];
            foreach ($parts as $part) {
                if ($part['percent'] <= 0) {
                    continue;
                }
                $amount = (int) round($deal->estimated_value_cents * $part['percent'] / 100);
                $row = Commission::query()->updateOrCreate(
                    [
                        'crm_deal_id' => $deal->id,
                        'kind' => $part['kind'],
                    ],
                    [
                        'tenant_id' => $deal->tenant_id,
                        'organization_id' => $deal->organization_id,
                        'branch_id' => $deal->branch_id,
                        'user_id' => $part['user_id'],
                        'amount_cents' => $amount,
                        'status' => 'projected',
                        'settled_at' => null,
                    ],
                );
                $rows[] = $row;
            }

            return ['projected' => true, 'items' => $rows];
        });
    }

    public function updateStatus(User $actor, Commission $commission, string $status): Commission
    {
        $membership = $this->organizations->requireManager($actor);
        abort_if((int) $commission->organization_id !== (int) $membership->organization_id, 404);
        $current = $commission->status;
        $allowed = [
            'projected' => ['approved', 'cancelled'],
            'approved' => ['awaiting_payment', 'cancelled'],
            'awaiting_payment' => ['partially_paid', 'paid', 'cancelled'],
            'partially_paid' => ['paid', 'cancelled'],
            'paid' => [],
            'cancelled' => [],
        ];
        abort_unless(in_array($status, $allowed[$current] ?? [], true), 422, 'Transição de comissão inválida.');
        $commission->status = $status;
        $commission->settled_at = $status === 'paid' ? now() : $commission->settled_at;
        $commission->save();

        return $commission->fresh();
    }
}
