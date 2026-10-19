<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InvestmentResultRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->input('sale_costs') === null || $this->input('sale_costs') === '') {
            $this->merge(['sale_costs' => 0]);
        }
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['sale_price' => ['required', 'numeric', 'min:0', 'max:999999999999'], 'sale_date' => ['nullable', 'date', 'max:40'], 'sale_costs' => ['nullable', 'numeric', 'min:0', 'max:999999999999'], 'payoff_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'], 'flip_duration_months' => ['required', 'integer', 'min:1', 'max:600']];
    }
}
