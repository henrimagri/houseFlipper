<?php

use App\Models\Despesa;
use App\Models\Documento;
use App\Models\EventoCalendario;
use App\Models\Projeto;
use App\Models\Propriedade;
use App\Models\Tarefa;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$fixture = json_decode(file_get_contents(__DIR__.'/fixture.private'), true);
$phase = $fixture['phase'];
$variant = $argv[1] ?? 'natural';
$results = array_map(fn (int $index) => json_decode(file_get_contents(__DIR__."/$phase-$index.result"), true), [0, 1]);
$statuses = array_column($results, 'status');
$overlap = min(array_column($results, 'finished_at')) >= max(array_column($results, 'started_at'));
$project = $fixture['project'] ? Projeto::withoutGlobalScopes()->find($fixture['project']) : null;
$source = Propriedade::withoutGlobalScopes()->findOrFail($fixture['source']);
$destination = Propriedade::withoutGlobalScopes()->findOrFail($fixture['destination']);

$details = [];
$passed = match ($phase) {
    'project-create' => (function () use (&$details, $statuses, $source): bool {
        $projects = Projeto::withoutGlobalScopes()->where('propriedade_id', $source->id)->where('deletado_flg', false)->count();
        $details = ['source_deleted' => (bool) $source->deletado_flg, 'active_projects' => $projects];

        return in_array($statuses, [[201, 409], [404, 200], [422, 200]], true)
            && (($projects === 1 && ! $source->deletado_flg) || ($projects === 0 && $source->deletado_flg));
    })(),
    'expense-create' => (function () use (&$details, $statuses, $project): bool {
        $active = Despesa::withoutGlobalScopes()->where('projeto_id', $project->id)->where('deletado_flg', false)->count();
        $details = ['project_deleted' => (bool) $project->deletado_flg, 'active_expenses' => $active];

        return in_array($statuses, [[201, 409], [404, 200], [422, 200]], true)
            && (($active === 1 && ! $project->deletado_flg) || ($active === 0 && $project->deletado_flg));
    })(),
    'document-create' => (function () use (&$details, $statuses, $project, $fixture): bool {
        $documents = Documento::withoutGlobalScopes()->where('projeto_id', $project->id)->where('deletado_flg', false)->get();
        $files = Storage::disk(config('documents.disk'))->allFiles('documents/'.$fixture['tenant']);
        $details = ['project_deleted' => (bool) $project->deletado_flg, 'active_documents' => $documents->count(), 'stored_files' => count($files)];

        return in_array($statuses, [[201, 409], [404, 200], [422, 200]], true)
            && count($files) === $documents->count()
            && (($documents->count() === 1 && ! $project->deletado_flg) || ($documents->isEmpty() && $project->deletado_flg));
    })(),
    'task-create' => (function () use (&$details, $statuses, $project): bool {
        $active = Tarefa::withoutGlobalScopes()->where('projeto_id', $project->id)->where('deletado_flg', false)->count();
        $details = ['project_deleted' => (bool) $project->deletado_flg, 'active_tasks' => $active];

        return in_array($statuses, [[201, 200], [404, 200], [422, 200]], true) && $project->deletado_flg && $active === 0;
    })(),
    'event-create' => (function () use (&$details, $statuses, $project): bool {
        $active = EventoCalendario::withoutGlobalScopes()->where('projeto_id', $project->id)->where('deletado_flg', false)->count();
        $details = ['project_deleted' => (bool) $project->deletado_flg, 'active_events' => $active];

        return in_array($statuses, [[201, 200], [404, 200], [422, 200]], true) && $project->deletado_flg && $active === 0;
    })(),
    'move-source-delete' => (function () use (&$details, $statuses, $project, $source, $destination): bool {
        $details = ['project_property' => $project->propriedade_id, 'source_deleted' => (bool) $source->deletado_flg, 'destination_deleted' => (bool) $destination->deletado_flg];

        return in_array($statuses, [[200, 200], [200, 409]], true)
            && $project->propriedade_id === $destination->id && ! $project->deletado_flg && ! $destination->deletado_flg;
    })(),
    'move-destination-delete' => (function () use (&$details, $statuses, $project, $source, $destination): bool {
        $details = ['project_property' => $project->propriedade_id, 'source_deleted' => (bool) $source->deletado_flg, 'destination_deleted' => (bool) $destination->deletado_flg];
        $moved = $statuses === [200, 409] && $project->propriedade_id === $destination->id && ! $destination->deletado_flg;
        $rejected = in_array($statuses, [[404, 200], [422, 200]], true) && $project->propriedade_id === $source->id && $destination->deletado_flg;

        return ! $project->deletado_flg && ! $source->deletado_flg && ($moved || $rejected);
    })(),
    'project-update-delete' => (function () use (&$details, $statuses, $project): bool {
        $details = ['project_deleted' => (bool) $project->deletado_flg, 'project_name' => $project->nome];

        return in_array($statuses, [[200, 200], [404, 200]], true) && $project->deletado_flg;
    })(),
    default => false,
};

$evidence = ['phase' => $phase, 'variant' => $variant, 'statuses' => $statuses, 'requests_overlapped' => $overlap, 'details' => $details, 'passed' => $passed && $overlap];
echo json_encode($evidence, JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($evidence['passed'] ? 0 : 1);
