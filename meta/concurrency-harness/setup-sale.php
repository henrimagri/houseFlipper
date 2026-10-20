<?php

use App\Models\Propriedade;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

$project = dirname(__DIR__, 2);
$password = trim(file_get_contents(__DIR__.'/db-password.private'));
$pdo = new PDO('mysql:host=127.0.0.1;port=13308', 'root', $password);
$pdo->exec('DROP DATABASE IF EXISTS houseflipper_audit');
$pdo->exec('CREATE DATABASE houseflipper_audit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

require $project.'/vendor/autoload.php';
$app = require $project.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
Artisan::call('migrate', ['--force' => true]);

$tenant = Tenant::create(['nome' => 'Final sale concurrency audit', 'slug' => 'final-sale-concurrency-audit']);
$user = User::create([
    'name' => 'Audit',
    'email' => 'final-sale-concurrency@example.test',
    'email_verified_at' => now(),
    'password' => bin2hex(random_bytes(20)),
    'tenant_id' => $tenant->id,
]);
$property = Propriedade::create([
    'tenant_id' => $tenant->id,
    'apelido' => 'Final sale race fixture',
    'valor_aquisicao' => 100000,
    'status' => 'a_venda',
]);

file_put_contents(__DIR__.'/fixture.private', json_encode([
    'token' => $user->createToken('isolated-final-sale-audit')->plainTextToken,
    'property' => $property->id,
]));

echo "Disposable MariaDB schema and fictional sale fixture created.\n";
