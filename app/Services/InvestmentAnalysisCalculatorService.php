<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class InvestmentAnalysisCalculatorService
{
    public function calculateForStorage(array $data): array
    {
        return $this->validateForStorage($this->calculate($data));
    }

    public function validateForStorage(array $values): array
    {
        foreach ($values as $field => $value) {
            if (! is_numeric($value)) {
                continue;
            }
            $limit = str_contains($field, 'roi_percentage') ? 99999999.9999 : 9999999999999.99;
            // The monthly and annualized ROI columns have the same precision.
            if (str_contains($field, 'roi') && str_contains($field, 'percentage')) {
                $limit = 99999999.9999;
            }
            if (! is_finite((float) $value) || abs((float) $value) > $limit) {
                throw ValidationException::withMessages([
                    'financial_calculation' => 'O cálculo excede a precisão suportada. Revise valores, capital investido e prazo da operação.',
                ]);
            }
        }

        return $values;
    }

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
            $roi >= 30 => ['excellent', 'ROI na faixa igual ou superior a 30% das regras configuradas.'],
            $roi >= 20 => ['good', 'ROI na faixa de 20% a 29,99% das regras configuradas.'],
            $roi >= 10 => ['warning', 'ROI na faixa de 10% a 19,99% das regras configuradas.'],
            default => ['rejected', 'ROI abaixo de 10% nas regras configuradas.'],
        };

        return ['financed_amount' => round($financed, 2), 'total_own_capital_invested' => round($capital, 2), 'expected_net_profit' => round($profit, 2), 'expected_roi_percentage' => round($roi, 4), 'expected_monthly_roi_percentage' => round($monthly, 4), 'expected_annualized_roi_percentage' => round($annualized, 4), 'decision' => $decision, 'decision_reason' => $reason];
    }
}
