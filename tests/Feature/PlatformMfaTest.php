<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Models\PlatformEvent;
use App\Models\PlatformMfaCredential;
use App\Models\User;
use App\Services\Platform\EventRecorder;
use App\Services\Platform\PlatformMfa;
use App\Services\Platform\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformMfaTest extends TestCase
{
    use RefreshDatabase;

    private function login(bool $admin = true): User
    {
        $u = User::factory()->create(['password' => 'qa-password']);
        if ($admin) {
            PlatformAdmin::create(['user_id' => $u->id, 'email' => $u->email]);
        }
        Sanctum::actingAs($u);
        $u->withAccessToken($u->createToken('mfa-test')->accessToken);

        return $u;
    }

    private function enrol(): array
    {
        $secret = $this->postJson('/api/platform/mfa/enrol', ['password' => 'qa-password'])->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json('secret');
        $result = $this->postJson('/api/platform/mfa/confirm', ['code' => app(Totp::class)->code($secret, now()->timestamp)])->assertOk();

        return [$secret, $result->json('recovery_codes')];
    }

    public function test_rfc6238_sha1_vectors(): void
    {
        // Independent published RFC test vectors; eight digits only for this test.
        foreach ([59 => '94287082', 1111111109 => '07081804', 1111111111 => '14050471', 1234567890 => '89005924', 2000000000 => '69279037', 20000000000 => '65353130'] as $time => $expected) {
            $this->assertSame($expected, app(Totp::class)->code('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $time, 8));
        }
    }

    public function test_enrolment_blocks_data_until_confirmed_and_never_serializes_secrets(): void
    {
        $u = $this->login();
        $this->getJson('/api/platform/overview')->assertForbidden()->assertJsonPath('code', 'platform_mfa_required');
        $this->getJson('/api/platform/mfa/status')->assertOk()->assertJsonPath('enrolled', false);
        [$secret, $codes] = $this->enrol();
        $this->assertCount(10, $codes);
        $this->getJson('/api/platform/overview')->assertOk();
        $this->getJson('/api/platform/mfa/status')->assertJsonPath('enrolled', true)->assertJsonMissingPath('secret')->assertJsonMissingPath('recovery_codes');
        $this->assertStringNotContainsString($secret, \DB::table('platform_mfa_credentials')->where('user_id', $u->id)->value('secret'));
        $this->assertStringNotContainsString($codes[0], PlatformMfaCredential::first()->getRawOriginal('recovery_hashes'));
        $stored = PlatformEvent::all()->toJson();
        foreach ([$secret, $codes[0]] as $value) {
            $this->assertStringNotContainsString($value, $stored);
        }
    }

    public function test_invalid_expired_replayed_codes_and_one_use_recovery(): void
    {
        $u = $this->login();
        [$secret, $codes] = $this->enrol();
        $this->travel(2)->minutes();
        $this->postJson('/api/platform/mfa/challenge', ['code' => app(Totp::class)->code($secret, now()->subMinutes(2)->timestamp)])->assertUnprocessable();
        $code = app(Totp::class)->code($secret, now()->timestamp);
        $this->postJson('/api/platform/mfa/challenge', ['code' => $code])->assertOk();
        $this->postJson('/api/platform/mfa/challenge', ['code' => $code])->assertUnprocessable();
        // Fresh per-user minute for rate limiter, not to evade validation.
        $this->travel(1)->minutes();
        $this->postJson('/api/platform/mfa/challenge', ['code' => 'not-valid'])->assertUnprocessable();
        $this->postJson('/api/platform/mfa/challenge', ['code' => $codes[0]])->assertOk();
        $this->postJson('/api/platform/mfa/challenge', ['code' => $codes[0]])->assertUnprocessable();
        $this->assertCount(9, PlatformMfaCredential::first()->recovery_hashes);
        $this->assertDatabaseHas('platform_events', ['event' => 'admin.mfa.recovery_used', 'user_id' => $u->id]);
        $this->assertDatabaseHas('platform_events', ['event' => 'admin.mfa.failed']);
    }

    public function test_other_tokens_expiry_and_pending_enrolment_do_not_grant_access(): void
    {
        $u = $this->login();
        $secret = $this->postJson('/api/platform/mfa/enrol', ['password' => 'qa-password'])->json('secret');
        $this->travel(11)->minutes();
        $this->postJson('/api/platform/mfa/confirm', ['code' => app(Totp::class)->code($secret, now()->timestamp)])->assertUnprocessable();
        $this->enrol();
        $this->travel(9)->hours();
        $this->getJson('/api/platform/overview')->assertForbidden();
        $u->withAccessToken($u->createToken('another-login')->accessToken);
        $this->getJson('/api/platform/overview')->assertForbidden();
    }

    public function test_reauthentication_regeneration_and_disable_revoke_access(): void
    {
        $u = $this->login();
        [$secret, $codes] = $this->enrol();
        $this->travel(11)->minutes();
        $this->postJson('/api/platform/actions', ['action' => 'ip_block', 'target' => str_repeat('a', 64), 'password' => 'qa-password', 'confirmed' => true, 'reason' => 'qa_validation'])->assertForbidden();
        $code = app(Totp::class)->code($secret, now()->timestamp);
        $new = $this->postJson('/api/platform/mfa/recoveries', ['password' => 'qa-password', 'code' => $code])->assertOk()->json('recovery_codes');
        $this->postJson('/api/platform/mfa/challenge', ['code' => $codes[0]])->assertUnprocessable();
        $this->postJson('/api/platform/mfa/disable', ['password' => 'wrong', 'code' => $new[0]])->assertForbidden();
        $this->postJson('/api/platform/mfa/disable', ['password' => 'qa-password', 'code' => $new[0]])->assertOk();
        $this->getJson('/api/platform/overview')->assertForbidden();
        $this->assertDatabaseCount('platform_mfa_credentials', 0);
    }

    public function test_non_admin_and_mock_or_foreign_token_cannot_gain_mfa_access(): void
    {
        $u = $this->login(false);
        foreach (['status', 'enrol', 'confirm', 'challenge', 'recoveries', 'disable'] as $action) {
            $this->json($action === 'status' ? 'GET' : 'POST', '/api/platform/mfa/'.$action)->assertForbidden();
        }
        PlatformAdmin::create(['user_id' => $u->id, 'email' => $u->email]);
        $other = User::factory()->create();
        $u->withAccessToken($other->createToken('foreign')->accessToken);
        $this->getJson('/api/platform/mfa/status')->assertForbidden();
    }

    public function test_mfa_bruteforce_has_its_own_limit_and_invalid_input_is_not_recorded(): void
    {
        $this->login();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/platform/mfa/challenge', ['code' => 'SECRET_INVALID_CODE'])->assertUnprocessable();
        }
        $this->postJson('/api/platform/mfa/challenge', ['code' => 'SECRET_INVALID_CODE'])->assertStatus(429);
        $this->assertStringNotContainsString('SECRET_INVALID_CODE', PlatformEvent::all()->toJson());
    }

    public function test_failed_audit_rolls_back_enrolment_confirmation_and_token_grant(): void
    {
        $u = $this->login();
        $secret = $this->postJson('/api/platform/mfa/enrol', ['password' => 'qa-password'])->assertOk()->json('secret');
        $this->partialMock(EventRecorder::class, fn ($m) => $m->shouldReceive('audit')->andThrow(new \RuntimeException('audit unavailable')));
        $this->postJson('/api/platform/mfa/confirm', ['code' => app(Totp::class)->code($secret, now()->timestamp)])->assertStatus(500);
        $this->assertNull(PlatformMfaCredential::first()->confirmed_at);
        $this->assertNull($u->currentAccessToken()->fresh()->platform_mfa_at);
    }

    private function configureLocalQaException(User $user): void
    {
        $this->app->instance('env', 'local');
        config(['platform.local_qa_mfa' => ['user_id' => $user->id, 'email_sha256' => hash('sha256', $user->email), 'expires_at' => now()->addHour()->toIso8601String()]]);
    }

    public function test_local_qa_exception_allows_only_designated_operator_and_still_requires_password(): void
    {
        $u = $this->login();
        $this->configureLocalQaException($u);
        $this->getJson('/api/platform/mfa/status')->assertOk()->assertJsonPath('enrolled', false)->assertJsonPath('verified', true)->assertJsonPath('local_qa_exemption', true);
        $this->getJson('/api/platform/overview')->assertOk();
        $this->assertDatabaseCount('platform_mfa_credentials', 0);
        $this->postJson('/api/platform/actions', ['action' => 'ip_block', 'target' => str_repeat('a', 64), 'password' => 'wrong', 'confirmed' => true, 'reason' => 'qa_validation'])->assertForbidden();
        $this->login();
        $this->getJson('/api/platform/overview')->assertForbidden()->assertJsonPath('code', 'platform_mfa_required');
    }

    public function test_local_qa_exception_fails_closed_outside_local_sqlite_and_after_expiry(): void
    {
        $u = $this->login();
        $this->configureLocalQaException($u);
        $this->app->instance('env', 'production');
        $this->getJson('/api/platform/overview')->assertForbidden();
        $this->app->instance('env', 'local');
        // Inspect the configured driver without opening a MySQL connection.
        config(['database.connections.sqlite.driver' => 'mysql']);
        $this->assertFalse(app(PlatformMfa::class)->usesLocalQaExemption(request()->setUserResolver(fn () => $u)));
        config(['database.connections.sqlite.driver' => 'sqlite']);
        $this->travel(2)->hours();
        $this->getJson('/api/platform/overview')->assertForbidden();
    }

    public function test_local_qa_exception_requires_fingerprint_verified_email_role_and_own_token(): void
    {
        $u = $this->login();
        $this->configureLocalQaException($u);
        config(['platform.local_qa_mfa.email_sha256' => '']);
        $this->getJson('/api/platform/overview')->assertForbidden();
        $this->configureLocalQaException($u);
        $u->forceFill(['email_verified_at' => null])->save();
        $this->getJson('/api/platform/overview')->assertForbidden();
        $u->forceFill(['email_verified_at' => now()])->save();
        PlatformAdmin::where('user_id', $u->id)->delete();
        $this->getJson('/api/platform/overview')->assertForbidden();
        PlatformAdmin::create(['user_id' => $u->id, 'email' => $u->email]);
        $other = User::factory()->create();
        $u->withAccessToken($other->createToken('foreign')->accessToken);
        $this->getJson('/api/platform/mfa/status')->assertForbidden();
    }
}
