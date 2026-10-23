<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('local') || DB::getDriverName() !== 'sqlite' || config('queue.connections.platform_ops.driver') !== 'database') {
    exit(2);
}
$before = DB::table('jobs')->where('queue', 'platform-ops')->count();
if ($before !== 0) {
    echo json_encode(['skipped' => 'queue_not_empty', 'queued' => $before]);
    exit(2);
}
$started = now();
$exit = Artisan::call('queue:work', ['connection' => 'platform_ops', '--queue' => 'platform-ops', '--stop-when-empty' => true, '--max-time' => 10, '--max-jobs' => 1, '--sleep' => 1, '--timeout' => 150, '--tries' => 1]);
$observed = DB::table('platform_heartbeats')->where('type', 'queue')->where('last_seen', '>=', $started)->count();
$report = ['exit' => $exit, 'initial_queue_empty' => true, 'fixture_job_dispatched' => false, 'observed_queue_heartbeats' => $observed, 'persistent_worker' => false, 'scope' => 'one bounded real worker loop; no daemon installation', 'checked_at' => now()->toIso8601String()];
file_put_contents(__DIR__.'/platform-phase2-queue-smoke.json', json_encode($report, JSON_PRETTY_PRINT));
echo json_encode($report);
exit($exit === 0 && $observed > 0 ? 0 : 1);
