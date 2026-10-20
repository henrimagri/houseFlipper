<?php

use App\Models\Projeto;
use App\Models\Propriedade;
use App\Models\Tarefa;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

$projectRoot = dirname(__DIR__, 2);
$password = trim(file_get_contents(__DIR__.'/db-password.private'));
$pdo = new PDO('mysql:host=127.0.0.1;port=13308', 'root', $password);
$pdo->exec('DROP DATABASE IF EXISTS houseflipper_audit');
$pdo->exec('CREATE DATABASE houseflipper_audit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

require $projectRoot.'/vendor/autoload.php';
$app = require $projectRoot.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
Artisan::call('migrate', ['--force' => true]);

$tenant = Tenant::create(['nome' => 'Task stage concurrency audit', 'slug' => 'task-stage-concurrency-audit']);
$user = User::create([
    'name' => 'Audit',
    'email' => 'task-stage-concurrency@example.test',
    'email_verified_at' => now(),
    'password' => bin2hex(random_bytes(20)),
    'tenant_id' => $tenant->id,
]);
$property = Propriedade::create([
    'tenant_id' => $tenant->id,
    'apelido' => 'Task stage concurrency fixture',
    'valor_aquisicao' => 100000,
    'status' => 'em_reforma',
]);
$project = Projeto::create([
    'tenant_id' => $tenant->id,
    'propriedade_id' => $property->id,
    'nome' => 'Concurrent work',
    'status' => 'em_andamento',
    'orcamento_total' => 1000,
]);
$stage = $project->etapas()->create([
    'tenant_id' => $tenant->id,
    'nome' => 'Concurrent stage',
    'ordem' => 1,
    'orcamento' => 1000,
    'custo_real' => 0,
    'status' => 'pendente',
    'progresso_pct' => 0,
]);
$task = Tarefa::create([
    'tenant_id' => $tenant->id,
    'projeto_id' => $project->id,
    'etapa_id' => $stage->id,
    'titulo' => 'Original task',
    'prioridade' => 'media',
    'status' => 'pendente',
]);

file_put_contents(__DIR__.'/fixture.private', json_encode([
    'token' => $user->createToken('isolated-task-stage-audit')->plainTextToken,
    'project' => $project->id,
    'stage' => $stage->id,
    'task' => $task->id,
]));

echo "Disposable MariaDB schema and fictional task/stage fixture created.\n";
