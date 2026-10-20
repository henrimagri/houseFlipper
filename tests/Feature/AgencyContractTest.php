<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencyContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_agency_contract_crud_is_tenant_scoped(): void
    {
        [$user, $token] = $this->agency('one');
        [, $otherToken] = $this->agency('two');
        $payload = ['title' => 'Venda unidade 42', 'party_name' => 'Cliente Exemplo', 'type' => 'sale', 'status' => 'active', 'amount_cents' => 45000000, 'starts_at' => '2026-10-01', 'ends_at' => '2027-10-01'];

        $id = $this->withToken($token)->postJson('/api/imobiliaria/contracts', $payload)->assertCreated()->json('data.id');
        $this->withToken($token)->getJson('/api/imobiliaria/contracts')->assertOk()->assertJsonPath('meta.total', 1);
        $this->withToken($token)->patchJson('/api/imobiliaria/contracts/'.$id, [...$payload, 'status' => 'completed'])->assertOk()->assertJsonPath('data.status', 'completed');
        app('auth')->forgetGuards();
        $this->withToken($otherToken)->patchJson('/api/imobiliaria/contracts/'.$id, [...$payload, 'status' => 'cancelled'])->assertNotFound();
        app('auth')->forgetGuards();
        $this->withToken($token)->deleteJson('/api/imobiliaria/contracts/'.$id)->assertOk();
        $this->assertSoftDeleted('agency_contracts', ['id' => $id, 'tenant_id' => $user->tenant_id]);
    }

    private function agency(string $slug): array
    {
        $tenant = Tenant::query()->create(['nome' => $slug, 'slug' => 'contract-'.$slug]);
        $user = User::query()->create(['tenant_id' => $tenant->id, 'name' => $slug, 'email' => $slug.'@contracts.test', 'email_verified_at' => now(), 'password' => 'password-123']);
        TenantProductSubscription::query()->create(['tenant_id' => $tenant->id, 'product_slug' => 'imobiliaria', 'active' => true, 'status' => SubscriptionStatus::Active]);

        return [$user, $user->createToken('test')->plainTextToken];
    }
}
