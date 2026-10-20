<?php

use App\Models\Propriedade;
use App\Models\Venda;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$fixture = json_decode(file_get_contents(__DIR__.'/fixture.private'), true);
$results = array_map(fn (int $index) => json_decode(file_get_contents(__DIR__."/sale-$index.result"), true), [0, 1]);
$statuses = array_column($results, 'status');
sort($statuses);
$sales = Venda::where('propriedade_id', $fixture['property'])->get();
$property = Propriedade::findOrFail($fixture['property']);

$evidence = [
    'database' => 'MariaDB isolated',
    'worker_statuses' => $statuses,
    'requests_overlapped' => min(array_column($results, 'finished_at')) >= max(array_column($results, 'started_at')),
    'active_final_sales' => $sales->where('deletado_flg', false)->whereIn('status', ['fechada', 'escriturada'])->count(),
    'sale_values' => $sales->pluck('valor_venda')->map(fn ($value) => (float) $value)->values()->all(),
    'property_status' => $property->status,
];
$evidence['passed'] = $evidence['worker_statuses'] === [201, 422]
    && $evidence['requests_overlapped']
    && $evidence['active_final_sales'] === 1
    && count($evidence['sale_values']) === 1
    && $evidence['property_status'] === 'vendida';

echo json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($evidence['passed'] ? 0 : 1);
