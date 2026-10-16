<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PropriedadeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'apelido' => $this->apelido,
            'tipo' => $this->tipo,
            'area_m2' => $this->area_m2,
            'quartos' => $this->quartos,
            'banheiros' => $this->banheiros,
            'vagas' => $this->vagas,
            'valor_aquisicao' => $this->valor_aquisicao,
            'data_aquisicao' => $this->data_aquisicao?->toDateString(),
            'valor_venda_alvo' => $this->valor_venda_alvo,
            'status' => $this->status,
            'observacoes' => $this->observacoes,
            'endereco' => $this->whenLoaded('endereco', fn () => $this->endereco ? [
                'cep' => $this->endereco->cep,
                'logradouro' => $this->endereco->logradouro,
                'numero' => $this->endereco->numero,
                'complemento' => $this->endereco->complemento,
                'bairro' => $this->endereco->bairro,
                'cidade' => $this->endereco->cidade,
                'estado' => $this->endereco->estado,
                'pais' => $this->endereco->pais,
            ] : null),
            'data_inclusao' => $this->data_inclusao?->toIso8601String(),
            'data_modificacao' => $this->data_modificacao?->toIso8601String(),
        ];
    }
}
