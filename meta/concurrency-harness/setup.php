<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

$project = dirname(__DIR__, 2);
$pdo = new PDO('mysql:host=127.0.0.1;port=13308', 'root', trim(file_get_contents(__DIR__.'/db-password.private')));
$pdo->exec('CREATE DATABASE houseflipper_audit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
require $project.'/vendor/autoload.php';
$app = require $project.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
Artisan::call('migrate', ['--force' => true]);
$tenant = Tenant::create(['nome' => 'Concurrency audit', 'slug' => 'concurrency-audit']);
$user = User::create(['name' => 'Audit', 'email' => 'concurrency@example.test', 'password' => bin2hex(random_bytes(20)), 'tenant_id' => $tenant->id]);
$opportunity = $user->opportunities()->create(['title' => 'Audit concurrency', 'property_type' => 'house', 'source' => 'manual']);
file_put_contents(__DIR__.'/fixture.private', json_encode(['token' => $user->createToken('isolated-audit')->plainTextToken, 'opportunity' => $opportunity->id]));
echo "Disposable audit schema migrated and fictional fixture created.\n";
