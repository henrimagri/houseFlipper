<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_financing_selection_updates_saved_result_and_last_deletion_clears_values(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
        $opportunity = Opportunity::create(['user_id' => $user->id, 'title' => 'Audit', 'property_type' => 'house', 'source' => 'manual']);
        $id = $this->postJson("/api/opportunities/{$opportunity->id}/investment-analysis", [
            'purchase_price' => 100000, 'down_payment' => 20000, 'expected_sale_price' => 150000, 'expected_months_until_sale' => 6,
        ])->assertCreated()->json('data.id');
        $a = $this->postJson("/api/investment-analyses/$id/financing-simulations", ['label' => 'A', 'installment_value' => 1000, 'total_installments' => 360, 'paid_installments' => 2, 'payoff_amount' => 80000, 'outstanding_balance' => 80000])->assertCreated()->json('id');
        $b = $this->postJson("/api/investment-analyses/$id/financing-simulations", ['label' => 'B', 'installment_value' => 500, 'total_installments' => 360, 'paid_installments' => 2, 'payoff_amount' => 70000, 'outstanding_balance' => 70000])->assertCreated()->json('id');
        $this->postJson("/api/investment-analyses/$id/result", ['sale_price' => 150000, 'flip_duration_months' => 6])->assertOk();
        $this->patchJson("/api/financing-details/$b/select")->assertOk()->assertJsonPath('data.result.payoff_amount', 70000);
        $this->deleteJson("/api/financing-details/$a")->assertOk();
        $this->deleteJson("/api/financing-details/$b")->assertOk();
        $this->getJson("/api/investment-analyses/$id")->assertOk()->assertJsonPath('data.expected_installment_value', 0)->assertJsonPath('data.expected_outstanding_balance', 0)->assertJsonPath('data.progress.financing', false);
    }
}
