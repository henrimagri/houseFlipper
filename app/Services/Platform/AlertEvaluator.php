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
            if ($control->key === 'correlated_auth') {
                $fired += $this->correlatedAuth($control, $window, $threshold);

                continue;
            }
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
            $groups = ($v['group_by'] ?? 'global') === 'ip_hash' ? (clone $query)->whereNotNull('ip_hash')->selectRaw('ip_hash,count(*) as count')->groupBy('ip_hash')->havingRaw('count(*) >= ?', [$threshold])->orderByDesc('count')->limit(100)->get() : [(object) ['ip_hash' => null, 'count' => $query->count()]];
            foreach ($groups as $group) {
                $count = (int) $group->count;
                if ($count < $threshold) {
                    continue;
                }
                $severity = in_array($v['severity'] ?? 'high', ['medium', 'high', 'critical'], true) ? ($v['severity'] ?? 'high') : 'high';
                $key = 'alert-'.$control->key.'-'.($group->ip_hash ?? 'global').'-'.intdiv(now()->timestamp, $window);
                DB::transaction(function () use ($control, $key, $count, $severity, $group, &$fired) {
                    $e = app(EventRecorder::class)->record('security', 'platform.alert.triggered', ['rule' => $control->key, 'count' => $count, 'outcome' => 'threshold_reached'], severity: $severity, dedupe: $key);
                    if (! $e->wasRecentlyCreated) {
                        return;
                    }
                    if ($group->ip_hash) {
                        $e->update(['ip_hash' => $group->ip_hash, 'ip_mask' => null]);
                    }
                    PlatformIncident::create(['title' => 'Alerta: '.$control->key, 'severity' => $severity, 'status' => 'open', 'created_by' => 0, 'event_ids' => [$e->id]]);
                    $fired++;
                });
            }
        }

        return $fired;
    }

    private function correlatedAuth(PlatformControl $control, int $window, int $threshold): int
    {
        $until = now();
        $since = $until->copy()->subSeconds($window);
        $account = DB::connection()->getQueryGrammar()->wrap('metadata->account_hash');
        $knownAccount = 'CASE WHEN LENGTH('.$account.') = 64 THEN '.$account.' ELSE NULL END';
        // account_hash is an HMAC produced by ObservePlatformRequest, never an
        // address or an assertion that this IP identifies a single person.
        $groups = PlatformEvent::query()->whereBetween('created_at', [$since, $until])
            ->where('category', 'auth')->where('event', 'auth.login.failed')->whereNotNull('ip_hash')
            ->selectRaw('ip_hash, COUNT(*) as failures, COUNT(DISTINCT '.$knownAccount.') as accounts')
            ->groupBy('ip_hash')->havingRaw('COUNT(*) >= ?', [$threshold])
            ->havingRaw('COUNT(DISTINCT '.$knownAccount.') >= 5')->orderByDesc('failures')->limit(100)->get();
        $fired = 0;
        foreach ($groups as $group) {
            $evidence = PlatformEvent::query()->whereBetween('created_at', [$since, $until])->where('ip_hash', $group->ip_hash);
            $denied = (clone $evidence)->where('category', 'security')->where('event', 'security.access.denied')
                ->whereIn('status', [401, 403])->where(fn ($q) => $q->where('route', 'api/platform')->orWhere('route', 'like', 'api/platform/%'));
            $limited = (clone $evidence)->where('category', 'security')->where('event', 'auth.rate_limited')->where('status', 429);
            if (! (clone $denied)->exists() || (clone $limited)->count() < 2) {
                continue;
            }
            // Preserve each required signal in the linked evidence, even when
            // thousands of failures would otherwise fill the 30-event cap.
            $ids = [...(clone $denied)->orderBy('id')->limit(1)->pluck('id')->all(), ...(clone $limited)->orderBy('id')->limit(2)->pluck('id')->all()];
            $loginEvidence = (clone $evidence)->where('category', 'auth')->where('event', 'auth.login.failed')->whereRaw('LENGTH('.$account.') = 64');
            $representatives = (clone $loginEvidence)->selectRaw('MIN(id) as id')->groupByRaw($account)->orderBy('id')->limit(5)->pluck('id')->all();
            $ids = array_values(array_unique([...$ids, ...$representatives, ...(clone $loginEvidence)->orderByDesc('id')->limit(21)->pluck('id')->all()]));
            $severity = in_array($control->value['severity'] ?? 'high', ['medium', 'high', 'critical'], true) ? ($control->value['severity'] ?? 'high') : 'high';
            $key = 'alert-correlated_auth-'.$group->ip_hash.'-'.intdiv($until->timestamp, $window);
            DB::transaction(function () use ($group, $severity, $key, $ids, &$fired) {
                $event = app(EventRecorder::class)->record('security', 'platform.alert.triggered', ['rule' => 'correlated_auth', 'count' => (int) $group->failures, 'outcome' => 'correlation_suggested'], severity: $severity, dedupe: $key);
                if (! $event->wasRecentlyCreated) {
                    return;
                }
                $event->update(['ip_hash' => $group->ip_hash, 'ip_mask' => null]);
                PlatformIncident::create(['title' => 'Revisão sugerida: correlação de autenticação', 'severity' => $severity, 'status' => 'open', 'created_by' => 0, 'event_ids' => array_slice([$event->id, ...$ids], 0, 30)]);
                $fired++;
            });
        }

        return $fired;
    }
}
