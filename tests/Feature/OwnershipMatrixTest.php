<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OwnershipMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleted_opportunity_blocks_its_analysis_and_financial_children(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
        $opportunity = Opportunity::create(['user_id' => $user->id, 'title' => 'Audit', 'property_type' => 'house', 'source' => 'manual']);
        $payload = ['purchase_price' => 100000, 'down_payment' => 20000, 'expected_sale_price' => 150000, 'expected_months_until_sale' => 6];
        $id = $this->postJson("/api/opportunities/{$opportunity->id}/investment-analysis", $payload)->assertCreated()->json('data.id');
        $costPayload = ['category' => 'other', 'description' => 'Audit', 'amount' => 100, 'payment_status' => 'pending'];
        $cost = $this->postJson("/api/investment-analyses/$id/costs", $costPayload)->assertCreated()->json('id');
        $detail = $this->postJson("/api/investment-analyses/$id/financing-simulations", ['label' => 'Audit'])->assertCreated()->json('id');
        $this->deleteJson("/api/opportunities/{$opportunity->id}")->assertOk();
        $this->getJson("/api/investment-analyses/$id")->assertNotFound();
        $this->putJson("/api/investment-analyses/$id", $payload)->assertNotFound();
        $this->putJson("/api/investment-costs/$cost", $costPayload)->assertNotFound();
        $this->deleteJson("/api/investment-costs/$cost")->assertNotFound();
        $this->patchJson("/api/financing-details/$detail/select")->assertNotFound();
        $this->assertDatabaseHas('investment_costs', ['id' => $cost, 'amount' => 100]);
    }

    public function test_every_private_mutation_rejects_another_user_without_changing_data(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $opportunity = Opportunity::create(['user_id' => $owner->id, 'title' => 'Private', 'property_type' => 'house', 'source' => 'manual']);
        $this->actingAs($owner, 'sanctum');
        $analysisPayload = ['purchase_price' => 100000, 'down_payment' => 20000, 'expected_sale_price' => 150000, 'expected_months_until_sale' => 6];
        $analysis = $this->postJson("/api/opportunities/{$opportunity->id}/investment-analysis", $analysisPayload)->assertCreated()->json('data.id');
        $financing = $this->postJson("/api/investment-analyses/$analysis/financing-simulations", ['label' => 'Private'])->assertCreated()->json('id');
        $costPayload = ['category' => 'other', 'description' => 'Private', 'amount' => 100, 'payment_status' => 'pending'];
        $cost = $this->postJson("/api/investment-analyses/$analysis/costs", $costPayload)->assertCreated()->json('id');
        $resultPayload = ['sale_price' => 150000, 'flip_duration_months' => 6];
        $result = $this->postJson("/api/investment-analyses/$analysis/result", $resultPayload)->assertOk()->json('id');
        $before = [];
        foreach (['opportunities', 'investment_analyses', 'investment_costs', 'investment_results', 'financing_details', 'opportunity_status_histories'] as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        $this->actingAs($intruder, 'sanctum');
        $checks = [
            ['GET', "/api/opportunities/{$opportunity->id}", []],
            ['PUT', "/api/opportunities/{$opportunity->id}", ['title' => 'Intruder', 'property_type' => 'house', 'source' => 'manual']],
            ['DELETE', "/api/opportunities/{$opportunity->id}", []],
            ['PATCH', "/api/opportunities/{$opportunity->id}/favorite", []],
            ['PATCH', "/api/opportunities/{$opportunity->id}/archive", []],
            ['PATCH', "/api/opportunities/{$opportunity->id}/discard", ['discarded_reason' => 'Intruder']],
            ['PATCH', "/api/opportunities/{$opportunity->id}/status", ['status' => 'analyzing']],
            ['POST', "/api/opportunities/{$opportunity->id}/investment-analysis", $analysisPayload],
            ['GET', "/api/investment-analyses/$analysis", []],
            ['PUT', "/api/investment-analyses/$analysis", $analysisPayload],
            ['PUT', "/api/investment-analyses/$analysis/financing", ['label' => 'Intruder']],
            ['POST', "/api/investment-analyses/$analysis/financing-simulations", ['label' => 'Intruder']],
            ['GET', "/api/investment-analyses/$analysis/financing-reference", []],
            ['PATCH', "/api/financing-details/$financing/select", []],
            ['DELETE', "/api/financing-details/$financing", []],
            ['POST', "/api/investment-analyses/$analysis/costs", $costPayload],
            ['PUT', "/api/investment-costs/$cost", $costPayload],
            ['DELETE', "/api/investment-costs/$cost", []],
            ['POST', "/api/investment-analyses/$analysis/result", $resultPayload],
            ['PUT', "/api/investment-results/$result", $resultPayload],
        ];
        foreach ($checks as [$method, $uri, $payload]) {
            $response = $this->json($method, $uri, $payload);
            $this->assertSame(404, $response->status(), "$method $uri must conceal the foreign resource.");
        }
        foreach ($before as $table => $rows) {
            $this->assertSame($rows, DB::table($table)->orderBy('id')->get()->toJson(), $table);
        }
    }
}
