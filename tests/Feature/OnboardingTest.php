<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\ProductOnboarding;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_dismiss_an_incomplete_tour(): void
    {
        [$user, $token] = $this->agency();

        $this->withToken($token)->putJson('/api/onboarding/imobiliaria', [
            'tour_version' => 2,
            'current_step' => 1,
            'completed_steps' => ['org'],
            'completed' => false,
            'dismissed' => true,
        ])->assertOk()
            ->assertJsonPath('data.dismissed', true)
            ->assertJsonPath('data.current_step', 1);

        $this->withToken($token)->getJson('/api/onboarding/imobiliaria')
            ->assertOk()
            ->assertJsonPath('data.dismissed', true)
            ->assertJsonPath('data.tour_version', 2);

        $this->assertDatabaseHas('product_onboardings', [
            'user_id' => $user->id,
            'product_slug' => 'imobiliaria',
            'dismissed' => true,
        ]);
    }

    public function test_stale_tour_version_is_reset_when_loaded(): void
    {
        [$user, $token] = $this->agency();
        ProductOnboarding::query()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'product_slug' => 'imobiliaria',
            'tour_version' => 1,
            'current_step' => 5,
            'completed_steps' => ['org'],
            'dismissed' => true,
            'completed_at' => now(),
        ]);

        $this->withToken($token)->getJson('/api/onboarding/imobiliaria')
            ->assertOk()
            ->assertJsonPath('data.tour_version', 2)
            ->assertJsonPath('data.current_step', 0)
            ->assertJsonPath('data.dismissed', false)
            ->assertJsonPath('data.completed_at', null);
    }

    private function agency(): array
    {
        $tenant = Tenant::query()->create(['nome' => 'Imobiliária QA', 'slug' => 'onboarding-qa']);
        $user = User::query()->create(['tenant_id' => $tenant->id, 'name' => 'Pessoa QA', 'email' => 'onboarding@example.test', 'email_verified_at' => now(), 'password' => 'password']);
        TenantProductSubscription::query()->create(['tenant_id' => $tenant->id, 'product_slug' => 'imobiliaria', 'active' => true, 'status' => SubscriptionStatus::Active]);

        return [$user, $user->createToken('test')->plainTextToken];
    }
}
