<?php

namespace App\Services;

use App\Models\InvestmentAnalysis;

class InvestmentCapitalService
{
    public function actual(InvestmentAnalysis $analysis, ?int $monthsOverride = null): float
    {
        $months = max(1, (int) ($monthsOverride ?? $analysis->expected_months_until_sale));
        $monthlyCategories = ['condominium', 'property_tax', 'water', 'energy', 'internet', 'insurance'];
        $monthlyDescriptions = ['Custos operacionais mensais', 'Funcionário', 'Contador', 'Outros operacionais'];
        $operationCosts = $analysis->costs()
            ->whereNotIn('category', ['down_payment', 'financing_installment'])
            ->get()
            ->sum(function ($cost) use ($months, $monthlyCategories, $monthlyDescriptions) {
                $amount = (float) $cost->amount;
                $isMonthly = in_array($cost->category, $monthlyCategories, true) || in_array($cost->description, $monthlyDescriptions, true);

                return $isMonthly ? $amount * $months : $amount;
            });
        $financing = $analysis->financingDetail()->first();
        $paidInstallments = $financing ? (float) $financing->installment_value * (int) $financing->paid_installments : 0;

        return round((float) $analysis->down_payment + $operationCosts + $paidInstallments, 2);
    }
}
