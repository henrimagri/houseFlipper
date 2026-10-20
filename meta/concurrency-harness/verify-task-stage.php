<?php

use App\Models\Projeto;
use App\Models\ProjetoEtapa;
use App\Models\Tarefa;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$fixture = json_decode(file_get_contents(__DIR__.'/fixture.private'), true);

$readResults = static function (string $phase): array {
    return array_map(
        fn (int $index) => json_decode(file_get_contents(__DIR__."/$phase-$index.result"), true),
        [0, 1],
    );
};
$lastWorker = static function (array $results): int {
    return $results[0]['finished_at'] > $results[1]['finished_at'] ? 0 : 1;
};

$derivedResults = $readResults('task-derived');
$conflictResults = $readResults('task-conflict');
$stageResults = $readResults('stage-conflict');
$task = Tarefa::findOrFail($fixture['task']);
$stage = ProjetoEtapa::findOrFail($fixture['stage']);
$project = Projeto::findOrFail($fixture['project']);
$taskLast = $lastWorker($conflictResults);
$stageLast = $lastWorker($stageResults);
$expectedTaskTitle = $taskLast === 0 ? 'Concurrent title A' : 'Concurrent title B';
$expectedStage = $stageLast === 0
    ? ['orcamento' => 2100.0, 'status' => 'em_andamento', 'progresso_pct' => 35]
    : ['orcamento' => 3200.0, 'status' => 'concluida', 'progresso_pct' => 100];

$evidence = [
    'database' => 'MariaDB isolated',
    'policy' => 'serialized row updates; disjoint PATCH fields are preserved; conflicting fields use last committed write',
    'task_derived' => [
        'worker_statuses' => array_column($derivedResults, 'status'),
        'requests_overlapped' => min(array_column($derivedResults, 'finished_at')) >= max(array_column($derivedResults, 'started_at')),
        'status' => $task->status,
        'completion_date_present' => $task->data_conclusao !== null,
    ],
    'task_conflict' => [
        'worker_statuses' => array_column($conflictResults, 'status'),
        'requests_overlapped' => min(array_column($conflictResults, 'finished_at')) >= max(array_column($conflictResults, 'started_at')),
        'last_finished_worker' => $taskLast,
        'expected_title' => $expectedTaskTitle,
        'actual_title' => $task->titulo,
    ],
    'stage_conflict' => [
        'worker_statuses' => array_column($stageResults, 'status'),
        'requests_overlapped' => min(array_column($stageResults, 'finished_at')) >= max(array_column($stageResults, 'started_at')),
        'last_finished_worker' => $stageLast,
        'expected' => $expectedStage,
        'actual' => [
            'orcamento' => (float) $stage->orcamento,
            'status' => $stage->status,
            'progresso_pct' => $stage->progresso_pct,
        ],
        'project_budget' => (float) $project->orcamento_total,
    ],
];
$allStatusesOk = collect([$derivedResults, $conflictResults, $stageResults])
    ->flatten(1)->every(fn (array $result) => $result['status'] === 200);
$allOverlap = $evidence['task_derived']['requests_overlapped']
    && $evidence['task_conflict']['requests_overlapped']
    && $evidence['stage_conflict']['requests_overlapped'];
$evidence['passed'] = $allStatusesOk
    && $allOverlap
    && $task->status === 'concluida'
    && $task->data_conclusao !== null
    && $task->titulo === $expectedTaskTitle
    && $evidence['stage_conflict']['actual'] === $expectedStage
    && (float) $project->orcamento_total === $expectedStage['orcamento'];

echo json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($evidence['passed'] ? 0 : 1);
