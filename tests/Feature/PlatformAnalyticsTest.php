<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Models\PlatformEvent;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): void
    {
        $u = User::factory()->create(['email_verified_at' => now()]);
        PlatformAdmin::create(['user_id' => $u->id, 'email' => $u->email]);
        $this->platformSession($u);
    }

    public function test_clients_filter_products_pagination_and_do_not_return_secrets(): void
    {

        $t = Tenant::create(['nome' => 'one', 'slug' => 'one']);
        $a = User::factory()->create(['tenant_id' => $t->id]);

        User::factory()->create();
        TenantProductSubscription::create(['tenant_id' => $t->id, 'product_slug' => 'corretor', 'status' => 'trial', 'active' => true]);
        $this->operator();

        $r = $this->getJson('/api/platform/clients?product=corretor&per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $a->id);

        $this->assertArrayNotHasKey('password', $r->json('data.0'));
        $this->getJson('/api/platform/clients/'.$a->id)->assertOk()->assertJsonPath('revenue', null);

        $this->getJson('/api/platform/clients?per_page=101')->assertUnprocessable();
        $this->getJson('/api/platform/events?from=2000-01-01')->assertUnprocessable();

    }

    public function test_funnel_is_cohort_distinct_and_paid_values_are_not_invented(): void
    {

        $t = Tenant::create(['nome' => 'cohort', 'slug' => 'cohort']);
        User::factory()->count(2)->create(['tenant_id' => $t->id, 'email_verified_at' => now()]);

        foreach (['corretor', 'imobiliaria'] as $p) {
            TenantProductSubscription::create(['tenant_id' => $t->id, 'product_slug' => $p, 'status' => 'trial', 'active' => true, 'trial_starts_at' => now(), 'trial_ends_at' => now()->addDays(7)]);
        }

        $this->operator();
        $this->getJson('/api/platform/funnel?product=corretor')->assertOk()->assertJsonPath('stages.1.value', 2)->assertJsonPath('stages.3.value', 2)->assertJsonPath('stages.4.value', null);

        $this->getJson('/api/platform/overview?product=corretor')->assertOk()->assertJsonPath('revenue.mrr', null);

    }

    public function test_security_event_filters_do_not_mix_tenants(): void
    {

        PlatformEvent::create(['category' => 'security', 'event' => 'blocked', 'tenant_id' => 1, 'product' => 'corretor']);
        PlatformEvent::create(['category' => 'security', 'event' => 'blocked', 'tenant_id' => 2, 'product' => 'imobiliaria']);
        $this->operator();

        $this->getJson('/api/platform/events?category=security&tenant_id=1&product=corretor')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.tenant_id', 1);

        $this->getJson('/api/platform/cohorts')->assertOk();

    }
}
