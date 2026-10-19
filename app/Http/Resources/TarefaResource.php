<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TarefaResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'projeto_id' => $this->projeto_id,
            'projeto' => $this->whenLoaded('projeto', fn () => $this->projeto ? ['id' => $this->projeto->id, 'nome' => $this->projeto->nome] : null),
            'etapa_id' => $this->etapa_id,
            'etapa' => $this->whenLoaded('etapa', fn () => $this->etapa ? ['id' => $this->etapa->id, 'nome' => $this->etapa->nome] : null),
            'responsavel_user_id' => $this->responsavel_user_id,
            'responsavel' => $this->whenLoaded('responsavel', fn () => $this->responsavel ? ['id' => $this->responsavel->id, 'nome' => $this->responsavel->name] : null),
            'titulo' => $this->titulo,
            'descricao' => $this->descricao,
            'data_prevista' => $this->data_prevista?->toDateString(),
            'data_conclusao' => $this->data_conclusao?->toDateString(),
            'prioridade' => $this->prioridade,
            'status' => $this->status,
            'custo_estimado' => $this->custo_estimado === null ? null : (float) $this->custo_estimado,
            'custo_real' => $this->custo_real === null ? null : (float) $this->custo_real,
            'data_inclusao' => $this->data_inclusao?->toIso8601String(),
            'data_modificacao' => $this->data_modificacao?->toIso8601String(),
        ];
    }
}
