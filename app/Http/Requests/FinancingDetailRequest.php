<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinancingDetailRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $fields = ['interest_rate_monthly', 'interest_rate_yearly', 'total_installments', 'installment_value', 'paid_installments', 'outstanding_balance', 'payoff_amount'];
        $values = [];
        foreach ($fields as $field) {
            if ($this->input($field) === null || $this->input($field) === '') {
                $values[$field] = 0;
            }
        }
        $this->merge($values);
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['label' => ['nullable', 'string', 'max:120'], 'bank_name' => ['nullable', 'string', 'max:120'], 'financing_system' => ['nullable', 'in:SAC,PRICE'], 'interest_rate_monthly' => ['nullable', 'numeric', 'min:0'], 'interest_rate_yearly' => ['nullable', 'numeric', 'min:0'], 'total_installments' => ['nullable', 'integer', 'min:0'], 'installment_value' => ['nullable', 'numeric', 'min:0'], 'paid_installments' => ['nullable', 'integer', 'min:0'], 'outstanding_balance' => ['nullable', 'numeric', 'min:0'], 'payoff_amount' => ['nullable', 'numeric', 'min:0']];
    }
}
