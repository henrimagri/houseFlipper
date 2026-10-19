<?php

namespace App\Services;

use App\Models\InvestmentAnalysis;

class FinancingReferenceService
{
    public function for(InvestmentAnalysis $analysis, ?string $bankId = null, string $system = 'SAC', int $installments = 360, ?float $principalOverride = null, int $paidInstallments = 0, ?float $monthlyRateOverride = null, ?string $bankName = null): array
    {
        $monthly = max(0, (float) ($monthlyRateOverride ?? 0));
        $yearly = ((1 + $monthly / 100) ** 12 - 1) * 100;
        $principal = max(0, $principalOverride ?? (float) $analysis->financed_amount);
        $installments = max(1, $installments);
        $paidInstallments = min(max(0, $paidInstallments), $installments);
        $rate = $monthly / 100;
        $installment = $system === 'PRICE' && $rate > 0
            ? $principal * ($rate * (1 + $rate) ** $installments) / ((1 + $rate) ** $installments - 1)
            : ($principal / $installments) + ($principal * $rate);
        if ($system === 'PRICE' && $rate > 0) {
            $outstanding = $principal * (1 + $rate) ** $paidInstallments - $installment * (((1 + $rate) ** $paidInstallments - 1) / $rate);
        } else {
            $outstanding = $principal - ($principal / $installments) * $paidInstallments;
            $installment = ($principal / $installments) + max(0, $outstanding) * $rate;
        }
        $outstanding = max(0, $outstanding);

        return [
            'bank_id' => null, 'bank_name' => filled($bankName) ? trim($bankName) : null, 'financing_system' => $system,
            'interest_rate_monthly' => round($monthly, 4), 'interest_rate_yearly' => round($yearly, 4),
            'total_installments' => $installments, 'installment_value' => round($installment, 2),
            'paid_installments' => $paidInstallments, 'outstanding_balance' => round($outstanding, 2),
            'payoff_amount' => round($outstanding, 2), 'reference_date' => null,
            'source' => 'Simulação com taxa informada pelo usuário; confirme CET, seguros, TR e condições com a instituição.', 'is_estimate' => true,
        ];
    }

    public function banks(): array
    {
        return [];
    }
}
