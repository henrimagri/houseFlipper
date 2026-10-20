<?php

use App\Models\CategoriaDespesa;
use App\Models\Projeto;
use App\Models\Propriedade;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

$phase = $argv[1] ?? throw new InvalidArgumentException('Phase required');
$projectRoot = dirname(__DIR__, 2);
$password = trim(file_get_contents(__DIR__.'/db-password.private'));
$pdo = new PDO('mysql:host=127.0.0.1;port=13308', 'root', $password);
$pdo->exec('DROP DATABASE IF EXISTS houseflipper_audit');
$pdo->exec('CREATE DATABASE houseflipper_audit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

require $projectRoot.'/vendor/autoload.php';
$app = require $projectRoot.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
Artisan::call('migrate', ['--force' => true]);

$tenant = Tenant::create(['nome' => 'Parent race audit', 'slug' => 'parent-race-audit']);
$user = User::create([
    'name' => 'Audit',
    'email' => 'parent-race@example.test',
    'email_verified_at' => now(),
    'password' => bin2hex(random_bytes(20)),
    'tenant_id' => $tenant->id,
]);
$source = Propriedade::create([
    'tenant_id' => $tenant->id,
    'apelido' => 'Race source property',
    'valor_aquisicao' => 100000,
    'status' => 'adquirida',
]);
$destination = Propriedade::create([
    'tenant_id' => $tenant->id,
    'apelido' => 'Race destination property',
    'valor_aquisicao' => 120000,
    'status' => 'adquirida',
]);
$project = null;
if ($phase !== 'project-create') {
    $project = Projeto::create([
        'tenant_id' => $tenant->id,
        'propriedade_id' => $source->id,
        'nome' => 'Parent race project',
        'status' => 'planejamento',
        'orcamento_total' => 1000,
    ]);
}
$category = CategoriaDespesa::create([
    'tenant_id' => $tenant->id,
    'nome' => 'Race category',
    'tipo' => 'reforma',
]);

file_put_contents(__DIR__.'/fixture.private', json_encode([
    'phase' => $phase,
    'token' => $user->createToken('isolated-parent-race-audit')->plainTextToken,
    'tenant' => $tenant->id,
    'source' => $source->id,
    'destination' => $destination->id,
    'project' => $project?->id,
    'category' => $category->id,
]));
