<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_revoke_other_sessions_without_ending_current_session(): void
    {
        $tenant = Tenant::query()->create(['nome' => 'Conta', 'slug' => 'session-test']);
        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Pessoa',
            'email' => 'session@example.test',
            'password' => 'password',
        ]);
        $current = $user->createToken('Atual')->plainTextToken;
        $user->createToken('Outro');

        $this->withToken($current)->postJson('/api/auth/logout-others')
            ->assertOk()
            ->assertJsonPath('revoked', 1);

        $this->assertCount(1, $user->fresh()->tokens);
        $this->withToken($current)->getJson('/api/auth/sessions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.current', true);
    }

    public function test_user_cannot_revoke_another_users_session(): void
    {
        $tenant = Tenant::query()->create(['nome' => 'Conta', 'slug' => 'session-owner']);
        $owner = User::query()->create(['tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'password']);
        $attacker = User::query()->create(['tenant_id' => $tenant->id, 'name' => 'Other', 'email' => 'other@example.test', 'password' => 'password']);
        $foreign = $owner->createToken('Owner device')->accessToken;
        $attackerToken = $attacker->createToken('Other device')->plainTextToken;

        $this->withToken($attackerToken)->deleteJson('/api/auth/sessions/'.$foreign->id)->assertNotFound();
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $foreign->id]);
    }

    public function test_unverified_account_can_only_read_session_before_confirming_email(): void
    {
        $tenant = Tenant::query()->create(['nome' => 'Pendente', 'slug' => 'pending-verification']);
        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Pendente',
            'email' => 'pending-verification@example.test',
            'password' => 'password',
        ]);
        $user->forceFill(['requires_email_verification' => true, 'email_verified_at' => null])->save();
        $token = $user->createToken('Browser')->plainTextToken;

        $this->withToken($token)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('requires_email_verification', true);
        $this->withToken($token)->getJson('/api/products')
            ->assertForbidden()
            ->assertJsonPath('code', 'email_verification_required');
        $this->withToken($token)->postJson('/api/billing/trial', ['product' => 'corretor'])
            ->assertForbidden()
            ->assertJsonPath('code', 'email_verification_required');
    }
}
