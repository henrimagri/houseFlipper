<?php

use App\Models\InvestmentAnalysis;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$fixture = json_decode(file_get_contents(__DIR__.'/fixture.private'), true);
$query = InvestmentAnalysis::where('opportunity_id', $fixture['opportunity']);
$analysis = (clone $query)->where('is_primary', true)->firstOrFail();
$evidence = ['database' => 'MariaDB isolated', 'analyses' => $query->count(), 'primary_analyses' => (clone $query)->where('is_primary', true)->count(), 'selected_financing' => $analysis->financingSimulations()->where('is_selected', true)->count(), 'cost_rows' => $analysis->costs()->count(), 'cost_sum' => (float) $analysis->costs()->sum('amount'), 'cached_cost_total' => (float) $analysis->detailed_costs_total];
$evidence['passed'] = $evidence['analyses'] === 2 && $evidence['primary_analyses'] === 1 && $evidence['selected_financing'] === 1 && $evidence['cost_rows'] === 2 && $evidence['cost_sum'] === 300.0 && $evidence['cached_cost_total'] === 300.0;
echo json_encode($evidence, JSON_PRETTY_PRINT).PHP_EOL;
exit($evidence['passed'] ? 0 : 1);
