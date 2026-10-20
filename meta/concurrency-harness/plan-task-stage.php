<?php

$fixture = json_decode(file_get_contents(__DIR__.'/fixture.private'), true);
$phase = $argv[1] ?? '';

$jobs = match ($phase) {
    'task-derived' => [
        ['method' => 'PATCH', 'uri' => '/api/tarefas/'.$fixture['task'], 'body' => ['status' => 'concluida']],
        ['method' => 'PATCH', 'uri' => '/api/tarefas/'.$fixture['task'], 'body' => ['titulo' => 'Title changed concurrently']],
    ],
    'task-conflict' => [
        ['method' => 'PATCH', 'uri' => '/api/tarefas/'.$fixture['task'], 'body' => ['titulo' => 'Concurrent title A']],
        ['method' => 'PATCH', 'uri' => '/api/tarefas/'.$fixture['task'], 'body' => ['titulo' => 'Concurrent title B']],
    ],
    'stage-conflict' => [
        ['method' => 'PATCH', 'uri' => '/api/projetos-etapas/'.$fixture['stage'], 'body' => ['orcamento' => 2100, 'status' => 'em_andamento', 'progresso_pct' => 35]],
        ['method' => 'PATCH', 'uri' => '/api/projetos-etapas/'.$fixture['stage'], 'body' => ['orcamento' => 3200, 'status' => 'concluida', 'progresso_pct' => 100]],
    ],
    default => throw new InvalidArgumentException('Unknown phase'),
};

foreach ($jobs as $index => $job) {
    file_put_contents(__DIR__."/$phase-$index.job", json_encode($job));
}
