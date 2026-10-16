<?php

namespace App\Services;

use App\Models\InvestmentAnalysis;

class InvestmentIntelligenceService
{
    public function __construct(
        private readonly InvestmentAnalysisCalculatorService $calculator,
        private readonly InvestmentCapitalService $capital,
        private readonly OpportunityScoreService $opportunityScore,
        private readonly InvestmentScoreService $investmentScore,
    ) {}

    public function analyze(InvestmentAnalysis $analysis): array
    {
        $analysis->loadMissing(['opportunity', 'result']);
        $opportunityScore = $this->opportunityScore->calculate($analysis);
        $investmentScore = $this->investmentScore->calculate($analysis);
        $opportunity = $analysis->opportunity;
        $asking = (float) ($opportunity->asking_price ?: $analysis->purchase_price);
        $market = (float) ($opportunity->estimated_market_price ?: $analysis->expected_sale_price);
        $discount = $market > 0 ? (($market - $asking) / $market) * 100 : 0;
        $suggested = $this->suggestedSalePrice($analysis);
        $scenarios = [
            'pessimistic' => $this->scenario($analysis, 'Pessimista', $suggested * .90, 1.15, 2),
            'realistic' => $this->scenario($analysis, 'Realista', $suggested, 1, 0),
            'optimistic' => $this->scenario($analysis, 'Otimista', $suggested * 1.08, .95, -1),
        ];
        $roi = (float) ($analysis->result?->roi_percentage ?? $analysis->expected_roi_percentage);
        $months = max(1, (int) ($analysis->result?->flip_duration_months ?? $analysis->expected_months_until_sale));
        $purchase = max(1, (float) $analysis->purchase_price);
        $ownCapitalRatio = (float) ($analysis->result?->total_own_capital_invested ?? $analysis->total_own_capital_invested) / $purchase;
        $financedRatio = (float) $analysis->financed_amount / $purchase;
        $appreciation = (($suggested - $purchase) / $purchase) * 100;
        $conditionScore = match ($analysis->property_condition) {
            'excellent' => 10, 'good' => 8, 'fair' => 5, 'poor' => 2, default => 5
        };
        $score = (int) round(max(0, min(100,
            min(25, max(0, $roi) * .6) + min(20, max(0, $discount)) +
            ($months <= 6 ? 15 : ($months <= 9 ? 11 : ($months <= 12 ? 7 : 3))) +
            ($months <= 6 ? 10 : ($months <= 12 ? 6 : 3)) +
            ($ownCapitalRatio <= .35 ? 10 : ($ownCapitalRatio <= .55 ? 7 : 4)) +
            $conditionScore + ($financedRatio <= .8 ? 5 : 2) + min(5, max(0, $appreciation) / 5)
        )));
        $filled = collect(['purchase_price', 'down_payment', 'expected_sale_price', 'expected_months_until_sale', 'detailed_costs_total'])->filter(fn ($field) => (float) $analysis->{$field} > 0)->count();
        $confidence = min(98, 55 + ($filled * 7) + ($analysis->financingDetail()->exists() ? 8 : 0));
        $signal = $roi >= 20 ? ['color' => 'green', 'label' => 'Comprar'] : ($roi >= 10 ? ['color' => 'yellow', 'label' => 'Negociar'] : ['color' => 'red', 'label' => 'Não comprar']);
        $potential = $score >= 85 ? 'Excelente' : ($score >= 70 ? 'Bom' : ($score >= 50 ? 'Atenção' : 'Baixo'));
        $explanation = sprintf('%s. O imóvel está %.1f%% abaixo do valor de mercado e o ROI sobre o capital próprio é de %.1f%%, com retorno estimado em %d meses.', $signal['label'] === 'Comprar' ? 'Oportunidade atrativa' : 'A operação exige revisão', $discount, $roi, $months);

        return [
            'opportunity_score' => $opportunityScore,
            'investment_score' => $investmentScore,
            'financial_analysis_complete' => $investmentScore !== null,
            'discount_percentage' => $opportunityScore['discount_percentage'],
            'score' => $opportunityScore['score'],
            'potential' => $opportunityScore['potential'],
            'confidence' => $investmentScore['confidence'] ?? 0,
            'signal' => $investmentScore['signal'] ?? ['color' => 'slate', 'label' => 'Análise financeira pendente'],
            'explanation' => $investmentScore['explanation'] ?? $opportunityScore['disclaimer'],
            'recommendation' => $investmentScore['signal']['label'] ?? null,
            'suggested_sale_price' => round($suggested, 2),
            'scenarios' => $scenarios,
            'actual_own_capital' => $this->capital->actual($analysis),
        ];
    }

    private function suggestedSalePrice(InvestmentAnalysis $analysis): float
    {
        $byArea = (float) $analysis->average_price_per_m2 * (float) ($analysis->opportunity->area_m2 ?? 0);

        return max((float) $analysis->average_sold_value, $byArea, (float) $analysis->expected_sale_price, (float) $analysis->opportunity->estimated_market_price);
    }

    private function scenario(InvestmentAnalysis $analysis, string $label, float $salePrice, float $costMultiplier, int $monthDelta): array
    {
        $data = $analysis->toArray();
        $data['expected_sale_price'] = $salePrice;
        $data['detailed_costs_total'] = (float) $analysis->detailed_costs_total * $costMultiplier;
        $data['expected_months_until_sale'] = max(1, (int) $analysis->expected_months_until_sale + $monthDelta);
        $result = $this->calculator->calculate($data);

        return ['label' => $label, 'sale_price' => round($salePrice, 2), 'profit' => $result['expected_net_profit'], 'roi' => $result['expected_roi_percentage'], 'months' => $data['expected_months_until_sale']];
    }
}
