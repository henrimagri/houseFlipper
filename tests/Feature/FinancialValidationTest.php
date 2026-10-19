<?php

namespace Tests\Feature;

use App\Models\InvestmentCost;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculated_returns_outside_database_precision_are_rejected_atomically(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
        $opportunity = Opportunity::create(['user_id' => $user->id, 'title' => 'Audit', 'property_type' => 'house', 'source' => 'manual']);
        $this->postJson("/api/opportunities/{$opportunity->id}/investment-analysis", ['purchase_price' => 100000, 'down_payment' => 0.01, 'expected_sale_price' => 999999999999, 'expected_months_until_sale' => 1])->assertUnprocessable();
        $this->assertDatabaseCount('investment_analyses', 0);
        $id = $this->postJson("/api/opportunities/{$opportunity->id}/investment-analysis", ['purchase_price' => 100000, 'down_payment' => 0.01, 'expected_sale_price' => 0, 'expected_months_until_sale' => 1])->assertCreated()->json('data.id');
        $this->postJson("/api/investment-analyses/$id/result", ['sale_price' => 999999999999, 'flip_duration_months' => 1])->assertUnprocessable();
        $this->assertDatabaseCount('investment_results', 0);
        $this->assertDatabaseHas('investment_analyses', ['id' => $id, 'result_completed_at' => null]);
    }

    public function test_financing_rejects_paid_installments_exceeding_total_and_extreme_duration(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
        $opportunity = Opportunity::create(['user_id' => $user->id, 'title' => 'Audit', 'property_type' => 'house', 'source' => 'manual']);
        $id = $this->postJson("/api/opportunities/{$opportunity->id}/investment-analysis", ['purchase_price' => 100000, 'down_payment' => 20000, 'expected_sale_price' => 150000, 'expected_months_until_sale' => 6])->assertCreated()->json('data.id');
        $this->postJson("/api/investment-analyses/$id/financing-simulations", ['total_installments' => 12, 'paid_installments' => 13])->assertUnprocessable();
        $this->postJson("/api/investment-analyses/$id/financing-simulations", ['total_installments' => 2147483647])->assertUnprocessable();
        $this->postJson("/api/investment-analyses/$id/result", ['sale_price' => 100000, 'flip_duration_months' => 2147483647])->assertUnprocessable();
    }

    public function test_detailed_cost_payment_status_keeps_paid_at_consistent(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
        $opportunity = Opportunity::create(['user_id' => $user->id, 'title' => 'Paid cost', 'property_type' => 'house', 'source' => 'manual']);
        $analysisId = $this->postJson("/api/opportunities/{$opportunity->id}/investment-analysis", [
            'purchase_price' => 100000,
            'down_payment' => 20000,
            'expected_sale_price' => 150000,
            'expected_months_until_sale' => 6,
        ])->assertCreated()->json('data.id');
        $payload = ['category' => 'renovation', 'description' => 'Custo pago', 'amount' => 500, 'payment_status' => 'paid'];

        $costId = $this->postJson("/api/investment-analyses/$analysisId/costs", $payload)
            ->assertCreated()->json('id');
        $this->assertSame(now()->toDateString(), InvestmentCost::findOrFail($costId)->paid_at?->toDateString());

        $this->putJson("/api/investment-costs/$costId", $payload + ['paid_at' => '2026-10-16'])->assertOk();
        $this->assertSame('2026-10-16', InvestmentCost::findOrFail($costId)->paid_at?->toDateString());

        $this->putJson("/api/investment-costs/$costId", array_replace($payload, ['description' => 'Custo editado']))->assertOk();
        $this->assertSame('2026-10-16', InvestmentCost::findOrFail($costId)->paid_at?->toDateString());

        $this->putJson("/api/investment-costs/$costId", array_replace($payload, ['payment_status' => 'pending', 'paid_at' => '2026-10-16']))->assertOk();
        $this->assertNull(InvestmentCost::findOrFail($costId)->paid_at);
    }
}
