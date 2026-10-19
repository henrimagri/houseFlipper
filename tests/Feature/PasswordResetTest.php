<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PasswordChangedEmail;
use App\Notifications\ResetPasswordEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $overrides = []): User
    {
        $tenant = Tenant::create(['nome' => 'Reset QA', 'slug' => 'reset-qa-'.uniqid()]);

        return User::factory()->create(array_replace([
            'tenant_id' => $tenant->id,
            'email' => 'reset@example.test',
            'password' => 'CurrentPassword123!',
        ], $overrides));
    }

    private function resetPayload(User $user, string $token, string $password = 'NewPassword123!'): array
    {
        return [
            'email' => $user->email,
            'token' => $token,
            'password' => $password,
            'password_confirmation' => $password,
        ];
    }

    public function test_forgot_password_is_anti_enumeration_and_sends_fragment_link_only_to_eligible_account(): void
    {
        Notification::fake();
        $user = $this->user();

        $existing = $this->postJson('/api/auth/password/forgot', ['email' => '  RESET@EXAMPLE.TEST '])
            ->assertAccepted()->json();
        $missing = $this->postJson('/api/auth/password/forgot', ['email' => 'missing@example.test'])
            ->assertAccepted()->json();
        $this->assertSame($existing, $missing);

        Notification::assertSentTo($user, ResetPasswordEmail::class, function (ResetPasswordEmail $notification) use ($user): bool {
            $url = $notification->toMail($user)->actionUrl;

            return str_contains($url, '/redefinir-senha#token=')
                && str_contains($url, '&email=reset%40example.test')
                && ! str_contains((string) parse_url($url, PHP_URL_QUERY), 'token');
        });

        $inactive = $this->user(['email' => 'inactive@example.test', 'ativo_flg' => false]);
        $this->postJson('/api/auth/password/forgot', ['email' => $inactive->email])->assertAccepted()->assertExactJson($existing);
        Notification::assertNotSentTo($inactive, ResetPasswordEmail::class);
    }

    public function test_valid_token_resets_password_once_and_revokes_every_api_token(): void
    {
        Notification::fake();
        $user = $this->user();
        $firstToken = $user->createToken('first')->plainTextToken;
        $user->createToken('second');
        $resetToken = Password::broker()->createToken($user);
        $storedToken = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');
        $this->assertNotSame($resetToken, $storedToken);
        $this->assertTrue(Hash::check($resetToken, $storedToken));

        $this->postJson('/api/auth/password/reset', $this->resetPayload($user, $resetToken))
            ->assertOk()->assertJsonPath('message', 'Senha redefinida. Entre novamente em todos os dispositivos.');

        $this->assertTrue(Hash::check('NewPassword123!', $user->refresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
        Notification::assertSentTo($user, PasswordChangedEmail::class);
        $this->withToken($firstToken)->getJson('/api/auth/me')->assertUnauthorized();
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'CurrentPassword123!'])->assertUnauthorized();
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'NewPassword123!'])->assertOk();
        $this->postJson('/api/auth/password/reset', $this->resetPayload($user, $resetToken))->assertUnprocessable();
    }

    public function test_expired_invalid_or_ineligible_reset_cannot_change_password(): void
    {
        $user = $this->user();
        $expired = Password::broker()->createToken($user);
        $this->travel(61)->minutes();
        $this->postJson('/api/auth/password/reset', $this->resetPayload($user, $expired))->assertUnprocessable();
        $this->travelBack();

        $this->postJson('/api/auth/password/reset', $this->resetPayload($user, 'invalid-token'))->assertUnprocessable();
        $this->postJson('/api/auth/password/reset', array_replace($this->resetPayload($user, Password::broker()->createToken($user)), ['email' => 'wrong@example.test']))->assertUnprocessable();

        $disabledToken = Password::broker()->createToken($user);
        $user->tenant->update(['ativo_flg' => false]);
        $this->postJson('/api/auth/password/reset', $this->resetPayload($user, $disabledToken))->assertUnprocessable();
        $this->assertTrue(Hash::check('CurrentPassword123!', $user->refresh()->password));
    }

    public function test_password_contract_and_request_rate_limit_are_enforced(): void
    {
        Notification::fake();
        $user = $this->user();
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/auth/password/reset', $this->resetPayload($user, $token, 'short'))
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $unicode = str_repeat('é', 40);
        $this->postJson('/api/auth/password/reset', $this->resetPayload($user, $token, $unicode))
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/api/auth/password/reset', array_replace($this->resetPayload($user, $token), ['password_confirmation' => 'DifferentPassword123!']))
            ->assertUnprocessable()->assertJsonValidationErrors('password');

        foreach (range(1, 3) as $attempt) {
            $this->postJson('/api/auth/password/forgot', ['email' => $user->email])->assertAccepted();
        }
        $this->postJson('/api/auth/password/forgot', ['email' => $user->email])->assertTooManyRequests();
    }

    public function test_delivery_failure_keeps_the_public_response_generic(): void
    {
        $user = $this->user();
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('PRIVATE_RESET_DELIVERY_MARKER'));

        $response = $this->postJson('/api/auth/password/forgot', ['email' => $user->email])
            ->assertAccepted()->assertJsonMissing(['exception' => 'PRIVATE_RESET_DELIVERY_MARKER']);

        $this->assertStringNotContainsString('PRIVATE_RESET_DELIVERY_MARKER', $response->getContent());
    }
}
