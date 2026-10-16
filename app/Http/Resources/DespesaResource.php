<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DespesaResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'categoria_despesa_id' => $this->categoria_despesa_id,
            'categoria' => $this->whenLoaded('categoria', fn () => [
                'id' => $this->categoria->id,
                'nome' => $this->categoria->nome,
                'tipo' => $this->categoria->tipo,
            ]),
            'propriedade_id' => $this->propriedade_id,
            'projeto_id' => $this->projeto_id,
            'etapa_id' => $this->etapa_id,
            'tarefa_id' => $this->tarefa_id,
            'descricao' => $this->descricao,
            'valor' => $this->valor,
            'data_despesa' => $this->data_despesa?->toDateString(),
            'data_pagamento' => $this->data_pagamento?->toDateString(),
            'forma_pagamento' => $this->forma_pagamento,
            'status_pagamento' => $this->status_pagamento,
            'documento_numero' => $this->documento_numero,
            'observacoes' => $this->observacoes,
        ];
    }
}
