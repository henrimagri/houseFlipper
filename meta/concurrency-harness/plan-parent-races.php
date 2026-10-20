<?php

$fixture = json_decode(file_get_contents(__DIR__.'/fixture.private'), true);
$phase = $fixture['phase'];
$variant = $argv[1] ?? 'natural';
$project = $fixture['project'];
$source = $fixture['source'];
$destination = $fixture['destination'];

$jobs = match ($phase) {
    'project-create' => [
        ['method' => 'POST', 'uri' => '/api/projetos', 'body' => ['propriedade_id' => $source, 'nome' => 'Concurrent project']],
        ['method' => 'DELETE', 'uri' => '/api/propriedades/'.$source],
    ],
    'expense-create' => [
        ['method' => 'POST', 'uri' => '/api/despesas', 'body' => ['categoria_despesa_id' => $fixture['category'], 'descricao' => 'Concurrent expense', 'valor' => 100, 'data_despesa' => '2026-10-19', 'propriedade_id' => $source, 'projeto_id' => $project]],
        ['method' => 'DELETE', 'uri' => '/api/projetos/'.$project],
    ],
    'document-create' => [
        ['method' => 'POST', 'uri' => '/api/documentos', 'file' => true, 'body' => ['projeto_id' => $project, 'categoria' => 'contrato']],
        ['method' => 'DELETE', 'uri' => '/api/projetos/'.$project],
    ],
    'task-create' => [
        ['method' => 'POST', 'uri' => '/api/tarefas', 'body' => ['projeto_id' => $project, 'titulo' => 'Concurrent task', 'prioridade' => 'media', 'status' => 'pendente']],
        ['method' => 'DELETE', 'uri' => '/api/projetos/'.$project],
    ],
    'event-create' => [
        ['method' => 'POST', 'uri' => '/api/eventos-calendario', 'body' => ['projeto_id' => $project, 'titulo' => 'Concurrent event', 'categoria' => 'obra', 'inicio_em' => '2026-10-20 10:00:00']],
        ['method' => 'DELETE', 'uri' => '/api/projetos/'.$project],
    ],
    'move-source-delete' => [
        ['method' => 'PATCH', 'uri' => '/api/projetos/'.$project, 'body' => ['propriedade_id' => $destination]],
        ['method' => 'DELETE', 'uri' => '/api/propriedades/'.$source],
    ],
    'move-destination-delete' => [
        ['method' => 'PATCH', 'uri' => '/api/projetos/'.$project, 'body' => ['propriedade_id' => $destination]],
        ['method' => 'DELETE', 'uri' => '/api/propriedades/'.$destination],
    ],
    'project-update-delete' => [
        ['method' => 'PATCH', 'uri' => '/api/projetos/'.$project, 'body' => ['nome' => 'Concurrent renamed project']],
        ['method' => 'DELETE', 'uri' => '/api/projetos/'.$project],
    ],
    default => throw new InvalidArgumentException('Unknown phase'),
};

if ($variant === 'mutation-first') {
    $jobs[1]['delay_ms'] = 200;
}

foreach ($jobs as $index => $job) {
    file_put_contents(__DIR__."/$phase-$index.job", json_encode($job));
}
