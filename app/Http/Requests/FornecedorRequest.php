<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FornecedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->tenant_id !== null;
    }

    public function rules(): array
    {
        $required = $this->isMethod('PATCH') ? 'sometimes' : 'required';

        return [
            'nome' => [$required, 'string', 'max:160'],
            'categoria' => [$required, 'string', 'max:80'],
            'contato' => ['nullable', 'string', 'max:160'],
            'telefone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9()+\-\.\s]{7,30}$/'],
            'email' => ['nullable', 'email:rfc', 'max:254'],
            'avaliacao' => ['nullable', 'numeric', 'between:0,5'],
            'observacoes' => ['nullable', 'string', 'max:5000'],
            'ativo_flg' => ['sometimes', 'boolean'],
        ];
    }
}
