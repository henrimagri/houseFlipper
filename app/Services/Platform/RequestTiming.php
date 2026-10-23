<?php

namespace App\Services\Platform;

use Illuminate\Database\Events\QueryExecuted;

class RequestTiming
{
    public static function query(QueryExecuted $query): void
    {
        if (! config('observability.diagnostics') || ! request()->attributes->get('platform_request_id')) {
            return;
        }
        $r = request();
        $r->attributes->set('platform_db_count', $r->attributes->get('platform_db_count', 0) + 1);
        $r->attributes->set('platform_db_ms', $r->attributes->get('platform_db_ms', 0) + $query->time);
        if ($query->time >= 100) {
            $sql = preg_replace(["/'(?:[^']|'')*'/", '/\b\d+(?:\.\d+)?\b/'], '?', $query->sql);
            $slow = $r->attributes->get('platform_db_slow', []);
            if (count($slow) < 10) {
                $slow[] = ['fingerprint' => hash('sha256', $sql), 'ms' => round($query->time, 2)];
            }
            $r->attributes->set('platform_db_slow', $slow);
        }
    }

    public static function stage(string $name, int $start): void
    {
        if (config('observability.diagnostics')) {
            request()->attributes->set('platform_timing_'.$name, round((hrtime(true) - $start) / 1e6, 2));
        }
    }
}
