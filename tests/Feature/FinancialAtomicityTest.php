<?php

namespace Tests\Feature;

use App\Models\FinancingDetail;
use App\Models\InvestmentAnalysis;
use App\Models\InvestmentCost;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialAtomicityTest extends TestCase
{
    use RefreshDatabase;

    public function test_financing_mutations_roll_back_if_analysis_update_fails(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
        $opportunity = Opportunity::create(['user_id' => $user->id, 'title' => 'Audit', 'property_type' => 'house', 'source' => 'manual']);
        $id = $this->postJson("/api/opportunities/{$opportunity->id}/investment-analysis", ['purchase_price' => 100000, 'down_payment' => 20000, 'expected_sale_price' => 150000, 'expected_months_until_sale' => 6])->assertCreated()->json('data.id');
        $event = 'eloquent.updating: '.InvestmentAnalysis::class;
        foreach (['POST', 'PUT'] as $method) {
            $before = InvestmentAnalysis::findOrFail($id)->getRawOriginal();
            app('events')->listen($event, fn () => throw new \RuntimeException('Audit simulated persistence failure'));
            try {
                $uri = $method === 'POST' ? "/api/investment-analyses/$id/financing-simulations" : "/api/investment-analyses/$id/financing";
                $this->json($method, $uri, ['label' => 'Rollback', 'installment_value' => 100])->assertStatus(500);
                $this->assertDatabaseCount('financing_details', 0);
                $this->assertSame($before, InvestmentAnalysis::findOrFail($id)->getRawOriginal());
            } finally {
                app('events')->forget($event);
            }
        }
        $simulation = $this->postJson("/api/investment-analyses/$id/financing-simulations", ['label' => 'Keep', 'installment_value' => 100])->assertCreated()->json('id');
        $before = FinancingDetail::findOrFail($simulation)->getRawOriginal();
        app('events')->listen($event, fn () => throw new \RuntimeException('Audit simulated persistence failure'));
        try {
            $this->deleteJson("/api/financing-details/$simulation")->assertStatus(500);
            $this->assertSame($before, FinancingDetail::findOrFail($simulation)->getRawOriginal());
        } finally {
            app('events')->forget($event);
        }
    }

    public function test_failed_cost_recalculation_rolls_back_cost_creation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
        $opportunity = Opportunity::create(['user_id' => $user->id, 'title' => 'Audit', 'property_type' => 'house', 'source' => 'manual']);
        $id = $this->postJson("/api/opportunities/{$opportunity->id}/investment-analysis", ['purchase_price' => 100000, 'down_payment' => 20000, 'expected_sale_price' => 150000, 'expected_months_until_sale' => 6])->assertCreated()->json('data.id');
        $before = InvestmentAnalysis::findOrFail($id)->getRawOriginal();
        $event = 'eloquent.updating: '.InvestmentAnalysis::class;
        app('events')->listen($event, fn () => throw new \RuntimeException('Audit simulated persistence failure'));
        try {
            $this->postJson("/api/investment-analyses/$id/costs", ['category' => 'other', 'description' => 'Audit rollback', 'amount' => 500, 'payment_status' => 'pending'])->assertStatus(500);
            $this->assertDatabaseCount('investment_costs', 0);
            $this->assertSame($before, InvestmentAnalysis::findOrFail($id)->getRawOriginal());
        } finally {
            app('events')->forget($event);
        }
        $payload = ['category' => 'other', 'description' => 'Audit rollback', 'amount' => 500, 'payment_status' => 'pending'];
        $cost = $this->postJson("/api/investment-analyses/$id/costs", $payload)->assertCreated()->json('id');
        foreach (['PUT', 'DELETE'] as $method) {
            $beforeAnalysis = InvestmentAnalysis::findOrFail($id)->getRawOriginal();
            $beforeCost = InvestmentCost::findOrFail($cost)->getRawOriginal();
            app('events')->listen($event, fn () => throw new \RuntimeException('Audit simulated persistence failure'));
            try {
                $this->json($method, "/api/investment-costs/$cost", array_replace($payload, ['amount' => 999]))->assertStatus(500);
                $this->assertSame($beforeCost, InvestmentCost::findOrFail($cost)->getRawOriginal());
                $this->assertSame($beforeAnalysis, InvestmentAnalysis::findOrFail($id)->getRawOriginal());
            } finally {
                app('events')->forget($event);
            }
        }
        app('events')->listen($event, fn () => throw new \RuntimeException('Audit simulated persistence failure'));
        try {
            $this->postJson("/api/investment-analyses/$id/result", ['sale_price' => 150000, 'flip_duration_months' => 6])->assertStatus(500);
            $this->assertDatabaseCount('investment_results', 0);
        } finally {
            app('events')->forget($event);
        }
    }
}
