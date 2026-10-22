<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // Explicit fixture for pre-existing admin tests. No runtime MFA bypass.
    protected function platformSession(\App\Models\User $user): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($user);
        if (! $user->isPlatformAdmin()) return;
        \App\Models\PlatformMfaCredential::updateOrCreate(['user_id' => $user->id], ['secret' => app(\App\Services\Platform\Totp::class)->secret(), 'confirmed_at' => now()]);
        $token = $user->createToken('test-admin')->accessToken;
        $token->forceFill(['platform_mfa_at' => now(), 'platform_recent_auth_at' => now()])->save();
        $user->withAccessToken($token);
    }
}
