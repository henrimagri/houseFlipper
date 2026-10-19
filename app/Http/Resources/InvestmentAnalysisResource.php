<?php

namespace App\Http\Resources;

use App\Services\InvestmentCapitalService;
use App\Services\InvestmentIntelligenceService;
use App\Support\InvestmentFinancialPayload;
use App\Support\OpportunityPhotoUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class InvestmentAnalysisResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $actualCapital = app(InvestmentCapitalService::class)->actual($this->resource);

        return array_merge(Arr::only($this->resource->attributesToArray(), [
            'id', 'opportunity_id', 'projeto_id', 'status', 'is_primary',
            'purchase_price', 'down_payment', 'financed_amount', 'renovation_estimate',
            'documentation_costs', 'taxes_costs', 'broker_commission', 'detailed_costs_total',
            'expected_sale_price', 'expected_monthly_costs', 'expected_installment_value',
            'expected_outstanding_balance', 'total_own_capital_invested', 'expected_net_profit',
            'expected_months_until_sale', 'expected_roi_percentage',
            'expected_monthly_roi_percentage', 'expected_annualized_roi_percentage',
            'decision', 'decision_reason', 'property_condition', 'market_region',
            'average_price_per_m2', 'average_sold_value', 'suggested_sale_price',
            'viability_completed_at', 'financing_completed_at', 'costs_completed_at',
            'result_completed_at', 'created_at', 'updated_at',
        ]), [
            'actual_own_capital_invested' => $actualCapital,
            'opportunity' => $this->whenLoaded('opportunity', fn () => ['id' => $this->opportunity->id, 'title' => $this->opportunity->title, 'city' => $this->opportunity->city, 'state' => $this->opportunity->state, 'asking_price' => $this->opportunity->asking_price, 'estimated_market_price' => $this->opportunity->estimated_market_price, 'area_m2' => $this->opportunity->area_m2, 'cover_url' => $this->opportunity->coverPhoto?->path ? $this->storageUrl($this->opportunity->coverPhoto->path) : null]),
            'financing_detail' => $this->whenLoaded('financingDetail', fn () => $this->financingDetail ? InvestmentFinancialPayload::financing($this->financingDetail) : null),
            'financing_simulations' => $this->whenLoaded('financingSimulations', fn () => $this->financingSimulations->map(fn ($simulation) => InvestmentFinancialPayload::financing($simulation))->values()),
            'costs' => $this->whenLoaded('costs', fn () => $this->costs->map(fn ($cost) => InvestmentFinancialPayload::cost($cost))->values()),
            'result' => $this->whenLoaded('result', fn () => $this->result ? InvestmentFinancialPayload::result($this->result) : null),
            'intelligence' => app(InvestmentIntelligenceService::class)->analyze($this->resource),
            'progress' => ['viability' => $this->viability_completed_at !== null, 'financing' => $this->financing_completed_at !== null, 'costs' => $this->costs_completed_at !== null, 'result' => $this->result_completed_at !== null],
            'executive' => ['own_capital' => $actualCapital, 'expected_profit' => (float) $this->expected_net_profit, 'realized_profit' => (float) ($this->result?->net_profit ?? 0), 'roi' => (float) ($this->result?->roi_percentage ?? $this->expected_roi_percentage), 'monthly_roi' => (float) ($this->result?->monthly_roi_percentage ?? $this->expected_monthly_roi_percentage), 'annualized_roi' => (float) ($this->result?->annualized_roi_percentage ?? $this->expected_annualized_roi_percentage), 'financed_capital' => (float) $this->financed_amount, 'tied_capital' => $actualCapital],
        ]);
    }

    private function storageUrl(string $path): ?string
    {
        return OpportunityPhotoUrl::for($path);
    }
}
