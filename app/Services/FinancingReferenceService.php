<?php

namespace App\Services;

use App\Models\InvestmentAnalysis;

class FinancingReferenceService
{
    public function for(InvestmentAnalysis $analysis, ?string $bankId = null, string $system = 'SAC', int $installments = 360, ?float $principalOverride = null, int $paidInstallments = 0): array
    {
        $banks = collect($this->banks())->sortBy('yearly_rate')->values()->all();
        $bank = collect($banks)->firstWhere('id', $bankId) ?? $banks[0];
        $yearly = (float) $bank['yearly_rate'];
        $monthly = (float) $bank['monthly_rate'];
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
            'bank_id' => $bank['id'], 'bank_name' => $bank['name'], 'financing_system' => $system,
            'interest_rate_monthly' => round($monthly, 4), 'interest_rate_yearly' => round($yearly, 4),
            'total_installments' => $installments, 'installment_value' => round($installment, 2),
            'paid_installments' => $paidInstallments, 'outstanding_balance' => round($outstanding, 2),
            'payoff_amount' => round($outstanding, 2), 'reference_date' => '01/05/2026 a 31/05/2026',
            'source' => 'Banco Central do Brasil — financiamento imobiliário com taxas de mercado/TR', 'is_estimate' => true,
        ];
    }

    public function banks(): array
    {
        return collect([
            ['id' => '00360305', 'name' => 'CAIXA ECONÔMICA FEDERAL', 'monthly_rate' => 0.95, 'yearly_rate' => 12.00],
            ['id' => '60701190', 'name' => 'ITAÚ UNIBANCO S.A.', 'monthly_rate' => 0.95, 'yearly_rate' => 12.04],
            ['id' => '60746948', 'name' => 'BANCO BRADESCO S.A.', 'monthly_rate' => 0.97, 'yearly_rate' => 12.27],
            ['id' => '90400888', 'name' => 'BANCO SANTANDER (BRASIL) S.A.', 'monthly_rate' => 0.99, 'yearly_rate' => 12.54],
            ['id' => '00416968', 'name' => 'BANCO INTER', 'monthly_rate' => 1.00, 'yearly_rate' => 12.74],
            ['id' => '00000000', 'name' => 'BANCO DO BRASIL S.A.', 'monthly_rate' => 1.15, 'yearly_rate' => 14.76],
            ['id' => '02038232', 'name' => 'BANCO SICOOB S.A.', 'monthly_rate' => 0.94, 'yearly_rate' => 11.84],
            ['id' => '01181521', 'name' => 'BANCO COOPERATIVO SICREDI S.A.', 'monthly_rate' => 0.89, 'yearly_rate' => 11.27],
            ['id' => '92702067', 'name' => 'BANRISUL', 'monthly_rate' => 0.84, 'yearly_rate' => 10.52],
            ['id' => '00556603', 'name' => 'BANCO BARI S.A.', 'monthly_rate' => 0.68, 'yearly_rate' => 8.52],
        ])->sortBy('yearly_rate')->values()->all();
    }
}
