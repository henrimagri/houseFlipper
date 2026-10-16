<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjetoRequest extends FormRequest
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
            'nome' => ['required', 'string', 'max:150'],
            'descricao' => ['nullable', 'string'],
            'orcamento_total' => ['nullable', 'numeric', 'min:0'],
            'data_inicio' => ['nullable', 'date'],
            'data_termino_prevista' => ['nullable', 'date', 'after_or_equal:data_inicio'],
            'status' => ['nullable', 'in:planejamento,em_andamento,pausado,concluido,cancelado'],

            'etapas' => ['sometimes', 'array'],
            'etapas.*.nome' => ['required_with:etapas', 'string', 'max:150'],
            'etapas.*.descricao' => ['nullable', 'string'],
            'etapas.*.ordem' => ['nullable', 'integer', 'min:0'],
            'etapas.*.orcamento' => ['nullable', 'numeric', 'min:0'],
            'etapas.*.data_inicio_prevista' => ['nullable', 'date'],
            'etapas.*.data_termino_prevista' => ['nullable', 'date'],
            'etapas.*.status' => ['nullable', 'in:pendente,em_andamento,concluida,bloqueada'],

            'orcamento_categorias' => ['sometimes', 'array'],
            'orcamento_categorias.demolicao' => ['nullable', 'numeric', 'min:0'],
            'orcamento_categorias.eletrica' => ['nullable', 'numeric', 'min:0'],
            'orcamento_categorias.hidraulica' => ['nullable', 'numeric', 'min:0'],
            'orcamento_categorias.acabamento' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
