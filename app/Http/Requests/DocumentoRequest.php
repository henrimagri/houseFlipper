<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class DocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->tenant_id !== null;
    }

    public function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;
        $required = $this->isMethod('PATCH') ? 'sometimes' : 'required';
        $fileRules = $this->isMethod('post')
            ? ['required', File::types(config('documents.extensions'))->max(config('documents.max_kb'))]
            : ['prohibited'];

        return [
            'arquivo' => $fileRules,
            'projeto_id' => [
                'nullable', 'integer',
                Rule::exists('projetos', 'id')->where('tenant_id', $tenantId)->where('deletado_flg', false),
            ],
            'categoria' => [$required, 'string', 'max:80'],
            'status' => ['sometimes', 'required', 'in:ativo,em_revisao,aprovado,arquivado'],
            'descricao' => ['nullable', 'string', 'max:5000'],
            'data_vencimento' => ['nullable', 'date', 'max:40'],
        ];
    }
}
