<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProjetoResource extends JsonResource
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
            'nome' => $this->nome,
            'descricao' => $this->descricao,
            'orcamento_total' => $this->orcamento_total,
            'data_inicio' => $this->data_inicio?->toDateString(),
            'data_termino_prevista' => $this->data_termino_prevista?->toDateString(),
            'data_termino_real' => $this->data_termino_real?->toDateString(),
            'status' => $this->status,
            'etapas' => $this->whenLoaded('etapas'),
            'tarefas' => $this->whenLoaded('tarefas'),
        ];
    }
}
