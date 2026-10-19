<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PropriedadeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->tenant_id !== null;
    }

    public function rules(): array
    {
        $required = $this->isMethod('PATCH') ? 'sometimes' : 'required';

        return [
            'apelido' => [$required, 'string', 'max:120'],
            'tipo' => ['nullable', 'string', 'max:40'],
            'area_m2' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'quartos' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'banheiros' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'vagas' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'valor_aquisicao' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'data_aquisicao' => ['nullable', 'date', 'max:40'],
            'valor_venda_alvo' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'status' => ['sometimes', 'required', 'in:prospeccao,adquirida,em_reforma,a_venda,vendida'],
            'observacoes' => ['nullable', 'string', 'max:5000'],

            'endereco' => ['nullable', 'array:cep,logradouro,numero,complemento,bairro,cidade,estado,pais,latitude,longitude'],
            'endereco.cep' => ['nullable', 'string', 'max:12'],
            'endereco.logradouro' => ['nullable', 'string', 'max:200'],
            'endereco.numero' => ['nullable', 'string', 'max:20'],
            'endereco.complemento' => ['nullable', 'string', 'max:100'],
            'endereco.bairro' => ['nullable', 'string', 'max:100'],
            'endereco.cidade' => ['nullable', 'string', 'max:100'],
            'endereco.estado' => ['nullable', 'string', 'size:2'],
            'endereco.pais' => ['sometimes', 'required', 'string', 'max:60'],
            'endereco.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'endereco.longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
