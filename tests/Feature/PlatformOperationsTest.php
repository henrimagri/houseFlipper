<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Models\PlatformEvent;
use App\Models\User;
use App\Services\Platform\EventRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformOperationsTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): User
    {
        $u = User::factory()->create(['email_verified_at' => now()]);
        PlatformAdmin::create(['user_id' => $u->id, 'email' => $u->email]);
        Sanctum::actingAs($u);

        return $u;
    }

    public function test_admin_gate_rejects_guest_every_non_platform_role_and_unverified_or_suspended_operator(): void
    {
        $this->getJson('/api/platform/overview')->assertUnauthorized();
        foreach (['common', 'corretor', 'imobiliaria', 'house_flipper', 'no-product', 'foreign-tenant'] as $name) {
            $u = User::factory()->create(['name' => $name, 'email_verified_at' => now()]);
            Sanctum::actingAs($u);
            $this->getJson('/api/platform/overview')->assertForbidden();
        }
        $u = $this->operator();
        $this->getJson('/api/platform/overview')->assertOk();
        $u->forceFill(['email_verified_at' => null, 'requires_email_verification' => false])->save();
        $this->getJson('/api/platform/overview')->assertForbidden();
        $u->forceFill(['email_verified_at' => now(), 'ativo_flg' => false])->save();
        $this->getJson('/api/platform/overview')->assertUnauthorized();
    }

    public function test_request_logs_never_copy_headers_query_values_body_or_unmatched_paths(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer SECRET_TOKEN', 'Cookie' => 'SECRET_COOKIE'])->postJson('/api/auth/login', ['email' => 'not-a-valid-email', 'password' => 'SECRET_PASSWORD'])->assertUnprocessable();
        $this->getJson('/api/no-secret-PRIVATE_PATH?token=SECRET_QUERY')->assertNotFound();
        $stored = PlatformEvent::all()->toJson();
        foreach (['SECRET_TOKEN', 'SECRET_COOKIE', 'SECRET_PASSWORD', 'PRIVATE_PATH', 'SECRET_QUERY', 'not-a-valid-email'] as $secret) {
            $this->assertStringNotContainsString($secret, $stored);
        }
        $this->assertDatabaseHas('platform_events', ['route' => '[unmatched]', 'status' => 404]);
        $this->assertDatabaseHas('platform_events', ['event' => 'auth.login.failed']);
    }

    public function test_metadata_allowlist_deduplication_and_audit(): void
    {
        $u = $this->operator();
        $rec = app(EventRecorder::class);
        $a = $rec->record('analytics', 'product.used', ['password' => 'SECRET', 'headers' => ['Authorization' => 'TOKEN'], 'feature' => 'paint'], dedupe: 'same');
        $b = $rec->record('analytics', 'product.used', ['feature' => 'paint'], dedupe: 'same');
        $this->assertSame($a->id, $b->id);
        $this->assertSame(['feature' => 'paint'], $a->metadata);
        $this->getJson('/api/platform/overview')->assertOk()->assertHeader('X-Request-ID');
        $this->assertDatabaseHas('platform_events', ['category' => 'audit', 'event' => 'admin.resource.viewed', 'user_id' => $u->id]);
    }

    public function test_retention_dry_run_and_audit_minimum(): void
    {
        PlatformEvent::create(['category' => 'api', 'event' => 'api.request.completed', 'created_at' => now()->subDays(31)]);
        PlatformEvent::create(['category' => 'audit', 'event' => 'admin.test', 'created_at' => now()->subDays(31)]);
        config(['observability.retention.audit' => 1]);
        $this->artisan('platform:prune')->assertSuccessful();
        $this->assertSame(2, PlatformEvent::count());
        $this->artisan('platform:prune --execute')->assertSuccessful();
        $this->assertSame(1, PlatformEvent::count());
        $this->assertSame('audit', PlatformEvent::first()->category);
    }
}
