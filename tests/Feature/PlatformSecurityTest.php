<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Models\PlatformControl;
use App\Models\PlatformEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): User
    {
        $u = User::factory()->create(['email_verified_at' => now(), 'password' => 'test-password']);
        PlatformAdmin::create(['user_id' => $u->id, 'email' => $u->email]);
        Sanctum::actingAs($u);

        return $u;
    }

    public function test_every_admin_route_rejects_regular_user(): void
    {

        $this->withoutMiddleware(ThrottleRequests::class);
        Sanctum::actingAs(User::factory()->create());

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/platform/')) {
                continue;
            }$path = preg_replace('/\{[^}]+\}/', '1', $route->uri());
            $method = $route->methods()[0];
            $this->json($method, '/'.$path)->assertForbidden();
        }

    }

    public function test_revoke_and_suspend_require_reauthentication_and_audit(): void
    {

        $admin = $this->operator();
        $user = User::factory()->create();
        $token = $user->createToken('private-token-name')->plainTextToken;

        $payload = ['action' => 'suspend_user', 'target' => (string) $user->id, 'reason' => 'qa_validation'];

        $this->postJson('/api/platform/actions', $payload)->assertUnprocessable();

        $this->postJson('/api/platform/actions', $payload + ['confirmed' => true, 'password' => 'wrong'])->assertForbidden();

        $this->assertTrue($user->fresh()->ativo_flg);

        $this->postJson('/api/platform/actions', $payload + ['confirmed' => true, 'password' => 'test-password'])->assertOk();

        $this->assertFalse($user->fresh()->ativo_flg);
        $this->assertSame(0, $user->tokens()->count());

        $this->assertDatabaseHas('platform_events', ['event' => 'admin.suspend_user', 'category' => 'audit']);

        $this->assertStringNotContainsString('test-password', PlatformEvent::all()->toJson());

        $this->postJson('/api/platform/actions', ['action' => 'suspend_user', 'target' => (string) $admin->id, 'reason' => 'qa_validation', 'confirmed' => true, 'password' => 'test-password'])->assertUnprocessable();

    }

    public function test_security_signals_are_suspicions_without_payload_and_incidents_are_audited(): void
    {

        $this->operator();
        $this->getJson('/api/platform/events?search='.urlencode('<script>UNIQUE_PAYLOAD</script>'))->assertOk();

        $e = PlatformEvent::where('event', 'security.signal.detected')->firstOrFail();
        $this->assertSame('possible_xss', $e->metadata['rule']);
        $this->assertStringNotContainsString('UNIQUE_PAYLOAD', $e->toJson());

        $this->getJson('/api/platform/events/'.$e->id)->assertOk();

        $id = $this->postJson('/api/platform/incidents', ['title' => 'QA safe incident', 'severity' => 'medium', 'event_ids' => [$e->id]])->assertCreated()->json('data.id');

        $this->patchJson('/api/platform/incidents/'.$id, ['status' => 'resolved'])->assertUnprocessable();

        $this->patchJson('/api/platform/incidents/'.$id, ['status' => 'false_positive', 'resolution' => 'Evento seguro de QA'])->assertOk();

        $this->assertDatabaseHas('platform_events', ['event' => 'admin.incident.updated']);

    }

    public function test_ip_blocks_expire_and_tokens_never_appear_in_sessions(): void
    {

        $this->operator();
        $user = User::factory()->create();
        $secret = $user->createToken('private-session')->plainTextToken;

        $this->getJson('/api/platform/sessions')->assertOk()->assertDontSee('private-session')->assertDontSee(explode('|', $secret)[1]);

        $hash = hash_hmac('sha256', '127.0.0.1', (string) config('app.key'));
        PlatformControl::create(['kind' => 'ip', 'key' => $hash, 'value' => ['status' => 'blocked'], 'expires_at' => now()->addMinute()]);

        $this->getJson('/api/platform/overview')->assertForbidden();
        $this->travel(2)->minutes();
        $this->getJson('/api/platform/overview')->assertOk();

    }
}
