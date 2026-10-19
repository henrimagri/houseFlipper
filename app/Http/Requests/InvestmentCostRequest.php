<?php

namespace App\Http\Requests;

use App\Models\InvestmentCost;
use Illuminate\Foundation\Http\FormRequest;

class InvestmentCostRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $cost = $this->route('investmentCost');
        $cost = $cost instanceof InvestmentCost ? $cost : null;
        $status = (string) ($this->input('payment_status') ?? $cost?->payment_status ?? 'pending');

        if ($status !== 'paid') {
            $this->merge(['paid_at' => null]);

            return;
        }

        if (! $this->filled('paid_at')) {
            $this->merge([
                'paid_at' => $cost?->payment_status === 'paid' && $cost->paid_at
                    ? $cost->paid_at->toDateString()
                    : now()->toDateString(),
            ]);
        }
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['category' => ['required', 'in:acquisition,down_payment,itbi,registry,documentation,renovation,materials,labor,painting,woodwork,electricity,plumbing,condominium,property_tax,water,energy,internet,insurance,marketing,brokerage,financing_installment,other'], 'description' => ['required', 'string', 'max:255'], 'amount' => ['required', 'numeric', 'min:0', 'max:999999999999'], 'due_date' => ['nullable', 'date', 'max:40'], 'paid_at' => ['nullable', 'date', 'max:40'], 'payment_status' => ['required', 'in:pending,paid,overdue']];
    }
}
