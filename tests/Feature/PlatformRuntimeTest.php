<?php

namespace Tests\Feature;

use App\Models\PlatformEvent;
use App\Services\Platform\OperationalHeartbeats;
use App\Services\Platform\RequestAggregates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class PlatformRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_heartbeat_states_use_observed_time_without_exposing_hostname(): void
    {
        $this->travelTo(now()->startOfSecond());
        $service = app(OperationalHeartbeats::class);
        $this->assertSame('unconfigured', $service->snapshot()['data']['scheduler']['status']);
        $id = (string) Str::uuid();
        $start = now()->subMinutes(10);
        $service->beat('scheduler', $id, '1.2.3', $start, hostname: 'private-hostname');
        $initial = $service->snapshot();
        $this->assertSame('online', $initial['data']['scheduler']['status']);
        $this->assertStringNotContainsString('private-hostname', json_encode($initial));
        $this->assertNotSame('private-hostname', DB::table('platform_heartbeats')->value('host_hash'));
        $this->travel(100)->seconds();
        $this->assertSame('delayed', $service->snapshot()['data']['scheduler']['status']);
        $this->travel(201)->seconds();
        $this->assertSame('offline', $service->snapshot()['data']['scheduler']['status']);
        $service->beat('scheduler', $id, '1.2.4');
        $this->assertSame(1, DB::table('platform_heartbeats')->count());
        $this->assertSame($start->format('Y-m-d H:i:s'), DB::table('platform_heartbeats')->value('started_at'));
        $this->assertSame('online', $service->snapshot()['data']['scheduler']['status']);
    }

    public function test_future_and_invalid_worker_identity_are_rejected(): void
    {
        $service = app(OperationalHeartbeats::class);
        foreach ([['invalid', (string) Str::uuid(), null], ['queue', 'bad-id', null], ['ai', (string) Str::uuid(), now()->addMinute()]] as [$type, $id, $seen]) {
            try {
                $service->beat($type, $id, '1.0', seenAt: $seen);
                $this->fail('Invalid heartbeat accepted.');
            } catch (InvalidArgumentException) {
                $this->assertSame(0, DB::table('platform_heartbeats')->count());
            }
        }
        $id = (string) Str::uuid();
        $service->beat('queue', $id, '1.0');
        $this->expectException(InvalidArgumentException::class);
        $service->beat('ai', $id, '1.0');
    }

    public function test_aggregation_is_bounded_idempotent_and_handles_late_arrivals(): void
    {
        $this->travelTo(now()->startOfMinute()->addSeconds(30));
        $service = app(RequestAggregates::class);
        foreach ([10, 20, 100, 500] as $duration) {
            PlatformEvent::create(['category' => 'api', 'event' => 'api.request', 'route' => 'api/items/{item}', 'method' => 'GET', 'tenant_id' => 41, 'product' => 'corretor', 'status' => 200, 'duration_ms' => $duration, 'created_at' => now()->subMinute()]);
        }
        $this->assertSame(2, $service->run(2)['processed']);
        $this->assertSame(2, $service->run(2)['processed']);
        $this->assertSame(0, $service->run()['processed']);
        PlatformEvent::create(['category' => 'api', 'event' => 'api.request', 'route' => 'api/items/{item}', 'method' => 'GET', 'tenant_id' => 41, 'product' => 'corretor', 'status' => 500, 'duration_ms' => 90, 'created_at' => now()->subMinute()]);
        PlatformEvent::create(['category' => 'api', 'event' => 'api.request', 'route' => 'api/items/{item}', 'method' => 'GET', 'tenant_id' => 42, 'product' => 'house_flipper', 'status' => 200, 'duration_ms' => 5, 'created_at' => now()->subMinute()]);
        $this->assertSame(2, $service->run()['processed']);
        $summary = $service->summary(now()->subDay()->toIso8601String(), now()->toIso8601String(), 41, 'corretor');
        $this->assertCount(1, $summary['data']);
        $row = $summary['data'][0];
        $this->assertSame(5, $row['count']);
        $this->assertSame(100, $row['p50_ms_estimate_upper_bound']);
        $this->assertSame(500, $row['p95_ms_estimate_upper_bound']);
        $this->assertSame(0.2, $row['error_rate']);
        $this->assertSame([], $service->summary(now()->subDay()->toIso8601String(), now()->toIso8601String(), 42, 'corretor')['data']);
        $this->assertSame(1, $service->summary(now()->subDay()->toIso8601String(), now()->toIso8601String(), 42, 'house_flipper')['data'][0]['count']);
        $this->assertSame(6, PlatformEvent::count());
        $this->assertStringNotContainsString('request_id', json_encode(DB::table('platform_request_aggregates')->get()));
    }

    public function test_open_minutes_are_deferred_and_null_durations_are_not_measured_zero(): void
    {
        $this->travelTo(now()->startOfMinute()->addSeconds(30));
        PlatformEvent::create(['category' => 'api', 'event' => 'api.request', 'route' => 'api/test', 'status' => 200, 'created_at' => now()]);
        $service = app(RequestAggregates::class);
        $this->assertSame(0, $service->run()['processed']);
        $this->travel(61)->seconds();
        $this->assertSame(1, $service->run()['processed']);
        $summary = $service->summary(now()->subDay()->toIso8601String(), now()->toIso8601String());
        $this->assertSame(0, $summary['data'][0]['latency_samples']);
        $this->assertNull($summary['data'][0]['p50_ms_estimate_upper_bound']);
        $this->artisan('platform:aggregate-requests', ['--limit' => '0'])->assertExitCode(2);
        $this->artisan('platform:heartbeat', ['type' => 'queue', 'worker_id' => (string) Str::uuid()])->assertSuccessful();
    }
}
