<?php

use App\Services\Platform\OperationalHeartbeats;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('itbi:sync')
    ->monthlyOn((int) config('real_estate_data.itbi_sp.import_day', 5), '03:00')
    ->withoutOverlapping();

Schedule::command('data-sources:probe')
    ->everyThirtyMinutes()
    ->withoutOverlapping(10)
    ->runInBackground();

Schedule::command('platform:evaluate-alerts')->everyMinute()->withoutOverlapping();
Schedule::command('platform:prune --execute')->dailyAt('04:30')->withoutOverlapping();

Schedule::call(function () {
    $id = config('observability.scheduler_id');
    if ($id) {
        app(OperationalHeartbeats::class)->beat('scheduler', $id, 'laravel-'.app()->version());
    }
})->name('platform.scheduler-heartbeat')->everyMinute()->withoutOverlapping();
Schedule::command('platform:aggregate-requests --limit=10000')->everyMinute()->withoutOverlapping();

Schedule::command('platform:probe-health')->everyMinute()->withoutOverlapping();
