<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class RegistrationRequirementsTest extends TestCase
{
    use RefreshDatabase;

    private function payload(string $product = 'corretor'): array
    {
        return ['name' => 'Pessoa de teste', 'email' => $product.'@example.test', 'tenant_nome' => 'Operação de teste', 'phone' => '11987654321', 'password' => 'test-password-123', 'password_confirmation' => 'test-password-123', 'intended_product' => $product, 'professional_name' => 'Pessoa profissional', 'city' => 'São Paulo', 'company_name' => 'Agência de teste', 'region_preference' => 'São Paulo', 'usage_goal' => 'Planejar reformas', 'consent' => true, 'address' => ['postal_code' => '01310100', 'street' => 'Avenida Paulista', 'number' => '100', 'complement' => 'Sem complemento', 'neighborhood' => 'Bela Vista', 'city' => 'São Paulo', 'state' => 'SP']];
    }

    public function test_registration_rejects_missing_required_fields_without_creating_accounts(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        foreach (['tenant_nome', 'phone', 'intended_product', 'professional_name', 'city', 'address', 'consent'] as $field) {
            $data = $this->payload();
            unset($data[$field]);
            $this->postJson('/api/auth/register', $data)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        foreach (['postal_code', 'street', 'number', 'complement', 'neighborhood', 'city', 'state'] as $field) {
            $data = $this->payload();
            unset($data['address'][$field]);
            $this->postJson('/api/auth/register', $data)->assertUnprocessable()->assertJsonValidationErrors('address.'.$field);
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('tenants', 0);
    }

    public function test_product_specific_details_are_required_but_creci_is_optional(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        foreach (['imobiliaria' => ['company_name', 'city'], 'house_flipper' => ['region_preference', 'usage_goal']] as $product => $fields) {
            foreach ($fields as $field) {
                $data = $this->payload($product);
                unset($data[$field]);
                $this->postJson('/api/auth/register', $data)->assertUnprocessable()->assertJsonValidationErrors($field);
            }
        }
        foreach (['corretor', 'imobiliaria', 'house_flipper'] as $product) {
            $this->postJson('/api/auth/register', $this->payload($product))->assertCreated()->assertJsonPath('requires_email_verification', true);
        }
    }

    public function test_location_updates_only_authenticated_user_and_preserves_profile_address(): void
    {
        $tenant = Tenant::create(['nome' => 'QA', 'slug' => 'location-test']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $other = User::factory()->create();
        $user->forceFill(['registration_profile' => ['phone' => '11987654321', 'address' => ['city' => 'São Paulo', 'state' => 'SP'], 'preferences' => ['email_notifications' => false]]])->save();
        $otherBefore = $other->registration_profile;
        $this->putJson('/api/auth/location', ['city' => 'Curitiba', 'state' => 'PR'])->assertUnauthorized();
        $this->actingAs($user, 'sanctum')->putJson('/api/auth/location', ['city' => 'Curitiba', 'state' => 'XX'])->assertUnprocessable()->assertJsonValidationErrors('state');
        $this->putJson('/api/auth/location', ['city' => 'Curitiba', 'state' => 'PR', 'user_id' => $other->id, 'tenant_id' => $other->tenant_id])->assertOk()->assertJsonPath('location.city', 'Curitiba');
        $profile = $user->fresh()->registration_profile;
        $this->assertSame(['city' => 'Curitiba', 'state' => 'PR'], $profile['location']);
        $this->assertSame(['city' => 'São Paulo', 'state' => 'SP'], $profile['address']);
        $this->assertSame('11987654321', $profile['phone']);
        $this->assertFalse($profile['preferences']['email_notifications']);
        $this->assertSame($otherBefore, $other->fresh()->registration_profile);
    }
}
