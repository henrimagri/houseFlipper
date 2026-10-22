<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_unknowns_and_csv_formula_protection_audited(): void
    {
        $u = User::factory()->create(['name' => '=HYPERLINK("bad")']);
        PlatformAdmin::create(['user_id' => $u->id, 'email' => $u->email]);
        Sanctum::actingAs($u);
        $this->getJson('/api/platform/billing')->assertOk()->assertJsonPath('mrr', null)->assertJsonPath('received_revenue', null);
        $this->getJson('/api/platform/webhooks')->assertOk();
        $this->getJson('/api/platform/emails')->assertOk()->assertJsonPath('delivery', 'not_configured');
        $response = $this->get('/api/platform/export?report=clients')->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString($u->email, $csv);
        $this->assertDatabaseHas('platform_events', ['event' => 'admin.report.exported']);
        $this->getJson('/api/platform/export?report=private_messages')->assertUnprocessable();
    }
}
