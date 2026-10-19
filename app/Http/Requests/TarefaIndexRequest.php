<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TarefaIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->tenant_id !== null;
    }

    public function rules(): array
    {
        return [
            'projeto_id' => ['sometimes', 'integer', Rule::exists('projetos', 'id')->where('tenant_id', auth()->user()->tenant_id)->where('deletado_flg', false)],
            'status' => ['sometimes', 'in:pendente,em_andamento,concluida,cancelada'],
            'prioridade' => ['sometimes', 'in:baixa,media,alta,critica'],
            'busca' => ['sometimes', 'string', 'max:200'],
            'data_inicio' => ['sometimes', 'date', 'max:40'],
            'data_fim' => ['sometimes', 'date', 'max:40', 'after_or_equal:data_inicio'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
