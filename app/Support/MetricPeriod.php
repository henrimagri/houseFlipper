<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class MetricPeriod
{
    public static function fromRequest(Request $request, int $defaultDays = 30): array
    {
        $from = $request->date('from')?->startOfDay() ?? now()->subDays($defaultDays)->startOfDay();
        $to = $request->date('to')?->endOfDay() ?? now()->endOfDay();
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [
            CarbonImmutable::parse($from),
            CarbonImmutable::parse($to),
        ];
    }
}
