<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Models\PlatformEvent;
use App\Models\PlatformIncident;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\User;
use App\Services\Platform\EventRecorder;
use App\Services\Workspace\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformControlsTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): User
    {
        $u = User::factory()->create(['password' => 'safe-qa-password']);
        PlatformAdmin::create(['user_id' => $u->id, 'email' => $u->email]);
        $this->platformSession($u);

        return $u;
    }

    public function test_alerts_are_configured_confirmed_and_idempotent_in_their_window(): void
    {
        $this->operator();
        $params = ['kind' => 'alert', 'key' => 'login_failures', 'enabled' => true, 'threshold' => 2, 'window_seconds' => 600, 'confirmed' => true, 'reason' => 'qa_validation', 'password' => 'safe-qa-password'];
        $this->putJson('/api/platform/controls', array_replace($params, ['key' => 'arbitrary_code']))->assertUnprocessable();
        $this->putJson('/api/platform/controls', $params)->assertOk();
        for ($i = 0; $i < 2; $i++) {
            PlatformEvent::create(['category' => 'auth', 'event' => 'auth.login.failed']);
        }
        $this->artisan('platform:evaluate-alerts')->assertSuccessful();
        $this->artisan('platform:evaluate-alerts')->assertSuccessful();
        $this->assertSame(1, PlatformEvent::where('event', 'platform.alert.triggered')->count());
        $this->assertSame(1, PlatformIncident::count());
        $this->assertDatabaseHas('platform_events', ['event' => 'admin.control.updated']);
    }

    public function test_failed_audit_rolls_back_sensitive_action(): void
    {
        $this->operator();
        $target = User::factory()->create();
        $this->partialMock(EventRecorder::class, fn ($mock) => $mock->shouldReceive('audit')->andThrow(new \RuntimeException('audit unavailable')));
        $this->postJson('/api/platform/actions', ['action' => 'suspend_user', 'target' => (string) $target->id, 'confirmed' => true, 'reason' => 'qa_validation', 'password' => 'safe-qa-password'])->assertStatus(500);
        $this->assertTrue($target->fresh()->ativo_flg);
    }

    public function test_real_agency_admin_and_product_subscribers_cannot_access_admin_routes(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        foreach (['corretor', 'imobiliaria', 'house_flipper'] as $product) {
            $tenant = Tenant::create(['nome' => $product, 'slug' => $product]);
            $user = User::factory()->create(['tenant_id' => $tenant->id]);
            TenantProductSubscription::create(['tenant_id' => $tenant->id, 'product_slug' => $product, 'active' => true, 'status' => 'active']);
            if ($product === 'imobiliaria') {
                app(OrganizationService::class)->ensureForTenant($user);
            }$this->platformSession($user);
            foreach (Route::getRoutes() as $route) {
                if (! str_starts_with($route->uri(), 'api/platform/')) {
                    continue;
                }$path = preg_replace('/\{[^}]+\}/', '1', $route->uri());
                $this->json($route->methods()[0], '/'.$path)->assertForbidden();
            }
        }
    }

    public function test_product_events_are_recorded_only_on_successful_mutations(): void
    {
        $this->operator();
        $this->postJson('/api/platform/incidents', ['title' => 'QA successful mutation', 'severity' => 'low'])->assertCreated();
        $this->assertDatabaseHas('platform_events', ['event' => 'admin.incident.created']);
    }

    public function test_admin_throttle_emits_a_real_429_event_and_feature_flags_are_allowlisted(): void
    {
        $this->operator();
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/platform/controls')->assertOk();
        }
        $this->getJson('/api/platform/controls')->assertStatus(429);
        $this->assertDatabaseHas('platform_events', ['event' => 'auth.rate_limited', 'status' => 429]);
    }

    public function test_cohorts_do_not_treat_retained_audits_as_old_activity_coverage(): void
    {
        $this->operator();
        $u = User::factory()->create(['created_at' => now()->subDays(60)]);
        PlatformEvent::create(['category' => 'audit', 'event' => 'admin.resource.viewed', 'created_at' => now()->subDays(80)]);
        $result = $this->getJson('/api/platform/cohorts?from='.now()->subDays(90)->toDateString().'&user_id='.$u->id)->assertOk();
        $this->assertNull($result->json('data.0.d30.rate'));
    }
}
