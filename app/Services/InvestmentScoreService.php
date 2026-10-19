<?php

namespace App\Services;

use App\Models\InvestmentAnalysis;

class InvestmentScoreService
{
    public function calculate(InvestmentAnalysis $analysis): ?array
    {
        if (! $this->isComplete($analysis)) {
            return null;
        }

        $result = $analysis->result;
        $roi = (float) $result->roi_percentage;
        $profit = (float) $result->net_profit;
        $months = max(1, (int) $result->flip_duration_months);
        $capital = max(1, (float) $result->total_own_capital_invested);
        $financedRatio = (float) $analysis->financed_amount / max(1, (float) $analysis->purchase_price);
        $costRatio = (float) $analysis->detailed_costs_total / max(1, (float) $analysis->purchase_price);

        $score = (int) round(max(0, min(100,
            min(40, max(0, $roi) * 1.25)
            + ($profit > 0 ? 15 : 0)
            + ($months <= 6 ? 15 : ($months <= 12 ? 10 : 4))
            + ($financedRatio <= .8 ? 10 : 4)
            + ($costRatio <= .2 ? 10 : ($costRatio <= .35 ? 6 : 2))
            + ((float) $result->net_amount_received_after_payoff > $capital ? 10 : 0)
        )));
        $signal = $roi >= 20 ? ['color' => 'green', 'label' => 'Acima da meta'] : ($roi >= 10 ? ['color' => 'yellow', 'label' => 'Faixa de atenção'] : ['color' => 'red', 'label' => 'Abaixo da meta']);
        $dataCompleteness = min(100, 80 + ($analysis->costs()->count() >= 3 ? 15 : 5) + ($months > 0 ? 5 : 0));
        $reductions = collect([
            $costRatio > .2 ? ['label' => 'Custos e reforma elevados', 'impact' => -12] : null,
            $financedRatio > .8 ? ['label' => 'Financiamento elevado', 'impact' => -8] : null,
            $months > 12 ? ['label' => 'Prazo longo', 'impact' => -10] : null,
            $roi < 20 ? ['label' => 'ROI abaixo da meta', 'impact' => -15] : null,
            $profit <= 0 ? ['label' => 'Lucro líquido insuficiente', 'impact' => -20] : null,
        ])->filter()->values()->all();

        return [
            'score' => $score, 'data_completeness' => $dataCompleteness, 'signal' => $signal,
            'method' => 'rules_based_financial_calculation',
            'roi' => round($roi, 2), 'net_profit' => round($profit, 2),
            'explanation' => sprintf('O ROI líquido calculado é de %.1f%%. Considerando financiamento, custos da operação e venda, o resultado ficou na faixa %s das regras configuradas.', $roi, mb_strtolower($signal['label'])),
            'reducing_factors' => $reductions,
        ];
    }

    public function isComplete(InvestmentAnalysis $analysis): bool
    {
        return $analysis->viability_completed_at !== null
            && $analysis->financing_completed_at !== null
            && $analysis->costs_completed_at !== null
            && $analysis->result_completed_at !== null
            && $analysis->result !== null;
    }
}
