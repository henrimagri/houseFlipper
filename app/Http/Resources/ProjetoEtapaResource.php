<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjetoEtapaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'descricao' => $this->descricao,
            'ordem' => $this->ordem,
            'orcamento' => $this->orcamento,
            'custo_real' => $this->custo_real,
            'data_inicio_prevista' => $this->data_inicio_prevista?->toDateString(),
            'data_termino_prevista' => $this->data_termino_prevista?->toDateString(),
            'data_inicio_real' => $this->data_inicio_real?->toDateString(),
            'data_termino_real' => $this->data_termino_real?->toDateString(),
            'status' => $this->status,
            'progresso_pct' => $this->progresso_pct,
        ];
    }
}
