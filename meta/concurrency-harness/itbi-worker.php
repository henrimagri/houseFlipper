<?php

use App\Models\ItbiImportBatch;
use App\Services\RealEstateData\ItbiTransactionImportService;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$fixture = json_decode(file_get_contents(__DIR__.'/fixture.private'), true);
$prefix = $argv[1];
file_put_contents($prefix.'.ready', 'ready');
$deadline = microtime(true) + 15;
while (! file_exists($prefix.'.go')) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Barrier timeout');
    }
    usleep(10000);
}

$startedAt = microtime(true);
try {
    $batch = app(ItbiTransactionImportService::class)->import(ItbiImportBatch::findOrFail($fixture['batch']));
    $outcome = $batch->status;
    $message = null;
} catch (Throwable $exception) {
    $outcome = $exception->getMessage() === 'Este lote já está sendo processado.' ? 'rejected_processing' : 'unexpected_error';
    $message = $exception->getMessage();
}

file_put_contents($prefix.'.result', json_encode([
    'outcome' => $outcome,
    'message' => $message,
    'started_at' => $startedAt,
    'finished_at' => microtime(true),
]));
