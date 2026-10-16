<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InvestmentCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['category' => ['required', 'in:acquisition,down_payment,itbi,registry,documentation,renovation,materials,labor,painting,woodwork,electricity,plumbing,condominium,property_tax,water,energy,internet,insurance,marketing,brokerage,financing_installment,other'], 'description' => ['required', 'string', 'max:255'], 'amount' => ['required', 'numeric', 'min:0'], 'due_date' => ['nullable', 'date'], 'paid_at' => ['nullable', 'date'], 'payment_status' => ['required', 'in:pending,paid,overdue']];
    }
}
