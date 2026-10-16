<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class PropriedadeData extends Data
{
    public function __construct(
        public string $apelido,
        public ?string $tipo = null,
        public ?float $area_m2 = null,
        public ?int $quartos = null,
        public ?int $banheiros = null,
        public ?int $vagas = null,
        public ?float $valor_aquisicao = null,
        public ?string $data_aquisicao = null,
        public ?float $valor_venda_alvo = null,
        public ?string $status = null,
        public ?string $observacoes = null,
        public ?PropriedadeEnderecoData $endereco = null,
    ) {}
}
