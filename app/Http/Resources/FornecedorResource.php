<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FornecedorResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'categoria' => $this->categoria,
            'contato' => $this->contato,
            'telefone' => $this->telefone,
            'email' => $this->email,
            'avaliacao' => $this->avaliacao,
            'observacoes' => $this->observacoes,
            'ativo_flg' => $this->ativo_flg,
            'data_inclusao' => $this->data_inclusao?->toIso8601String(),
            'data_modificacao' => $this->data_modificacao?->toIso8601String(),
        ];
    }
}
