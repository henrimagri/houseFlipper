<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VendaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'propriedade_id' => [
                'required', 'integer',
                Rule::exists('propriedades', 'id')->where('tenant_id', auth()->user()->tenant_id),
            ],
            'valor_venda' => ['required', 'numeric', 'min:0'],
            'comprador_nome' => ['nullable', 'string', 'max:200'],
            'comprador_documento' => ['nullable', 'string', 'max:30'],
            'comprador_contato' => ['nullable', 'string', 'max:150'],
            'valor_comissao' => ['nullable', 'numeric', 'min:0'],
            'valor_impostos' => ['nullable', 'numeric', 'min:0'],
            'outras_taxas' => ['nullable', 'numeric', 'min:0'],
            'data_proposta' => ['nullable', 'date'],
            'data_fechamento' => ['nullable', 'date'],
            'data_escritura' => ['nullable', 'date'],
            'forma_pagamento' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', 'in:proposta,negociacao,fechada,escriturada,cancelada'],
            'observacoes' => ['nullable', 'string'],
        ];
    }
}
