<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class ProjetoData extends Data
{
    public function __construct(
        public int $propriedade_id,
        public string $nome,
        public ?string $descricao = null,
        public float $orcamento_total = 0,
        public ?string $data_inicio = null,
        public ?string $data_termino_prevista = null,
        public ?string $status = null,
    ) {}
}
