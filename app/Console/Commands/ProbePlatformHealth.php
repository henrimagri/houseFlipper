<?php

namespace App\Console\Commands;

use App\Services\Platform\HealthProbe;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProbePlatformHealth extends Command
{
    protected $signature = 'platform:probe-health';

    protected $description = 'Observe health snapshots without inventing historical uptime';

    public function handle(): int
    {
        $snapshot = app(HealthProbe::class)->snapshot();
        foreach ($snapshot['services'] as $name => $service) {
            DB::table('platform_health_samples')->updateOrInsert(['service' => $name, 'bucket_at' => now()->startOfMinute()], ['status' => $service['status']]);
        }
        $this->info('Health statuses recorded; no endpoint, credential or payload stored.');

        return self::SUCCESS;
    }
}
