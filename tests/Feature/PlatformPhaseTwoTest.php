<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Models\PlatformEvent;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\User;
use App\Services\Platform\RequestAggregates;
use App\Services\Platform\ResourceQuota;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PlatformPhaseTwoTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        PlatformAdmin::create(['user_id' => $u->id, 'email' => $u->email]);
        $this->platformSession($u);

        return $u;
    }

    public function test_runtime_and_readiness_are_authorized_and_never_infer_running_daemons(): void
    {
        $this->getJson('/api/platform/heartbeats')->assertUnauthorized();
        $this->admin();
        $this->getJson('/api/platform/heartbeats')->assertOk()->assertJsonPath('data.scheduler.status', 'unconfigured');
        $r = $this->getJson('/api/platform/readiness')->assertOk();
        $checks = collect($r->json('data'))->keyBy('key');
        $this->assertSame('approved', $checks['admin_mfa']['status']);
        $this->assertSame('not_configured', $checks['backup_restore']['status']);
        $this->assertSame('blocked', $checks['mysql_validation']['status']);
        $this->assertLessThan(100, $r->json('score'));
    }

    public function test_quota_admission_is_idempotent_and_isolated_and_undeclared_limits_remain_null(): void
    {
        $tenant = Tenant::create(['nome' => 'A', 'slug' => 'resource-a']);
        $other = Tenant::create(['nome' => 'B', 'slug' => 'resource-b']);
        $u = User::factory()->create(['tenant_id' => $tenant->id]);
        $v = User::factory()->create(['tenant_id' => $other->id]);
        foreach ([$tenant, $other] as $t) {
            TenantProductSubscription::create(['tenant_id' => $t->id, 'product_slug' => 'corretor', 'plan_slug' => 'quota_fixture', 'active' => true, 'status' => 'active']);
        }
        config(['platform_resources.plans.quota_fixture.ai_requests' => 1]);
        $service = app(ResourceQuota::class);
        $operation = (string) Str::uuid();
        $service->admit($u, 'corretor', 'ai_requests', $operation);
        $service->admit($u, 'corretor', 'ai_requests', $operation);
        $service->admit($v, 'corretor', 'ai_requests', (string) Str::uuid());
        $snapshot = $service->snapshot($tenant->id, 'corretor');
        $this->assertSame(1, (int) $snapshot['data'][0]['used']);
        $this->assertNull($snapshot['data'][1]['limit']);
        $this->assertNull($snapshot['data'][1]['used']);
        $this->assertFalse($snapshot['data'][1]['enforced']);
        $this->assertTrue($snapshot['data'][0]['enforced']);
        try {
            $service->admit($u, 'corretor', 'ai_requests', (string) Str::uuid());
            $this->fail('Quota accepted over limit');
        } catch (HttpException $e) {
            $this->assertSame(429, $e->getStatusCode());
        }
        $this->assertDatabaseCount('platform_resource_admissions', 2);
    }

    public function test_retention_holds_api_events_until_aggregation_has_consumed_them(): void
    {
        PlatformEvent::create(['category' => 'api', 'event' => 'api.request.completed', 'created_at' => now()->subDays(40), 'duration_ms' => 120]);
        $this->artisan('platform:prune --execute')->assertSuccessful();
        $this->assertDatabaseCount('platform_events', 1);
        app(RequestAggregates::class)->run();
        $this->artisan('platform:prune --execute')->assertSuccessful();
        $this->assertDatabaseCount('platform_events', 0);
        $this->assertDatabaseCount('platform_request_aggregates', 1);
    }

    public function test_inventory_is_bounded_and_never_deletes_unknown_files(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Storage::disk('local')->put('qa/photo.jpg', 'authorized-fixture');
        Storage::disk('local')->put('qa/other.txt', 'second');
        $this->artisan('platform:inventory-storage --limit=1')->assertSuccessful();
        $this->assertDatabaseCount('platform_storage_inventory', 1);
        Storage::disk('local')->assertExists('qa/photo.jpg');
        Storage::disk('local')->assertExists('qa/other.txt');
        $this->assertStringNotContainsString('photo.jpg', json_encode(DB::table('platform_storage_inventory')->get()));
    }

    public function test_timing_diagnostics_are_opt_in_and_never_capture_query_or_bindings(): void
    {
        $this->admin();
        config(['observability.diagnostics' => true]);
        Route::middleware(['api', 'auth:sanctum', 'active-account', 'verified-account', 'platform-admin'])->get('/api/platform/qa-timing', function () {
            event(new QueryExecuted("select * from users where email='PRIVATE_EMAIL'", ['PRIVATE_BINDING'], 125, DB::connection()));

            return response()->json(['ok' => true]);
        });
        $this->getJson('/api/platform/qa-timing')->assertOk();
        $event = PlatformEvent::where('category', 'api')->where('route', 'api/platform/qa-timing')->firstOrFail();
        $this->assertGreaterThan(0, $event->metadata['timings']['database_count']);
        $this->assertCount(1, $event->metadata['timings']['slow_queries']);
        $this->assertStringNotContainsString('PRIVATE_EMAIL', $event->toJson());
        $this->assertStringNotContainsString('PRIVATE_BINDING', $event->toJson());
    }

    public function test_incident_notes_and_priority_are_audited_without_copying_note_contents(): void
    {
        $admin = $this->admin();
        $id = $this->postJson('/api/platform/incidents', ['title' => 'QA operational incident', 'severity' => 'low'])->assertCreated()->json('data.id');
        $event = PlatformEvent::create(['category' => 'security', 'event' => 'qa.event', 'created_at' => now()]);
        $this->patchJson('/api/platform/incidents/'.$id, ['status' => 'investigating', 'assigned_user_id' => $admin->id, 'severity' => 'high', 'note' => 'PRIVATE_INCIDENT_NOTE', 'event_ids' => [$event->id]])->assertOk();
        $result = $this->getJson('/api/platform/incidents')->assertOk();
        $this->assertSame('PRIVATE_INCIDENT_NOTE', $result->json('data.0.notes.0.note'));
        $this->assertSame('high', $result->json('data.0.severity'));
        $this->assertStringNotContainsString('PRIVATE_INCIDENT_NOTE', PlatformEvent::where('category', 'audit')->get()->toJson());
    }

    public function test_health_history_records_only_actual_probes_and_labels_observed_time(): void
    {
        Http::fake(['*' => Http::response([], 200)]);
        $this->assertDatabaseCount('platform_health_samples', 0);
        $this->artisan('platform:probe-health')->assertSuccessful();
        $this->assertGreaterThan(0, DB::table('platform_health_samples')->count());
        $this->admin();
        $r = $this->getJson('/api/platform/healthHistory')->assertOk();
        $this->assertStringContainsString('Não é SLA', $r->json('method'));
        $this->getJson('/api/platform/apm?user_id=1')->assertUnprocessable();
    }
}
