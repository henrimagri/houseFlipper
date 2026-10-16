<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InvestmentResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['sale_price' => ['required', 'numeric', 'min:0'], 'sale_date' => ['nullable', 'date'], 'sale_costs' => ['nullable', 'numeric', 'min:0'], 'payoff_amount' => ['nullable', 'numeric', 'min:0'], 'flip_duration_months' => ['required', 'integer', 'min:1']];
    }
}
