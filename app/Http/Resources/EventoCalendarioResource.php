<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EventoCalendarioResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'projeto_id' => $this->projeto_id,
            'projeto' => $this->whenLoaded('projeto', fn () => $this->projeto ? [
                'id' => $this->projeto->id,
                'nome' => $this->projeto->nome,
            ] : null),
            'titulo' => $this->titulo,
            'descricao' => $this->descricao,
            'categoria' => $this->categoria,
            'inicio_em' => $this->inicio_em?->toIso8601String(),
            'fim_em' => $this->fim_em?->toIso8601String(),
            'dia_inteiro' => $this->dia_inteiro,
            'local' => $this->local,
            'endereco' => $this->endereco,
            'status' => $this->status,
            'criado_por' => $this->whenLoaded('criadoPor', fn () => $this->criadoPor?->name),
        ];
    }
}
