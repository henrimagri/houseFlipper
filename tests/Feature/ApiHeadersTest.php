<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\CorsOriginPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_cors_allowlist_uses_exact_configured_origins_and_rejects_unsafe_values(): void
    {
        $this->assertContains(config('app.frontend_url'), config('cors.allowed_origins'));
        $this->assertNotContains('*', config('cors.allowed_origins'));
        $this->assertSame([
            'https://app.example.test',
            'http://localhost:5173',
        ], CorsOriginPolicy::normalize([
            ' https://APP.example.test/ ',
            'http://localhost:5173',
            'https://app.example.test',
            '*',
            'javascript:alert(1)',
            'https://user:password@app.example.test',
            'https://app.example.test/private',
            'https://app.example.test?token=secret',
        ]));
    }

    public function test_cors_preflight_allows_configured_frontend_and_rejects_another_origin(): void
    {
        $frontend = config('app.frontend_url');
        $headers = [
            'Origin' => $frontend,
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'authorization,content-type',
        ];

        $this->withHeaders($headers)->options('/api/auth/me')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', $frontend);

        $this->withHeaders(array_replace($headers, ['Origin' => 'https://evil.example.test']))
            ->options('/api/auth/me')
            ->assertNoContent()
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_authentication_and_validation_errors_also_disable_caching(): void
    {
        $unauthorized = $this->getJson('/api/auth/me')->assertUnauthorized();
        $invalid = $this->postJson('/api/auth/login', [])->assertUnprocessable();
        foreach ([$unauthorized, $invalid] as $response) {
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $response->assertHeader('X-Content-Type-Options', 'nosniff');
        }
    }

    public function test_token_and_financial_responses_cannot_be_cached(): void
    {
        $user = User::factory()->create();
        $login = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        $login->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString('no-store', $login->headers->get('Cache-Control'));
        $response = $this->actingAs($user, 'sanctum')->getJson('/api/opportunities')->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_api_errors_are_json_and_hardened_without_accept_header(): void
    {
        $validation = $this->post('/api/auth/login', []);
        $missing = $this->get('/api/does-not-exist');

        $validation->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
        $missing->assertNotFound()->assertJsonStructure(['message']);
        $missing->assertJsonMissingPath('exception')->assertJsonMissingPath('trace')->assertJsonMissingPath('file');

        foreach ([$validation, $missing] as $response) {
            $response->assertHeader('Content-Type', 'application/json');
            $response->assertHeader('X-Content-Type-Options', 'nosniff');
            $response->assertHeader('Referrer-Policy', 'no-referrer');
            $response->assertHeader('X-Frame-Options', 'DENY');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
    }

    public function test_api_server_errors_remain_generic_even_when_debug_is_enabled(): void
    {
        config(['app.debug' => true]);
        Route::get('/api/audit-server-error', fn () => throw new \RuntimeException('PRIVATE_EXCEPTION_MARKER'));

        $response = $this->get('/api/audit-server-error');

        $response->assertInternalServerError()->assertExactJson(['message' => 'Server Error']);
        $this->assertStringNotContainsString('PRIVATE_EXCEPTION_MARKER', $response->getContent());
        $this->assertStringNotContainsString(base_path(), $response->getContent());
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }
}
