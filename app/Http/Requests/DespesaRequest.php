<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DespesaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            'categoria_despesa_id' => [
                'required', 'integer',
                Rule::exists('categorias_despesa', 'id')->where('tenant_id', $tenantId),
            ],
            'descricao' => ['required', 'string', 'max:250'],
            'valor' => ['required', 'numeric', 'min:0'],
            'data_despesa' => ['required', 'date'],
            'propriedade_id' => [
                'nullable', 'integer',
                Rule::exists('propriedades', 'id')->where('tenant_id', $tenantId),
            ],
            'projeto_id' => [
                'nullable', 'integer',
                Rule::exists('projetos', 'id')->where('tenant_id', $tenantId),
            ],
            'etapa_id' => [
                'nullable', 'integer',
                Rule::exists('projetos_etapas', 'id')->where('tenant_id', $tenantId),
            ],
            'tarefa_id' => [
                'nullable', 'integer',
                Rule::exists('tarefas', 'id')->where('tenant_id', $tenantId),
            ],
            'data_pagamento' => ['nullable', 'date'],
            'forma_pagamento' => ['nullable', 'string', 'max:40'],
            'status_pagamento' => ['nullable', 'in:pendente,pago,atrasado,cancelado'],
            'documento_numero' => ['nullable', 'string', 'max:80'],
            'observacoes' => ['nullable', 'string'],
        ];
    }
}
