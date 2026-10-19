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
        $suggested = $this->suggestedSalePrice($analysis);
        $informedSalePrice = max(0, (float) $analysis->expected_sale_price);
        $scenarios = [
            'pessimistic' => $this->scenario($analysis, 'Sensibilidade desfavorável', $informedSalePrice, .90, 1.15, 2),
            'realistic' => $this->scenario($analysis, 'Premissas informadas', $informedSalePrice, 1, 1, 0),
            'optimistic' => $this->scenario($analysis, 'Sensibilidade favorável', $informedSalePrice, 1.08, .95, -1),
        ];

        return [
            'opportunity_score' => $opportunityScore,
            'investment_score' => $investmentScore,
            'financial_analysis_complete' => $investmentScore !== null,
            'discount_percentage' => $opportunityScore['discount_percentage'],
            'score' => $opportunityScore['score'],
            'potential' => $opportunityScore['potential'],
            'data_completeness' => $investmentScore['data_completeness'] ?? 0,
            'analysis_method' => 'rules_based_financial_calculation',
            'signal' => $investmentScore['signal'] ?? ['color' => 'slate', 'label' => 'Análise financeira pendente'],
            'explanation' => $investmentScore['explanation'] ?? $opportunityScore['disclaimer'],
            'recommendation' => null,
            'rule_signal' => $investmentScore['signal']['label'] ?? null,
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

    private function scenario(InvestmentAnalysis $analysis, string $label, float $baseSalePrice, float $saleMultiplier, float $costMultiplier, int $monthDelta): array
    {
        $salePrice = $baseSalePrice * $saleMultiplier;
        $data = $analysis->toArray();
        $data['expected_sale_price'] = $salePrice;
        $data['detailed_costs_total'] = (float) $analysis->detailed_costs_total * $costMultiplier;
        $data['expected_months_until_sale'] = max(1, (int) $analysis->expected_months_until_sale + $monthDelta);
        $result = $this->calculator->calculate($data);

        return [
            'label' => $label,
            'sale_price' => round($salePrice, 2),
            'profit' => $result['expected_net_profit'],
            'roi' => $result['expected_roi_percentage'],
            'months' => $data['expected_months_until_sale'],
            'assumptions' => [
                'sale_price_change_percentage' => round(($saleMultiplier - 1) * 100, 2),
                'cost_change_percentage' => round(($costMultiplier - 1) * 100, 2),
                'duration_change_months' => $monthDelta,
                'basis' => 'expected_sale_price_and_costs_informed_by_user',
            ],
        ];
    }
}
