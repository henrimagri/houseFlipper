<?php

namespace App\Services;

class InvestmentAnalysisCalculatorService
{
    public function calculate(array $data): array
    {
        $money = fn (string $key): float => (float) ($data[$key] ?? 0);
        $months = max(1, (int) ($data['expected_months_until_sale'] ?? 1));
        $financed = max(0, $money('purchase_price') - $money('down_payment'));
        $capital = $money('down_payment') + $money('detailed_costs_total') + ($money('expected_installment_value') * $months);
        $netReceived = $money('expected_sale_price') - $money('expected_outstanding_balance');
        $profit = $netReceived - $capital;
        $roi = $capital > 0 ? ($profit / $capital) * 100 : 0;
        $growthFactor = max(0, 1 + $roi / 100);
        $monthly = $growthFactor > 0 ? (($growthFactor ** (1 / $months)) - 1) * 100 : -100;
        $annualized = $growthFactor > 0 ? (($growthFactor ** (12 / $months)) - 1) * 100 : -100;
        [$decision, $reason] = match (true) {
            $roi >= 30 => ['excellent', 'Excelente negócio. Alto retorno sobre o capital investido.'],
            $roi >= 20 => ['good', 'Bom negócio. Retorno atrativo para operação de flip.'],
            $roi >= 10 => ['warning', 'Atenção. Margem apertada, revisar custos e preço de venda.'],
            default => ['rejected', 'Reprovado. Retorno baixo para o risco da operação.'],
        };

        return ['financed_amount' => round($financed, 2), 'total_own_capital_invested' => round($capital, 2), 'expected_net_profit' => round($profit, 2), 'expected_roi_percentage' => round($roi, 4), 'expected_monthly_roi_percentage' => round($monthly, 4), 'expected_annualized_roi_percentage' => round($annualized, 4), 'decision' => $decision, 'decision_reason' => $reason];
    }
}
