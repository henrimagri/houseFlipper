<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\CrmContact;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\User;
use App\Services\Workspace\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencyCrmSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_crm_rejects_references_from_another_tenant(): void
    {
        [$user, $token] = $this->agencyUser('agency-a');
        [$foreign] = $this->agencyUser('agency-b');
        $foreignContact = CrmContact::withoutGlobalScopes()->create([
            'tenant_id' => $foreign->tenant_id,
            'user_id' => $foreign->id,
            'name' => 'Contato externo',
        ]);

        $this->withToken($token)->postJson('/api/imobiliaria/deals', [
            'title' => 'Tentativa inválida',
            'assigned_user_id' => $foreign->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['assigned_user_id']);

        $this->withToken($token)->postJson('/api/imobiliaria/deals', [
            'title' => 'Contato inválido',
            'crm_contact_id' => $foreignContact->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['crm_contact_id']);
    }

    public function test_deal_can_be_edited_and_archived_with_history(): void
    {
        [$user, $token] = $this->agencyUser('agency-edit');
        $created = $this->withToken($token)->postJson('/api/imobiliaria/deals', ['title' => 'Original'])
            ->assertCreated()->json('data');

        $this->withToken($token)->patchJson('/api/imobiliaria/deals/'.$created['id'], [
            'title' => 'Atualizado',
            'priority' => 'alta',
        ])->assertOk()->assertJsonPath('data.title', 'Atualizado');
        $this->assertDatabaseHas('crm_deal_activities', ['crm_deal_id' => $created['id'], 'kind' => 'updated']);

        $this->withToken($token)->deleteJson('/api/imobiliaria/deals/'.$created['id'])->assertOk();
        $this->assertSoftDeleted('crm_deals', ['id' => $created['id']]);
        $this->assertDatabaseHas('crm_deal_activities', ['crm_deal_id' => $created['id'], 'kind' => 'archived']);
    }

    private function agencyUser(string $slug): array
    {
        $tenant = Tenant::query()->create(['nome' => $slug, 'slug' => $slug]);
        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $slug,
            'email' => $slug.'@example.test',
            'email_verified_at' => now(),
            'password' => 'password',
        ]);
        TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_slug' => 'imobiliaria',
            'active' => true,
            'status' => SubscriptionStatus::Active,
        ]);
        app(OrganizationService::class)->ensureForTenant($user);

        return [$user, $user->createToken('test')->plainTextToken];
    }
}
