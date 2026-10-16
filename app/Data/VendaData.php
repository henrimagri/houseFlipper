<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class VendaData extends Data
{
    public function __construct(
        public int $propriedade_id,
        public float $valor_venda,
        public ?string $comprador_nome = null,
        public ?string $comprador_documento = null,
        public ?string $comprador_contato = null,
        public float $valor_comissao = 0,
        public float $valor_impostos = 0,
        public float $outras_taxas = 0,
        public ?string $data_proposta = null,
        public ?string $data_fechamento = null,
        public ?string $data_escritura = null,
        public ?string $forma_pagamento = null,
        public ?string $status = null,
        public ?string $observacoes = null,
    ) {}
}
