<?php

namespace App\Services\Ai;

use App\Models\CrmContact;
use App\Models\CrmDeal;
use App\Models\Opportunity;
use App\Models\OrganizationMembership;
use App\Models\User;

class AiOperationalTools
{
    public function crmSummary(User $user): array
    {
        $membership = OrganizationMembership::query()->where('user_id', $user->id)->where('status', 'active')->first();
        abort_unless($membership, 403, 'É necessário pertencer a uma imobiliária ativa.');
        $contacts = CrmContact::query()->where(fn ($query) => $query->where('organization_id', $membership->organization_id)->orWhere('user_id', $user->id));
        $deals = CrmDeal::query()->where('organization_id', $membership->organization_id);

        return [
            'contacts' => (clone $contacts)->count(),
            'contacts_with_consent' => (clone $contacts)->where('consent', true)->count(),
            'deals' => (clone $deals)->count(),
            'pipeline_value_cents' => (int) (clone $deals)->sum('estimated_value_cents'),
            'overdue_next_actions' => (clone $contacts)->whereNotNull('next_action_at')->where('next_action_at', '<', now())->count(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    public function investmentSummary(User $user): array
    {
        $items = Opportunity::query()->where('user_id', $user->id)->where('is_archived', false)->get(['asking_price', 'estimated_market_price']);
        $known = $items->filter(fn (Opportunity $item) => (float) $item->asking_price > 0 && (float) $item->estimated_market_price > 0);

        return [
            'opportunities' => $items->count(),
            'with_market_estimate' => $known->count(),
            'asking_total' => round((float) $known->sum('asking_price'), 2),
            'estimated_market_total' => round((float) $known->sum('estimated_market_price'), 2),
            'gross_spread_total' => round((float) $known->sum(fn (Opportunity $item) => (float) $item->estimated_market_price - (float) $item->asking_price), 2),
            'warning' => 'O spread bruto não desconta reforma, impostos, financiamento, corretagem nem prazo. Não é ROI líquido.',
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
