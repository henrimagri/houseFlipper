<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class PropriedadeEnderecoData extends Data
{
    public function __construct(
        public ?string $cep = null,
        public ?string $logradouro = null,
        public ?string $numero = null,
        public ?string $complemento = null,
        public ?string $bairro = null,
        public ?string $cidade = null,
        public ?string $estado = null,
        public ?string $pais = 'Brasil',
        public ?float $latitude = null,
        public ?float $longitude = null,
    ) {}
}
