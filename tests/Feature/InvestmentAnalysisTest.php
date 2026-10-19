<?php

namespace Tests\Feature;

use App\Models\InvestmentAnalysis;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\FinancingReferenceService;
use App\Services\InvestmentAnalysisCalculatorService;
use App\Services\InvestmentIntelligenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestmentAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculator_applies_expected_financial_rules(): void
    {
        $result = app(InvestmentAnalysisCalculatorService::class)->calculate([
            'purchase_price' => 500000, 'down_payment' => 100000, 'detailed_costs_total' => 85000,
            'expected_sale_price' => 750000, 'expected_months_until_sale' => 10,
            'expected_monthly_costs' => 1000, 'expected_installment_value' => 4000,
            'expected_outstanding_balance' => 380000,
        ]);

        $this->assertSame(400000.0, $result['financed_amount']);
        $this->assertSame(225000.0, $result['total_own_capital_invested']);
        $this->assertSame(145000.0, $result['expected_net_profit']);
        $this->assertSame('excellent', $result['decision']);
    }

    public function test_calculator_uses_compound_equivalence_for_periodic_returns(): void
    {
        $result = app(InvestmentAnalysisCalculatorService::class)->calculate([
            'purchase_price' => 100000,
            'down_payment' => 100000,
            'detailed_costs_total' => 0,
            'expected_sale_price' => 200000,
            'expected_months_until_sale' => 12,
            'expected_monthly_costs' => 0,
            'expected_installment_value' => 0,
            'expected_outstanding_balance' => 0,
        ]);

        $this->assertSame(100.0, $result['expected_roi_percentage']);
        $this->assertSame(5.9463, $result['expected_monthly_roi_percentage']);
        $this->assertSame(100.0, $result['expected_annualized_roi_percentage']);
        $this->assertStringNotContainsString('negócio', $result['decision_reason']);
        $this->assertStringNotContainsString('comprar', $result['decision_reason']);
    }

    public function test_first_analysis_is_primary_and_cannot_be_read_by_another_user(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $opportunity = Opportunity::create(['user_id' => $owner->id, 'title' => 'Apartamento', 'property_type' => 'apartment', 'source' => 'manual']);
        $payload = ['purchase_price' => 300000, 'down_payment' => 60000, 'expected_sale_price' => 450000, 'expected_months_until_sale' => 6];

        $response = $this->actingAs($owner, 'sanctum')->postJson("/api/opportunities/{$opportunity->id}/investment-analysis", $payload)->assertCreated();
        $analysisId = $response->json('data.id');
        $this->assertDatabaseHas('investment_analyses', ['id' => $analysisId, 'user_id' => $owner->id, 'is_primary' => true]);
        $this->actingAs($intruder, 'sanctum')->getJson("/api/investment-analyses/{$analysisId}")->assertNotFound();
    }

    public function test_analysis_cover_uses_the_same_public_image_policy_as_opportunities(): void
    {
        $user = User::factory()->create();
        $opportunity = Opportunity::create(['user_id' => $user->id, 'title' => 'Capa auditada', 'property_type' => 'house', 'source' => 'manual']);
        $analysisId = $this->actingAs($user, 'sanctum')->postJson("/api/opportunities/{$opportunity->id}/investment-analysis", [
            'purchase_price' => 300000,
            'down_payment' => 60000,
            'expected_sale_price' => 450000,
            'expected_months_until_sale' => 6,
        ])->assertCreated()->json('data.id');
        $photo = $opportunity->photos()->create(['path' => 'https://127.0.0.1/private.jpg', 'original_name' => 'cover.jpg', 'is_cover' => true]);

        foreach (['https://127.0.0.1/private.jpg', 'opportunities/../documents/private.jpg', 'documents/tenant/private.jpg'] as $unsafePath) {
            $photo->update(['path' => $unsafePath]);
            $this->getJson("/api/investment-analyses/{$analysisId}")
                ->assertOk()
                ->assertJsonPath('data.opportunity.cover_url', null)
                ->assertJsonMissing(['cover_url' => $unsafePath]);
        }

        $photo->update(['path' => 'opportunities/tenant/safe.jpg']);
        $this->getJson("/api/investment-analyses/{$analysisId}")
            ->assertOk()
            ->assertJsonPath('data.opportunity.cover_url', '/storage/opportunities/tenant/safe.jpg');

        $photo->update(['path' => 'https://images.example.test/safe.jpg']);
        $this->getJson("/api/investment-analyses/{$analysisId}")
            ->assertOk()
            ->assertJsonPath('data.opportunity.cover_url', 'https://images.example.test/safe.jpg');
    }

    public function test_analysis_resource_omits_internal_ownership_and_relation_keys(): void
    {
        $user = User::factory()->create();
        $opportunity = Opportunity::create(['user_id' => $user->id, 'title' => 'Análise mínima', 'property_type' => 'house', 'source' => 'manual']);
        $analysis = $opportunity->investmentAnalyses()->create([
            'user_id' => $user->id,
            'purchase_price' => 300000,
            'down_payment' => 60000,
            'expected_sale_price' => 450000,
            'expected_months_until_sale' => 6,
        ]);
        $analysis->financingSimulations()->create([
            'label' => 'Cenário seguro',
            'is_selected' => true,
            'bank_name' => 'Banco teste',
            'financing_system' => 'PRICE',
        ]);
        $cost = $analysis->costs()->create([
            'user_id' => $user->id,
            'category' => 'renovation',
            'description' => 'Obra',
            'amount' => 10000,
        ]);
        $result = $analysis->result()->create([
            'sale_price' => 450000,
            'net_profit' => 100000,
            'flip_duration_months' => 6,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/investment-analyses/{$analysis->id}")
            ->assertOk()
            ->assertJsonPath('data.financing_detail.bank_name', 'Banco teste')
            ->assertJsonPath('data.financing_simulations.0.label', 'Cenário seguro')
            ->assertJsonPath('data.costs.0.description', 'Obra')
            ->assertJsonPath('data.result.net_profit', 100000);

        foreach ([
            'data.user_id', 'data.deleted_at',
            'data.financing_detail.investment_analysis_id',
            'data.financing_simulations.0.investment_analysis_id',
            'data.costs.0.investment_analysis_id', 'data.costs.0.user_id',
            'data.result.investment_analysis_id',
        ] as $path) {
            $response->assertJsonMissingPath($path);
        }

        $this->postJson("/api/investment-analyses/{$analysis->id}/financing-simulations", [
            'label' => 'Cenário adicional',
            'bank_name' => 'Outro banco',
            'financing_system' => 'SAC',
        ])->assertCreated()
            ->assertJsonMissingPath('investment_analysis_id');

        $this->putJson("/api/investment-costs/{$cost->id}", [
            'category' => 'renovation',
            'description' => 'Obra atualizada',
            'amount' => 12000,
            'payment_status' => 'pending',
        ])->assertOk()
            ->assertJsonMissingPath('investment_analysis_id')
            ->assertJsonMissingPath('user_id');

        $this->putJson("/api/investment-results/{$result->id}", [
            'sale_price' => 450000,
            'sale_costs' => 0,
            'flip_duration_months' => 6,
        ])->assertOk()
            ->assertJsonMissingPath('investment_analysis_id');
    }

    public function test_empty_optional_values_are_saved_as_zero(): void
    {
        $user = User::factory()->create();
        $opportunity = Opportunity::create(['user_id' => $user->id, 'title' => 'Casa', 'property_type' => 'house', 'source' => 'manual']);
        $analysisId = $this->actingAs($user, 'sanctum')->postJson("/api/opportunities/{$opportunity->id}/investment-analysis", [
            'purchase_price' => 500000, 'down_payment' => 100000,
            'expected_sale_price' => 700000, 'expected_months_until_sale' => 6,
        ])->json('data.id');

        $this->actingAs($user, 'sanctum')->putJson("/api/investment-analyses/{$analysisId}", [
            'purchase_price' => 500000, 'down_payment' => 100000,
            'expected_sale_price' => 700000, 'expected_months_until_sale' => 6,
            'expected_monthly_costs' => null, 'broker_commission' => null,
        ])->assertOk();

        $this->assertDatabaseHas('investment_analyses', ['id' => $analysisId, 'expected_monthly_costs' => 0, 'broker_commission' => 0]);
    }

    public function test_financing_simulation_uses_only_the_rate_informed_by_the_user(): void
    {
        $analysis = new InvestmentAnalysis(['financed_amount' => 400000]);

        $reference = app(FinancingReferenceService::class)->for($analysis, null, 'PRICE', 360, null, 0, 0.95, 'Banco informado');

        $this->assertSame(0.95, $reference['interest_rate_monthly']);
        $this->assertSame(12.0149, $reference['interest_rate_yearly']);
        $this->assertSame('Banco informado', $reference['bank_name']);
        $this->assertNull($reference['reference_date']);
        $this->assertSame([], app(FinancingReferenceService::class)->banks());
        $this->assertSame(400000.0, $reference['outstanding_balance']);
        $this->assertGreaterThan(0, $reference['installment_value']);
        $this->assertTrue($reference['is_estimate']);

        $afterEightPayments = app(FinancingReferenceService::class)->for($analysis, null, 'PRICE', 360, 400000, 8, 0.95, 'Banco informado');
        $this->assertSame(8, $afterEightPayments['paid_installments']);
        $this->assertLessThan(400000, $afterEightPayments['outstanding_balance']);
        $this->assertSame($afterEightPayments['outstanding_balance'], $afterEightPayments['payoff_amount']);
    }

    public function test_financing_reference_endpoint_accepts_manual_rate_and_rejects_out_of_range_values(): void
    {
        $user = User::factory()->create();
        $opportunity = Opportunity::create(['user_id' => $user->id, 'title' => 'Casa', 'property_type' => 'house', 'source' => 'manual']);
        $analysis = $opportunity->investmentAnalyses()->create([
            'user_id' => $user->id, 'purchase_price' => 500000, 'down_payment' => 100000,
            'financed_amount' => 400000, 'expected_sale_price' => 700000, 'expected_months_until_sale' => 8,
        ]);

        $this->actingAs($user, 'sanctum')->getJson("/api/investment-analyses/{$analysis->id}/financing-reference?system=PRICE&installments=360&monthly_rate=0.95&bank_name=Banco%20informado")
            ->assertOk()
            ->assertJsonPath('data.bank_name', 'Banco informado')
            ->assertJsonPath('data.interest_rate_monthly', 0.95)
            ->assertJsonPath('data.interest_rate_yearly', 12.0149)
            ->assertJsonPath('data.reference_date', null)
            ->assertJsonCount(0, 'banks');

        $this->actingAs($user, 'sanctum')->getJson("/api/investment-analyses/{$analysis->id}/financing-reference?monthly_rate=101")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('monthly_rate');
    }

    public function test_actual_own_capital_uses_down_payment_projected_costs_and_paid_installments(): void
    {
        $user = User::factory()->create();
        $opportunity = Opportunity::create(['user_id' => $user->id, 'title' => 'Casa', 'property_type' => 'house', 'source' => 'manual']);
        $analysisId = $this->actingAs($user, 'sanctum')->postJson("/api/opportunities/{$opportunity->id}/investment-analysis", [
            'purchase_price' => 500000, 'down_payment' => 100000,
            'expected_sale_price' => 700000, 'expected_months_until_sale' => 6,
        ])->json('data.id');
        $cost = $this->actingAs($user, 'sanctum')->postJson("/api/investment-analyses/{$analysisId}/costs", [
            'category' => 'renovation', 'description' => 'Obra', 'amount' => 90000, 'payment_status' => 'pending',
        ])->json();

        $this->actingAs($user, 'sanctum')->getJson("/api/investment-analyses/{$analysisId}")->assertJsonPath('data.actual_own_capital_invested', 190000);
        $this->actingAs($user, 'sanctum')->putJson("/api/investment-costs/{$cost['id']}", [
            'category' => 'renovation', 'description' => 'Obra', 'amount' => 90000, 'payment_status' => 'paid',
        ])->assertOk();
        $this->actingAs($user, 'sanctum')->getJson("/api/investment-analyses/{$analysisId}")->assertJsonPath('data.actual_own_capital_invested', 190000);

        $this->actingAs($user, 'sanctum')->putJson("/api/investment-analyses/{$analysisId}/financing", [
            'bank_name' => 'Banco teste', 'financing_system' => 'PRICE', 'total_installments' => 360,
            'installment_value' => 5000, 'paid_installments' => 8,
            'outstanding_balance' => 390000, 'payoff_amount' => 390000,
        ])->assertOk();
        $this->actingAs($user, 'sanctum')->getJson("/api/investment-analyses/{$analysisId}")->assertJsonPath('data.actual_own_capital_invested', 230000);

        $this->actingAs($user, 'sanctum')->postJson("/api/investment-analyses/{$analysisId}/result", [
            'sale_price' => 850000, 'sale_costs' => 0, 'payoff_amount' => 1,
            'total_own_capital_invested' => 1, 'flip_duration_months' => 6,
        ])->assertJsonPath('total_own_capital_invested', 230000)
            ->assertJsonPath('payoff_amount', 390000)
            ->assertJsonPath('net_profit', 230000)
            ->assertJsonPath('roi_percentage', 100);
    }

    public function test_monthly_operating_costs_are_applied_for_the_flip_duration(): void
    {
        $user = User::factory()->create();
        $opportunity = Opportunity::create(['user_id' => $user->id, 'title' => 'Apartamento', 'property_type' => 'apartment', 'source' => 'manual']);
        $analysisId = $this->actingAs($user, 'sanctum')->postJson("/api/opportunities/{$opportunity->id}/investment-analysis", [
            'purchase_price' => 600000, 'down_payment' => 120000,
            'expected_sale_price' => 950000, 'expected_months_until_sale' => 6,
        ])->json('data.id');

        $this->actingAs($user, 'sanctum')->postJson("/api/investment-analyses/{$analysisId}/costs", [
            'category' => 'renovation', 'description' => 'Reforma total', 'amount' => 90000, 'payment_status' => 'pending',
        ])->assertCreated();
        $this->actingAs($user, 'sanctum')->postJson("/api/investment-analyses/{$analysisId}/costs", [
            'category' => 'condominium', 'description' => 'Condomínio', 'amount' => 3000, 'payment_status' => 'pending',
        ])->assertCreated();

        $this->actingAs($user, 'sanctum')->getJson("/api/investment-analyses/{$analysisId}")
            ->assertJsonPath('data.detailed_costs_total', 108000)
            ->assertJsonPath('data.actual_own_capital_invested', 228000);
    }

    public function test_intelligence_separates_market_score_from_financial_decision(): void
    {
        $user = User::factory()->create();
        $opportunity = Opportunity::create(['user_id' => $user->id, 'title' => 'Flip', 'property_type' => 'house', 'source' => 'manual', 'asking_price' => 400000, 'estimated_market_price' => 600000]);
        $analysis = $opportunity->investmentAnalyses()->create(['user_id' => $user->id, 'purchase_price' => 400000, 'down_payment' => 100000, 'financed_amount' => 300000, 'expected_sale_price' => 600000, 'expected_months_until_sale' => 6, 'total_own_capital_invested' => 100000, 'expected_net_profit' => 200000, 'expected_roi_percentage' => 200]);

        $intelligence = app(InvestmentIntelligenceService::class)->analyze($analysis);

        $this->assertCount(3, $intelligence['scenarios']);
        $this->assertSame(600000.0, $intelligence['scenarios']['realistic']['sale_price']);
        $this->assertSame(0.0, $intelligence['scenarios']['realistic']['assumptions']['sale_price_change_percentage']);
        $this->assertSame(540000.0, $intelligence['scenarios']['pessimistic']['sale_price']);
        $this->assertSame(-10.0, $intelligence['scenarios']['pessimistic']['assumptions']['sale_price_change_percentage']);
        $this->assertSame(648000.0, $intelligence['scenarios']['optimistic']['sale_price']);
        $this->assertSame(8.0, $intelligence['scenarios']['optimistic']['assumptions']['sale_price_change_percentage']);
        $this->assertGreaterThanOrEqual(70, $intelligence['opportunity_score']['score']);
        $this->assertSame('Boa oportunidade de mercado', $intelligence['opportunity_score']['label']);
        $this->assertNull($intelligence['investment_score']);
        $this->assertNull($intelligence['recommendation']);
        $this->assertFalse($intelligence['financial_analysis_complete']);

        $analysis->update(['viability_completed_at' => now(), 'financing_completed_at' => now(), 'costs_completed_at' => now(), 'result_completed_at' => now()]);
        $analysis->result()->create(['sale_price' => 600000, 'payoff_amount' => 300000, 'total_own_capital_invested' => 100000, 'net_amount_received_after_payoff' => 300000, 'net_profit' => 200000, 'roi_percentage' => 200, 'monthly_roi_percentage' => 33.33, 'annualized_roi_percentage' => 3000, 'flip_duration_months' => 6]);
        $completed = app(InvestmentIntelligenceService::class)->analyze($analysis->refresh());

        $this->assertTrue($completed['financial_analysis_complete']);
        $this->assertSame('Acima da meta', $completed['investment_score']['signal']['label']);
        $this->assertSame('rules_based_financial_calculation', $completed['analysis_method']);
        $this->assertSame(90, $completed['data_completeness']);
        $this->assertArrayNotHasKey('confidence', $completed['investment_score']);
        $this->assertNull($completed['recommendation']);
        $this->assertSame('Acima da meta', $completed['rule_signal']);
    }

    public function test_user_can_compare_and_select_multiple_financing_simulations(): void
    {
        $user = User::factory()->create();
        $opportunity = Opportunity::create(['user_id' => $user->id, 'title' => 'Flip', 'property_type' => 'house', 'source' => 'manual']);
        $analysisId = $this->actingAs($user, 'sanctum')->postJson("/api/opportunities/{$opportunity->id}/investment-analysis", ['purchase_price' => 500000, 'down_payment' => 100000, 'expected_sale_price' => 700000, 'expected_months_until_sale' => 6])->json('data.id');
        $first = $this->actingAs($user, 'sanctum')->postJson("/api/investment-analyses/{$analysisId}/financing-simulations", ['bank_name' => 'Caixa', 'label' => 'Caixa', 'financing_system' => 'SAC', 'installment_value' => 5000, 'outstanding_balance' => 380000, 'payoff_amount' => 380000])->assertCreated()->json();
        $second = $this->actingAs($user, 'sanctum')->postJson("/api/investment-analyses/{$analysisId}/financing-simulations", ['bank_name' => 'Itaú', 'label' => 'Itaú', 'financing_system' => 'PRICE', 'installment_value' => 4800, 'outstanding_balance' => 375000, 'payoff_amount' => 375000])->assertCreated()->json();

        $this->assertTrue((bool) $first['is_selected']);
        $this->assertFalse((bool) $second['is_selected']);
        $this->actingAs($user, 'sanctum')->patchJson("/api/financing-details/{$second['id']}/select")->assertOk()->assertJsonPath('data.financing_detail.id', $second['id']);
    }
}
