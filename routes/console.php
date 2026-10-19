<?php

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
