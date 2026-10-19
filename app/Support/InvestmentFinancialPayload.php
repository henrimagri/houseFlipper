<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class InvestmentFinancialPayload
{
    public static function financing(Model $financing): array
    {
        return Arr::only($financing->attributesToArray(), [
            'id', 'label', 'is_selected', 'bank_name', 'financing_system',
            'interest_rate_monthly', 'interest_rate_yearly', 'total_installments',
            'installment_value', 'paid_installments', 'outstanding_balance', 'payoff_amount',
            'created_at', 'updated_at',
        ]);
    }

    public static function cost(Model $cost): array
    {
        return Arr::only($cost->attributesToArray(), [
            'id', 'category', 'description', 'amount', 'due_date', 'paid_at',
            'payment_status', 'created_at', 'updated_at',
        ]);
    }

    public static function result(Model $result): array
    {
        return Arr::only($result->attributesToArray(), [
            'id', 'sale_price', 'sale_date', 'sale_costs', 'payoff_amount',
            'total_own_capital_invested', 'net_amount_received_after_payoff', 'net_profit',
            'roi_percentage', 'monthly_roi_percentage', 'annualized_roi_percentage',
            'flip_duration_months', 'created_at', 'updated_at',
        ]);
    }
}
