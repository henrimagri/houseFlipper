<?php

namespace App\Services\Platform;

use App\Models\PlatformControl;
use App\Models\PlatformEvent;
use App\Models\PlatformIncident;
use Illuminate\Support\Facades\DB;

class AlertEvaluator
{
    public function run(): int
    {
        $fired = 0;
        foreach (PlatformControl::where('kind', 'alert')->limit(20)->get() as $control) {
            $v = $control->value;
            if (! ($v['enabled'] ?? false)) {
                continue;
            }$window = max(60, min(3600, (int) ($v['window_seconds'] ?? 600)));
            $threshold = max(1, min(10000, (int) ($v['threshold'] ?? 100)));
            $query = PlatformEvent::where('created_at', '>=', now()->subSeconds($window));
            if ($control->key === 'login_failures') {
                $query->where('event', 'auth.login.failed');
            } elseif ($control->key === 'server_errors') {
                $query->where('category', 'api')->where('status', '>=', 500);
            } elseif ($control->key === 'rate_limits') {
                $query->where('event', 'auth.rate_limited');
            } else {
                continue;
            }
            $count = $query->count();
            if ($count < $threshold) {
                continue;
            }
            $key = 'alert-'.$control->key.'-'.intdiv(now()->timestamp, $window);
            DB::transaction(function () use ($control, $key, $count, &$fired) {
                $e = app(EventRecorder::class)->record('security', 'platform.alert.triggered', ['rule' => $control->key, 'count' => $count, 'outcome' => 'threshold_reached'], severity: 'high', dedupe: $key);
                if (! $e->wasRecentlyCreated) {
                    return;
                }PlatformIncident::create(['title' => 'Alerta: '.$control->key, 'severity' => 'high', 'status' => 'open', 'created_by' => 0, 'event_ids' => [$e->id]]);
                $fired++;
            });
        }

        return $fired;
    }
}
