<?php

use App\Models\InvestmentAnalysis;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$fixture = json_decode(file_get_contents(__DIR__.'/fixture.private'), true);
$phase = $argv[1];
if ($phase === 'analyses') {
    $jobs = array_fill(0, 2, ['method' => 'POST', 'uri' => '/api/opportunities/'.$fixture['opportunity'].'/investment-analysis', 'body' => ['purchase_price' => 100000, 'down_payment' => 20000, 'expected_sale_price' => 150000, 'expected_months_until_sale' => 6]]);
} else {
    $analysis = InvestmentAnalysis::where('opportunity_id', $fixture['opportunity'])->where('is_primary', true)->firstOrFail();
    if ($phase === 'selection') {
        $jobs = [];
        foreach (['Audit A', 'Audit B'] as $label) {
            $detail = $analysis->financingSimulations()->create(['label' => $label, 'is_selected' => false]);
            $jobs[] = ['method' => 'PATCH', 'uri' => '/api/financing-details/'.$detail->id.'/select'];
        }
    } else {
        $jobs = [];
        foreach ([100, 200] as $amount) {
            $jobs[] = ['method' => 'POST', 'uri' => '/api/investment-analyses/'.$analysis->id.'/costs', 'body' => ['category' => 'other', 'description' => 'Audit concurrent cost', 'amount' => $amount, 'payment_status' => 'pending']];
        }
    }
}
foreach ($jobs as $i => $job) {
    file_put_contents(__DIR__."/$phase-$i.job", json_encode($job));
}
