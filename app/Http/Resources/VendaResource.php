<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class VendaResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'propriedade_id' => $this->propriedade_id,
            'propriedade' => $this->whenLoaded('propriedade', fn () => [
                'id' => $this->propriedade->id,
                'apelido' => $this->propriedade->apelido,
            ]),
            'comprador_nome' => $this->comprador_nome,
            'comprador_documento' => $this->comprador_documento,
            'comprador_contato' => $this->comprador_contato,
            'valor_venda' => $this->valor_venda,
            'valor_comissao' => $this->valor_comissao,
            'valor_impostos' => $this->valor_impostos,
            'outras_taxas' => $this->outras_taxas,
            'valor_liquido' => $this->valorLiquido(),
            'data_proposta' => $this->data_proposta?->toDateString(),
            'data_fechamento' => $this->data_fechamento?->toDateString(),
            'data_escritura' => $this->data_escritura?->toDateString(),
            'forma_pagamento' => $this->forma_pagamento,
            'status' => $this->status,
            'observacoes' => $this->observacoes,
        ];
    }
}
