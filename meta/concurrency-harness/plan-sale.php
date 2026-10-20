<?php

$fixture = json_decode(file_get_contents(__DIR__.'/fixture.private'), true);

foreach ([150000, 151000] as $index => $amount) {
    file_put_contents(__DIR__."/sale-$index.job", json_encode([
        'method' => 'POST',
        'uri' => '/api/vendas',
        'body' => [
            'propriedade_id' => $fixture['property'],
            'valor_venda' => $amount,
            'status' => 'fechada',
            'data_fechamento' => '2026-10-17',
        ],
    ]));
}
