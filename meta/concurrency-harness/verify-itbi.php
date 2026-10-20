<?php

use App\Models\ItbiImportBatch;
use App\Models\RealEstateTransaction;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$fixture = json_decode(file_get_contents(__DIR__.'/fixture.private'), true);
$results = array_map(fn (int $index) => json_decode(file_get_contents(__DIR__."/itbi-$index.result"), true), [0, 1]);
$outcomes = array_column($results, 'outcome');
sort($outcomes);
$batch = ItbiImportBatch::findOrFail($fixture['batch']);
$evidence = [
    'database' => 'MariaDB isolated',
    'worker_outcomes' => $outcomes,
    'requests_overlapped' => min(array_column($results, 'finished_at')) >= max(array_column($results, 'started_at')),
    'batch_status' => $batch->status,
    'total_rows' => $batch->total_rows,
    'imported_rows' => $batch->imported_rows,
    'updated_rows' => $batch->updated_rows,
    'failed_rows' => $batch->failed_rows,
    'transaction_count' => RealEstateTransaction::count(),
];
$evidence['passed'] = $evidence['worker_outcomes'] === ['completed', 'rejected_processing']
    && $evidence['requests_overlapped']
    && $evidence['batch_status'] === 'completed'
    && $evidence['total_rows'] === 500
    && $evidence['imported_rows'] === 500
    && $evidence['updated_rows'] === 0
    && $evidence['failed_rows'] === 0
    && $evidence['transaction_count'] === 500;

echo json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($evidence['passed'] ? 0 : 1);
