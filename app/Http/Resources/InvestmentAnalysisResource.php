<?php

namespace App\Http\Resources;

use App\Services\InvestmentCapitalService;
use App\Services\InvestmentIntelligenceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvestmentAnalysisResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $actualCapital = app(InvestmentCapitalService::class)->actual($this->resource);

        return array_merge($this->resource->attributesToArray(), [
            'actual_own_capital_invested' => $actualCapital,
            'opportunity' => $this->whenLoaded('opportunity', fn () => ['id' => $this->opportunity->id, 'title' => $this->opportunity->title, 'city' => $this->opportunity->city, 'state' => $this->opportunity->state, 'asking_price' => $this->opportunity->asking_price, 'estimated_market_price' => $this->opportunity->estimated_market_price, 'area_m2' => $this->opportunity->area_m2, 'cover_url' => $this->opportunity->coverPhoto?->path ? $this->storageUrl($this->opportunity->coverPhoto->path) : null]),
            'financing_detail' => $this->whenLoaded('financingDetail'),
            'financing_simulations' => $this->whenLoaded('financingSimulations'),
            'costs' => $this->whenLoaded('costs'), 'result' => $this->whenLoaded('result'),
            'intelligence' => app(InvestmentIntelligenceService::class)->analyze($this->resource),
            'progress' => ['viability' => $this->viability_completed_at !== null, 'financing' => $this->financing_completed_at !== null, 'costs' => $this->costs_completed_at !== null, 'result' => $this->result_completed_at !== null],
            'executive' => ['own_capital' => $actualCapital, 'expected_profit' => (float) $this->expected_net_profit, 'realized_profit' => (float) ($this->result?->net_profit ?? 0), 'roi' => (float) ($this->result?->roi_percentage ?? $this->expected_roi_percentage), 'monthly_roi' => (float) ($this->result?->monthly_roi_percentage ?? $this->expected_monthly_roi_percentage), 'annualized_roi' => (float) ($this->result?->annualized_roi_percentage ?? $this->expected_annualized_roi_percentage), 'financed_capital' => (float) $this->financed_amount, 'tied_capital' => $actualCapital],
        ]);
    }

    private function storageUrl(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return '/storage/'.ltrim($path, '/');
    }
}
