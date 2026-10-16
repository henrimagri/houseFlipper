<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InvestmentAnalysisRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $optionalMoneyFields = [
            'renovation_estimate', 'documentation_costs', 'taxes_costs',
            'broker_commission', 'expected_monthly_costs',
            'expected_installment_value', 'expected_outstanding_balance',
            'average_price_per_m2', 'average_sold_value', 'suggested_sale_price',
        ];
        $normalized = [];
        foreach ($optionalMoneyFields as $field) {
            if ($this->input($field) === null || $this->input($field) === '') {
                $normalized[$field] = 0;
            }
        }
        $this->merge($normalized);
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['purchase_price' => ['required', 'numeric', 'min:0'], 'down_payment' => ['required', 'numeric', 'min:0', 'lte:purchase_price'], 'property_condition' => ['nullable', 'in:poor,fair,good,excellent'], 'market_region' => ['nullable', 'string', 'max:120'], 'average_price_per_m2' => ['nullable', 'numeric', 'min:0'], 'average_sold_value' => ['nullable', 'numeric', 'min:0'], 'suggested_sale_price' => ['nullable', 'numeric', 'min:0'], 'renovation_estimate' => ['nullable', 'numeric', 'min:0'], 'documentation_costs' => ['nullable', 'numeric', 'min:0'], 'taxes_costs' => ['nullable', 'numeric', 'min:0'], 'broker_commission' => ['nullable', 'numeric', 'min:0'], 'expected_sale_price' => ['required', 'numeric', 'min:0'], 'expected_months_until_sale' => ['required', 'integer', 'min:1', 'max:600'], 'expected_monthly_costs' => ['nullable', 'numeric', 'min:0'], 'expected_installment_value' => ['nullable', 'numeric', 'min:0'], 'expected_outstanding_balance' => ['nullable', 'numeric', 'min:0']];
    }

    public function messages(): array
    {
        return ['required' => 'O campo :attribute é obrigatório.', 'numeric' => 'O campo :attribute deve conter um valor válido.', 'min' => 'O campo :attribute não pode ser negativo.', 'down_payment.lte' => 'A entrada não pode ser maior que o valor de compra.', 'expected_months_until_sale.min' => 'Informe pelo menos um mês até a venda.'];
    }

    public function attributes(): array
    {
        return ['purchase_price' => 'valor de compra', 'down_payment' => 'entrada', 'renovation_estimate' => 'reforma estimada', 'documentation_costs' => 'documentação', 'taxes_costs' => 'impostos', 'broker_commission' => 'comissão', 'expected_sale_price' => 'valor esperado de venda', 'expected_months_until_sale' => 'meses até a venda', 'expected_monthly_costs' => 'custos mensais', 'expected_installment_value' => 'parcela mensal', 'expected_outstanding_balance' => 'saldo para quitação'];
    }
}
