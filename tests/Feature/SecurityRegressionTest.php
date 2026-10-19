<?php

namespace Tests\Feature;

use App\Models\Propriedade;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MarketData\PrefeituraTransactionProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenantless_user_cannot_list_another_tenants_properties(): void
    {
        $tenant = Tenant::create(['nome' => 'Audit', 'slug' => 'audit']);
        Propriedade::create(['tenant_id' => $tenant->id, 'apelido' => 'Private', 'tipo' => 'casa']);
        $user = User::factory()->create(['tenant_id' => null]);
        $this->actingAs($user, 'sanctum')->getJson('/api/propriedades')->assertForbidden();
    }

    public function test_disabled_account_cannot_login(): void
    {
        $user = User::factory()->create(['ativo_flg' => false]);
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])->assertUnauthorized();
    }

    public function test_disabled_account_cannot_use_existing_token(): void
    {
        $tenant = Tenant::create(['nome' => 'Audit', 'slug' => 'audit']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'ativo_flg' => false]);
        $token = $user->createToken('audit')->plainTextToken;
        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_every_protected_route_blocks_an_inactive_account_before_binding_or_controller_execution(): void
    {
        $tenant = Tenant::create(['nome' => 'Inactive route audit', 'slug' => 'inactive-route-audit']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'ativo_flg' => false]);
        $this->actingAs($user, 'sanctum');

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/') || ! in_array('active-account', $route->gatherMiddleware(), true)) {
                continue;
            }

            $uri = '/'.preg_replace('/\{[^}]+\}/', '1', $route->uri());
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $response = $this->json($method, $uri);
                $this->assertSame(401, $response->status(), "$method $uri must be blocked before binding or controller work.");
                $response->assertJsonPath('message', 'Conta indisponível.');
            }
        }
    }

    public function test_login_attempts_are_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'nobody@example.test', 'password' => 'wrong']);
        }
        $this->postJson('/api/auth/login', ['email' => 'nobody@example.test', 'password' => 'wrong'])->assertTooManyRequests();
    }

    public function test_distributed_login_attempts_share_an_account_wide_budget(): void
    {
        $payload = ['email' => 'distributed-target@example.test', 'password' => 'wrong'];
        foreach (range(1, 30) as $attempt) {
            $this->withServerVariables(['REMOTE_ADDR' => "198.51.100.$attempt"])
                ->postJson('/api/auth/login', $payload)
                ->assertUnauthorized();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.250'])
            ->postJson('/api/auth/login', $payload)
            ->assertTooManyRequests();
    }

    public function test_invalid_pagination_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')->getJson('/api/opportunities?per_page=-1')->assertUnprocessable();
        $this->getJson('/api/opportunities?per_page[]=10')->assertUnprocessable();
    }

    public function test_issued_tokens_expire(): void
    {
        $user = User::factory()->create();
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        $this->assertNotNull($user->tokens()->firstOrFail()->expires_at);
    }

    public function test_regular_user_cannot_write_shared_itbi_dataset(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/itbi-imports/upload')->assertForbidden();
    }

    public function test_public_routes_do_not_require_authentication_but_resource_routes_do(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->get('/api/auth/me')->assertUnauthorized();
        $this->postJson('/api/auth/login', [])->assertUnprocessable();
        $this->postJson('/api/auth/register', [])->assertUnprocessable();
    }

    public function test_private_local_disk_does_not_register_file_serving_routes(): void
    {
        $this->assertFalse(Route::has('storage.local'));
        $this->assertFalse(Route::has('storage.local.upload'));

        $storageRoutes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_starts_with($route->uri(), 'storage/'));

        $this->assertCount(0, $storageRoutes);
    }

    public function test_inactive_tenant_cannot_use_existing_token(): void
    {
        $tenant = Tenant::create(['nome' => 'Suspended', 'slug' => 'suspended', 'ativo_flg' => false]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->withToken($user->createToken('audit')->plainTextToken)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_nested_address_cannot_override_tenant(): void
    {
        $own = Tenant::create(['nome' => 'Own', 'slug' => 'own']);
        $other = Tenant::create(['nome' => 'Other', 'slug' => 'other']);
        $user = User::factory()->create(['tenant_id' => $own->id]);
        $this->actingAs($user, 'sanctum')->postJson('/api/propriedades', [
            'apelido' => 'Audit', 'endereco' => ['cidade' => 'Teste', 'tenant_id' => $other->id],
        ])->assertUnprocessable();
        $this->assertDatabaseCount('propriedades_enderecos', 0);
    }

    public function test_every_protected_api_method_rejects_anonymous_requests(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (! in_array('auth:sanctum', $route->gatherMiddleware(), true)) {
                continue;
            }
            $uri = '/'.preg_replace('/\{[^}]+\}/', '1', $route->uri());
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $this->json($method, $uri)->assertUnauthorized();
            }
        }
    }

    public function test_api_route_inventory_has_an_explicit_authentication_boundary(): void
    {
        $public = [
            'api/auth/register',
            'api/auth/login',
            'api/auth/password/forgot',
            'api/auth/password/reset',
            'api/auth/postal-code/{postalCode}',
            'api/auth/email/verify/{id}/{hash}',
        ];
        $allowedBeforeVerification = [
            'api/auth/logout',
            'api/auth/logout-all',
            'api/auth/me',
            'api/auth/sessions',
            'api/auth/sessions/{tokenId}',
            'api/auth/email/resend',
        ];
        $seenPublic = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/')) {
                continue;
            }

            $middleware = $route->gatherMiddleware();
            if (in_array($uri, $public, true)) {
                $this->assertNotContains('auth:sanctum', $middleware, $uri);
                $seenPublic[] = $uri;

                continue;
            }

            $this->assertContains('auth:sanctum', $middleware, $uri);
            $this->assertContains('active-account', $middleware, $uri);

            if (! in_array($uri, $allowedBeforeVerification, true)) {
                $this->assertContains('verified-account', $middleware, $uri);
            }
        }

        sort($public);
        $seenPublic = array_values(array_unique($seenPublic));
        sort($seenPublic);
        $this->assertSame($public, $seenPublic);
    }

    public function test_sensitive_routes_keep_their_specific_permissions_and_rate_limits(): void
    {
        $routes = collect(Route::getRoutes())->keyBy(fn ($route): string => implode('|', array_diff($route->methods(), ['HEAD'])).' '.$route->uri());

        $this->assertContains('can:import-itbi', $routes->get('POST api/itbi-imports/upload')->gatherMiddleware());
        $this->assertContains('throttle:itbi-upload', $routes->get('POST api/itbi-imports/upload')->gatherMiddleware());
        $this->assertContains('throttle:document-upload', $routes->get('POST api/documentos')->gatherMiddleware());

        foreach (['GET api/ai-property-search', 'POST api/property-market-analysis', 'POST api/neighborhood-context'] as $key) {
            $this->assertContains('throttle:market-data', $routes->get($key)->gatherMiddleware(), $key);
        }
    }

    public function test_logout_deletes_only_current_bearer_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('audit')->plainTextToken;
        $other = $user->createToken('other')->accessToken;
        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $other->id]);
        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_logout_handles_authenticated_request_without_personal_access_token(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/auth/logout')->assertOk();
    }

    public function test_logout_invalidates_cookie_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')->postJson('/api/auth/logout')->assertOk();
        $this->assertGuest('web');
        Auth::forgetGuards();
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_user_can_list_only_their_sessions_and_identify_the_current_one(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('Chrome Windows', ['*'], now()->addHour());
        $other = $user->createToken('Celular', ['*'], now()->addHour())->accessToken;
        User::factory()->create()->createToken('Sessão alheia', ['*'], now()->addHour());

        $this->withToken($current->plainTextToken)->getJson('/api/auth/sessions')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $current->accessToken->id, 'name' => 'Chrome Windows', 'current' => true])
            ->assertJsonFragment(['id' => $other->id, 'name' => 'Celular', 'current' => false])
            ->assertJsonMissing(['name' => 'Sessão alheia']);
    }

    public function test_user_can_revoke_another_session_but_not_another_users_token(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('Atual')->plainTextToken;
        $other = $user->createToken('Outro')->accessToken;
        $foreign = User::factory()->create()->createToken('Alheio')->accessToken;

        $this->withToken($current)->deleteJson("/api/auth/sessions/{$foreign->id}")->assertNotFound();
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $foreign->id]);
        $this->deleteJson("/api/auth/sessions/{$other->id}")->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $other->id]);
        $this->getJson('/api/auth/me')->assertOk();
    }

    public function test_logout_all_revokes_every_bearer_token(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('Atual')->plainTextToken;
        $user->createToken('Outro');

        $this->withToken($current)->postJson('/api/auth/logout-all')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        Auth::forgetGuards();
        $this->withToken($current)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_registration_cannot_assign_privileges_or_another_tenant(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Audit', 'email' => 'audit@example.test', 'tenant_nome' => 'Audit', 'tenant_slug' => 'audit',
            'password' => 'Fictitious1234!', 'password_confirmation' => 'Fictitious1234!',
            'device_name' => 'Firefox · Linux',
            'tenant_id' => 999, 'ativo_flg' => false, 'permissions' => ['itbi.import'],
        ])->assertCreated();
        $user = User::where('email', 'audit@example.test')->firstOrFail();
        $this->assertNotSame(999, $user->tenant_id);
        $this->assertTrue($user->ativo_flg);
        $this->assertFalse($user->can('import-itbi'));
        $this->assertNotNull($user->tokens()->firstOrFail()->expires_at);
        $this->assertSame('Firefox · Linux', $user->tokens()->firstOrFail()->name);
    }

    public function test_login_rejects_wrong_payload_types(): void
    {
        $this->postJson('/api/auth/login', ['email' => ['invalid'], 'password' => ['invalid']])->assertUnprocessable();
    }

    public function test_provider_exceptions_do_not_disclose_credentials(): void
    {
        config()->set('services.prefeituras.endpoint', 'https://example.test/api');
        Http::fake(fn () => throw new \RuntimeException('AUDIT_PRIVATE_SENTINEL'));
        $response = app(PrefeituraTransactionProvider::class)->search([]);
        $this->assertStringNotContainsString('AUDIT_PRIVATE_SENTINEL', json_encode($response));
    }

    public function test_expired_token_is_rejected_with_clock_advanced(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('audit', ['*'], now()->addMinutes(720))->plainTextToken;
        $this->withToken($token)->getJson('/api/opportunities')->assertOk();
        $this->travel(721)->minutes();
        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/opportunities')->assertUnauthorized();
        $this->travelBack();
    }

    public function test_legacy_token_without_explicit_expiry_is_rejected_after_maximum_age(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('legacy')->plainTextToken;
        $this->travel(721)->minutes();
        $this->withToken($token)->getJson('/api/opportunities')->assertUnauthorized();
        $this->travelBack();
    }

    public function test_tenantless_profile_has_predictable_response_and_cannot_create_tenant_resource(): void
    {
        $this->actingAs(User::factory()->create(['tenant_id' => null]), 'sanctum');
        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('tenant', null);
        $this->postJson('/api/propriedades', ['apelido' => 'Audit'])->assertForbidden();
        $this->assertDatabaseCount('propriedades', 0);
    }

    public function test_expensive_routes_have_separate_budget_without_contacting_providers(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/property-market-analysis', [])->assertUnprocessable();
        }
        $this->postJson('/api/property-market-analysis', [])->assertTooManyRequests();
        $this->getJson('/api/opportunities')->assertOk();
    }

    public function test_email_case_does_not_create_duplicate_accounts_or_block_login(): void
    {
        User::factory()->create(['email' => 'Audit.Case@Example.test']);
        $this->postJson('/api/auth/login', ['email' => 'audit.case@example.test', 'password' => 'password'])->assertOk();
        $this->postJson('/api/auth/register', [
            'name' => 'Audit', 'email' => 'AUDIT.CASE@example.test', 'tenant_nome' => 'Audit', 'tenant_slug' => 'audit-case',
            'password' => 'Fictitious1234!', 'password_confirmation' => 'Fictitious1234!',
        ])->assertUnprocessable();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_ambiguous_legacy_email_cannot_select_an_arbitrary_account(): void
    {
        User::factory()->create(['email' => 'Audit@example.test']);
        User::factory()->create(['email' => 'audit@example.test']);
        $this->postJson('/api/auth/login', ['email' => 'AUDIT@example.test', 'password' => 'password'])->assertUnauthorized();
    }
}
