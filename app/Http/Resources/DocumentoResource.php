<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DocumentoResource extends JsonResource
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
            'nome' => $this->nome_original,
            'mime_type' => $this->mime_type,
            'tamanho_bytes' => $this->tamanho_bytes,
            'categoria' => $this->categoria,
            'status' => $this->status,
            'descricao' => $this->descricao,
            'data_vencimento' => $this->data_vencimento?->toDateString(),
            'enviado_por' => $this->whenLoaded('enviadoPor', fn () => $this->enviadoPor?->name),
            'data_inclusao' => $this->data_inclusao?->toIso8601String(),
            'data_modificacao' => $this->data_modificacao?->toIso8601String(),
        ];
    }
}
