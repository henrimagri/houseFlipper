<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PropriedadeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'apelido' => ['required', 'string', 'max:120'],
            'tipo' => ['nullable', 'string', 'max:40'],
            'area_m2' => ['nullable', 'numeric', 'min:0'],
            'quartos' => ['nullable', 'integer', 'min:0'],
            'banheiros' => ['nullable', 'integer', 'min:0'],
            'vagas' => ['nullable', 'integer', 'min:0'],
            'valor_aquisicao' => ['nullable', 'numeric', 'min:0'],
            'data_aquisicao' => ['nullable', 'date'],
            'valor_venda_alvo' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'in:prospeccao,adquirida,em_reforma,a_venda,vendida'],
            'observacoes' => ['nullable', 'string'],

            'endereco' => ['nullable', 'array'],
            'endereco.cep' => ['nullable', 'string', 'max:12'],
            'endereco.logradouro' => ['nullable', 'string', 'max:200'],
            'endereco.numero' => ['nullable', 'string', 'max:20'],
            'endereco.complemento' => ['nullable', 'string', 'max:100'],
            'endereco.bairro' => ['nullable', 'string', 'max:100'],
            'endereco.cidade' => ['nullable', 'string', 'max:100'],
            'endereco.estado' => ['nullable', 'string', 'size:2'],
            'endereco.pais' => ['nullable', 'string', 'max:60'],
            'endereco.latitude' => ['nullable', 'numeric'],
            'endereco.longitude' => ['nullable', 'numeric'],
        ];
    }
}
