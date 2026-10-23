<?php

namespace Tests\Feature;

use App\Models\PlatformControl;
use App\Models\PlatformEvent;
use App\Models\PlatformIncident;
use App\Services\Platform\AlertEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformCorrelationTest extends TestCase
{
    use RefreshDatabase;

    private function rule(bool $enabled = true): void
    {
        // Global is intentionally ignored: correlation must always group by IP.
        PlatformControl::updateOrCreate(['kind' => 'alert', 'key' => 'correlated_auth'], ['value' => ['enabled' => $enabled, 'threshold' => 5, 'window_seconds' => 600, 'group_by' => 'global']]);
    }

    private function signals(string $ip, int $accounts = 5, int $rates = 2, string $route = 'api/platform/overview'): void
    {
        for ($i = 0; $i < 5; $i++) {
            PlatformEvent::create(['category' => 'auth', 'event' => 'auth.login.failed', 'ip_hash' => $ip, 'metadata' => ['account_hash' => hash('sha256', 'fixture-account-'.($i % $accounts))], 'created_at' => now()]);
        }
        PlatformEvent::create(['category' => 'security', 'event' => 'security.access.denied', 'status' => 403, 'route' => $route, 'ip_hash' => $ip, 'created_at' => now()]);
        for ($i = 0; $i < $rates; $i++) {
            PlatformEvent::create(['category' => 'security', 'event' => 'auth.rate_limited', 'status' => 429, 'ip_hash' => $ip, 'created_at' => now()]);
        }
    }

    public function test_rule_is_opt_in_and_deduplicates_per_ip_and_time_bucket_without_blocking(): void
    {
        $this->freezeTime();
        $this->signals(str_repeat('a', 64));
        $this->assertSame(0, app(AlertEvaluator::class)->run());
        $this->rule(false);
        $this->assertSame(0, app(AlertEvaluator::class)->run());
        $this->rule();
        PlatformControl::where('key', 'correlated_auth')->update(['value' => ['enabled' => true, 'threshold' => 6, 'window_seconds' => 600]]);
        PlatformEvent::create(['category' => 'auth', 'event' => 'auth.login.failed', 'ip_hash' => str_repeat('a', 64), 'created_at' => now()]);
        $this->assertSame(1, app(AlertEvaluator::class)->run());
        $this->assertSame(0, app(AlertEvaluator::class)->run());
        $this->assertSame(1, PlatformIncident::count());
        $incident = PlatformIncident::firstOrFail();
        $linked = PlatformEvent::whereIn('id', $incident->event_ids)->get();
        $this->assertLessThanOrEqual(30, count($incident->event_ids));
        $this->assertSame(5, $linked->where('event', 'auth.login.failed')->count());
        $this->assertSame(1, $linked->where('event', 'security.access.denied')->count());
        $this->assertSame(2, $linked->where('event', 'auth.rate_limited')->count());
        $this->assertFalse(PlatformControl::where('kind', 'ip')->exists());
        $this->assertDatabaseHas('platform_events', ['event' => 'platform.alert.triggered', 'ip_hash' => str_repeat('a', 64)]);
    }

    public function test_all_independent_signals_and_distinct_accounts_are_required_on_same_ip(): void
    {
        $this->rule();
        $this->signals(str_repeat('a', 64), accounts: 4);
        $this->signals(str_repeat('b', 64), rates: 1);
        $this->signals(str_repeat('c', 64), route: 'api/propriedades');
        $this->assertSame(0, app(AlertEvaluator::class)->run());
        $this->signals(str_repeat('d', 64));
        $this->assertSame(1, app(AlertEvaluator::class)->run());
        $this->assertSame(1, PlatformIncident::count());
    }

    public function test_old_or_future_evidence_does_not_complete_a_current_correlation(): void
    {
        $this->freezeTime();
        $this->rule();
        $this->signals(str_repeat('a', 64));
        PlatformEvent::where('event', 'auth.rate_limited')->update(['created_at' => now()->subSeconds(601)]);
        $this->assertSame(0, app(AlertEvaluator::class)->run());
        PlatformEvent::where('event', 'auth.rate_limited')->update(['created_at' => now()->addSecond()]);
        $this->assertSame(0, app(AlertEvaluator::class)->run());
        PlatformEvent::where('event', 'auth.rate_limited')->update(['created_at' => now()]);
        $this->assertSame(1, app(AlertEvaluator::class)->run());
    }
}
