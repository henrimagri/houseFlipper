<?php

namespace App\Http\Requests;

use App\Models\Venda;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class VendaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->tenant_id !== null;
    }

    public function rules(): array
    {
        $required = $this->isMethod('PATCH') ? 'sometimes' : 'required';

        return [
            'propriedade_id' => [
                $required, 'integer',
                Rule::exists('propriedades', 'id')->where('tenant_id', auth()->user()->tenant_id)->where('deletado_flg', false),
            ],
            'valor_venda' => [$required, 'numeric', 'min:0', 'max:999999999999'],
            'comprador_nome' => ['nullable', 'string', 'max:200'],
            'comprador_documento' => ['nullable', 'string', 'max:30'],
            'comprador_contato' => ['nullable', 'string', 'max:150'],
            'valor_comissao' => ['sometimes', 'required', 'numeric', 'min:0', 'max:999999999999'],
            'valor_impostos' => ['sometimes', 'required', 'numeric', 'min:0', 'max:999999999999'],
            'outras_taxas' => ['sometimes', 'required', 'numeric', 'min:0', 'max:999999999999'],
            'data_proposta' => ['nullable', 'date', 'max:40'],
            'data_fechamento' => ['nullable', 'date', 'max:40'],
            'data_escritura' => ['nullable', 'date', 'max:40'],
            'forma_pagamento' => ['nullable', 'string', 'max:60'],
            'status' => ['sometimes', 'required', 'in:proposta,negociacao,fechada,escriturada,cancelada'],
            'observacoes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $sale = $this->persistedSale();
            $proposal = $this->effectiveDate('data_proposta', $sale);
            $closing = $this->effectiveDate('data_fechamento', $sale);
            $deed = $this->effectiveDate('data_escritura', $sale);
            $status = (string) ($this->exists('status') ? $this->input('status') : ($sale?->status ?? 'proposta'));

            if (in_array($status, ['fechada', 'escriturada'], true) && ! $closing) {
                $validator->errors()->add('data_fechamento', 'A data de fechamento é obrigatória para uma venda finalizada.');
            }
            if ($status === 'escriturada' && ! $deed) {
                $validator->errors()->add('data_escritura', 'A data da escritura é obrigatória para uma venda escriturada.');
            }

            if ($proposal && $closing && $closing->lt($proposal)) {
                $validator->errors()->add('data_fechamento', 'A data de fechamento não pode ser anterior à proposta.');
            }
            $deedLowerBound = $closing ?? $proposal;
            if ($deed && $deedLowerBound && $deed->lt($deedLowerBound)) {
                $validator->errors()->add('data_escritura', 'A data da escritura não pode ser anterior aos marcos anteriores da venda.');
            }
        }];
    }

    private function persistedSale(): ?Venda
    {
        $id = $this->route('venda');

        return is_numeric($id) ? Venda::ativos()->find((int) $id) : null;
    }

    private function effectiveDate(string $field, ?Venda $sale): ?CarbonImmutable
    {
        $value = $this->exists($field) ? $this->input($field) : $sale?->{$field};

        return filled($value) ? CarbonImmutable::parse($value) : null;
    }
}
