<?php

use App\Models\ItbiImportBatch;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

$project = dirname(__DIR__, 2);
$password = trim(file_get_contents(__DIR__.'/db-password.private'));
$pdo = new PDO('mysql:host=127.0.0.1;port=13308', 'root', $password);
$pdo->exec('DROP DATABASE IF EXISTS houseflipper_audit');
$pdo->exec('CREATE DATABASE houseflipper_audit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

require $project.'/vendor/autoload.php';
$app = require $project.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
Artisan::call('migrate', ['--force' => true]);

$path = 'audit/concurrent-itbi.csv';
$rows = ['SQL;Data da Transacao;Valor da Transacao;Tipo do Imovel;Logradouro;Numero;Bairro;Area Util;Cartorio'];
for ($index = 1; $index <= 500; $index++) {
    $rows[] = sprintf('%06d;15/05/2025;500000;Apartamento;Rua Concorrencia;%d;Moema;100;1 RI', $index, $index);
}
Storage::disk('local')->put($path, implode("\n", $rows));
$batch = ItbiImportBatch::create([
    'provider' => 'concurrency_audit',
    'source_url' => 'upload://concurrent-itbi.csv',
    'source_filename' => 'concurrent-itbi.csv',
    'storage_path' => $path,
    'status' => 'downloaded',
]);
file_put_contents(__DIR__.'/fixture.private', json_encode(['batch' => $batch->id, 'storage_path' => $path]));

echo "Disposable MariaDB schema and 500-row ITBI fixture created.\n";
