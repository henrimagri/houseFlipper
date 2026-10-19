<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinancialLedgerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->tenant_id !== null;
    }

    public function rules(): array
    {
        return [
            'data_inicio' => ['sometimes', 'nullable', 'date', 'max:40'],
            'data_fim' => ['sometimes', 'nullable', 'date', 'after_or_equal:data_inicio', 'max:40'],
            'projeto_id' => ['sometimes', 'nullable', 'integer', Rule::exists('projetos', 'id')->where('tenant_id', auth()->user()->tenant_id)->where('deletado_flg', false)],
            'page' => ['sometimes', 'integer', 'between:1,1000000'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
