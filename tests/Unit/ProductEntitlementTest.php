<?php

namespace Tests\Unit;

use App\Enums\SubscriptionStatus;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\User;
use App\Services\Ai\ProductEntitlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductEntitlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_remain_independent(): void
    {
        $tenant = Tenant::query()->create(['nome' => 'Conta', 'slug' => 'entitlement-test']);
        $user = User::query()->create(['tenant_id' => $tenant->id, 'name' => 'User', 'email' => 'products@example.test', 'password' => 'password']);
        TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_slug' => 'corretor',
            'active' => true,
            'status' => SubscriptionStatus::Active,
        ]);

        $service = app(ProductEntitlement::class);
        $this->assertTrue($service->canAccessProduct($user, 'corretor'));
        $this->assertFalse($service->canAccessProduct($user, 'imobiliaria'));
        $this->assertFalse($service->canAccessProduct($user, 'house_flipper'));
        $this->assertSame([], $service->navigationFor($user, 'imobiliaria'));
    }
}
