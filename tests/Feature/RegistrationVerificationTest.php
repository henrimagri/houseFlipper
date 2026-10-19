<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyAccountEmail;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class RegistrationVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return ['name' => 'Cadastro Fictício', 'email' => 'registration@example.test', 'tenant_nome' => 'Operação Fictícia', 'password' => 'Fictitious1234!', 'password_confirmation' => 'Fictitious1234!', 'phone' => '(11) 99999-1234',
            'address' => ['postal_code' => '01001000', 'street' => 'Rua Fictícia', 'number' => '10', 'complement' => '', 'neighborhood' => 'Centro', 'city' => 'São Paulo', 'state' => 'SP']];
    }

    private function link(User $user): string
    {
        $url = (new VerifyAccountEmail)->toMail($user)->actionUrl;
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        return $query['verification'];
    }

    public function test_every_business_route_blocks_an_unverified_account_before_controller_execution(): void
    {
        $user = User::factory()->create([
            'requires_email_verification' => true,
            'email_verified_at' => null,
        ]);
        $this->actingAs($user, 'sanctum');
        $allowed = [
            'api/auth/logout',
            'api/auth/logout-all',
            'api/auth/me',
            'api/auth/sessions',
            'api/auth/sessions/{tokenId}',
            'api/auth/email/resend',
        ];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/')
                || ! in_array('verified-account', $route->gatherMiddleware(), true)
                || in_array($route->uri(), $allowed, true)) {
                continue;
            }

            $uri = '/'.preg_replace('/\{[^}]+\}/', '1', $route->uri());
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $response = $this->json($method, $uri);
                $this->assertSame(403, $response->status(), "$method $uri must be blocked before routing or controller work.");
                $this->assertSame('email_verification_required', $response->json('code'), "$method $uri must use the verification boundary.");
            }
        }
    }

    public function test_registration_confirms_email_before_accessing_business_routes(): void
    {
        Notification::fake();
        $payload = $this->payload() + ['requires_email_verification' => false, 'email_verified_at' => now()->toIso8601String()];
        $response = $this->postJson('/api/auth/register', $payload)->assertCreated()->assertJsonPath('requires_email_verification', true);
        $user = User::where('email', $payload['email'])->firstOrFail();
        $this->assertTrue($user->requires_email_verification);
        $this->assertNull($user->email_verified_at);
        $this->assertSame('11999991234', $user->registration_profile['phone']);
        $this->assertSame('01001000', $user->registration_profile['address']['postal_code']);
        Notification::assertSentTo($user, VerifyAccountEmail::class);
        $token = $response->json('token');
        $this->withToken($token)->getJson('/api/dashboard')->assertForbidden()->assertJsonPath('code', 'email_verification_required');
        $this->getJson('/api/auth/me')->assertOk();
        $path = $this->link($user);
        $this->getJson($path)->assertOk();
        $this->assertNotNull($user->refresh()->email_verified_at);
        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/dashboard')->assertOk();
        $this->getJson($path)->assertOk();
    }

    public function test_modified_and_expired_verification_links_cannot_confirm_account(): void
    {
        $user = User::factory()->unverified()->create(['requires_email_verification' => true]);
        $path = $this->link($user);
        $this->getJson($path.'&tampered=1')->assertForbidden();
        $wrongHash = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => str_repeat('a', 40)], absolute: false);
        $this->getJson($wrongHash)->assertForbidden();
        $this->travel(61)->minutes();
        $this->getJson($path)->assertForbidden();
        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_confirmation_cannot_reenable_inactive_account(): void
    {
        $user = User::factory()->unverified()->create(['requires_email_verification' => true, 'ativo_flg' => false]);
        $this->getJson($this->link($user))->assertForbidden();
        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_resend_requires_authentication_and_has_per_user_limit(): void
    {
        Notification::fake();
        $this->postJson('/api/auth/email/resend')->assertUnauthorized();
        $user = User::factory()->unverified()->create(['requires_email_verification' => true]);
        $this->actingAs($user, 'sanctum')->postJson('/api/auth/email/resend')->assertStatus(202)->assertJsonPath('delivery', 'local');
        $this->postJson('/api/auth/email/resend')->assertTooManyRequests();
        Notification::assertSentToTimes($user, VerifyAccountEmail::class, 1);
    }

    public function test_notification_failure_preserves_account_and_reports_delivery_failure(): void
    {
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('PRIVATE_DELIVERY_MARKER'));
        $this->postJson('/api/auth/register', $this->payload())->assertCreated()->assertJsonPath('verification_delivery', 'failed');
        $this->assertDatabaseCount('users', 1);
        $this->assertTrue(User::firstOrFail()->requires_email_verification);
    }

    public function test_address_fields_and_password_byte_limit_are_validated(): void
    {
        Notification::fake();
        $data = $this->payload();
        $data['address']['tenant_id'] = 999;
        $this->postJson('/api/auth/register', $data)->assertUnprocessable()->assertJsonValidationErrors('address');
        $data = $this->payload();
        $data['password'] = $data['password_confirmation'] = str_repeat('é', 40);
        $this->postJson('/api/auth/register', $data)->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertDatabaseCount('users', 0);
        Notification::assertNothingSent();
    }

    public function test_postal_code_lookup_handles_valid_missing_and_malformed_provider_data(): void
    {
        Http::preventStrayRequests();
        Http::fake(['viacep.com.br/*' => Http::sequence()
            ->push(['logradouro' => 'Rua Fictícia', 'bairro' => 'Centro', 'localidade' => 'São Paulo', 'uf' => 'SP'])
            ->push(['erro' => true])->push(['logradouro' => []])]);
        $this->getJson('/api/auth/postal-code/01001000')->assertOk()->assertJsonPath('city', 'São Paulo');
        $this->getJson('/api/auth/postal-code/99999999')->assertNotFound();
        $this->getJson('/api/auth/postal-code/01001000')->assertStatus(503);
        $this->getJson('/api/auth/postal-code/not-a-code')->assertNotFound();
        Http::assertSentCount(3);
    }

    public function test_postal_code_lookup_limits_transport_and_provider_fields(): void
    {
        Http::preventStrayRequests();
        $observed = [];
        $call = 0;
        Http::fake(function ($request, $options) use (&$observed, &$call) {
            $observed = [
                'encoding' => $request->header('Accept-Encoding')[0] ?? null,
                'redirects' => $options['allow_redirects'] ?? null,
                'decode_content' => $options['decode_content'] ?? null,
            ];
            $call++;

            return match ($call) {
                1 => Http::response(['logradouro' => 'Rua Fictícia', 'bairro' => 'Centro', 'localidade' => 'São Paulo', 'uf' => 'sp']),
                2 => Http::response(['logradouro' => str_repeat('x', 181), 'bairro' => 'Centro', 'localidade' => 'São Paulo', 'uf' => 'SP']),
                3 => Http::response(['logradouro' => 'Rua', 'bairro' => 'Centro', 'localidade' => 'São Paulo', 'uf' => 'S1']),
                default => Http::response(str_repeat('x', 65_537)),
            };
        });

        $this->getJson('/api/auth/postal-code/01001000')->assertOk()->assertJsonPath('state', 'SP');
        $this->assertSame('identity', $observed['encoding']);
        $this->assertFalse($observed['redirects']);
        $this->assertFalse($observed['decode_content']);

        $this->getJson('/api/auth/postal-code/01001000')->assertStatus(503);
        $this->getJson('/api/auth/postal-code/01001000')->assertStatus(503);
        $this->getJson('/api/auth/postal-code/01001000')->assertStatus(503);
    }

    public function test_postal_code_lookup_aborts_announced_and_progressive_oversize(): void
    {
        Http::preventStrayRequests();
        $call = 0;
        Http::fake(function ($request, $options) use (&$call) {
            $call++;
            if ($call === 1) {
                $options['on_headers'](new PsrResponse(200, ['Content-Length' => '65537']));
            } else {
                $options['progress'](0, 65_537, 0, 0);
            }

            return Http::response([]);
        });
        $this->getJson('/api/auth/postal-code/01001000')->assertStatus(503);
        $this->getJson('/api/auth/postal-code/01001000')->assertStatus(503);
    }
}
