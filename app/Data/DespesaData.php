<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class DespesaData extends Data
{
    public function __construct(
        public int $categoria_despesa_id,
        public string $descricao,
        public float $valor,
        public string $data_despesa,
        public ?int $propriedade_id = null,
        public ?int $projeto_id = null,
        public ?int $etapa_id = null,
        public ?int $tarefa_id = null,
        public ?string $data_pagamento = null,
        public ?string $forma_pagamento = null,
        public ?string $status_pagamento = null,
        public ?string $documento_numero = null,
        public ?string $observacoes = null,
    ) {}
}
