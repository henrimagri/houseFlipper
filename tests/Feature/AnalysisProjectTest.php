<?php

namespace Tests\Feature;

use App\Models\InvestmentAnalysis;
use App\Models\InvestmentCost;
use App\Models\Opportunity;
use App\Models\Projeto;
use App\Models\ProjetoEtapa;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysisProjectTest extends TestCase
{
    use RefreshDatabase;

    private function analysis(): InvestmentAnalysis
    {
        $tenant = Tenant::create(['nome' => 'Conversion QA', 'slug' => 'conversion-qa']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->actingAs($user, 'sanctum');
        $opportunity = Opportunity::create(['user_id' => $user->id, 'title' => 'QA conversion', 'city' => 'São Paulo', 'state' => 'SP', 'property_type' => 'house', 'source' => 'manual']);
        $id = $this->postJson("/api/opportunities/{$opportunity->id}/investment-analysis", [
            'purchase_price' => 100000, 'down_payment' => 20000, 'expected_sale_price' => 150000, 'expected_months_until_sale' => 6,
        ])->assertCreated()->json('data.id');

        return InvestmentAnalysis::findOrFail($id);
    }

    public function test_conversion_is_idempotent_uses_saved_values_and_allocates_every_cent(): void
    {
        $analysis = $this->analysis();
        $analysis->costs()->create(['user_id' => $analysis->user_id, 'category' => 'renovation', 'description' => 'QA renovation', 'amount' => 123.47, 'payment_status' => 'pending']);
        $analysis->costs()->create(['user_id' => $analysis->user_id, 'category' => 'registry', 'description' => 'QA registry', 'amount' => 999, 'payment_status' => 'pending']);
        $url = "/api/investment-analyses/{$analysis->id}/project";
        $project = $this->postJson($url, ['tenant_id' => 999, 'orcamento_total' => 999999, 'propriedade_id' => 999])
            ->assertCreated()->assertJsonPath('data.orcamento_total', '123.47')->assertJsonCount(7, 'data.etapas')->json('data');
        $this->postJson($url)->assertOk()->assertJsonPath('data.id', $project['id']);
        $this->assertDatabaseCount('propriedades', 1);
        $this->assertDatabaseCount('propriedades_enderecos', 1);
        $this->assertDatabaseCount('projetos', 1);
        $this->assertDatabaseCount('projetos_etapas', 7);
        $this->assertDatabaseCount('work_budgets', 4);
        $this->assertSame($project['id'], $analysis->refresh()->projeto_id);
        $this->assertEqualsWithDelta(123.47, array_sum(array_column($project['etapas'], 'orcamento')), 0.001);
        $this->assertDatabaseHas('propriedades', ['id' => $project['propriedade_id'], 'tenant_id' => auth()->user()->tenant_id, 'valor_aquisicao' => 100000, 'tipo' => 'house']);
        $this->getJson("/api/investment-analyses/{$analysis->id}")->assertOk()->assertJsonPath('data.projeto_id', $project['id']);
        $this->putJson("/api/investment-analyses/{$analysis->id}", [
            'purchase_price' => 100000, 'down_payment' => 20000, 'expected_sale_price' => 150000,
            'expected_months_until_sale' => 6, 'projeto_id' => null,
        ])->assertOk()->assertJsonPath('data.projeto_id', $project['id']);
    }

    public function test_failure_in_stage_or_final_link_rolls_back_every_created_record(): void
    {
        $analysis = $this->analysis();
        foreach (['eloquent.creating: '.ProjetoEtapa::class, 'eloquent.updating: '.InvestmentAnalysis::class] as $event) {
            app('events')->listen($event, fn () => throw new \RuntimeException('QA simulated conversion failure'));
            try {
                $this->postJson("/api/investment-analyses/{$analysis->id}/project")->assertStatus(500);
                foreach (['propriedades', 'propriedades_enderecos', 'projetos', 'projetos_etapas', 'work_budgets'] as $table) {
                    $this->assertDatabaseCount($table, 0);
                }
                $this->assertNull($analysis->refresh()->projeto_id);
            } finally {
                app('events')->forget($event);
            }
        }
        $this->postJson("/api/investment-analyses/{$analysis->id}/project")->assertCreated();
    }

    public function test_legacy_two_request_protocol_leaves_property_after_second_request_fails(): void
    {
        $this->analysis();
        $property = $this->postJson('/api/propriedades', ['apelido' => 'QA legacy conversion', 'endereco' => ['cidade' => 'São Paulo', 'estado' => 'SP']])->assertCreated()->json('data');
        $event = 'eloquent.creating: '.ProjetoEtapa::class;
        app('events')->listen($event, fn () => throw new \RuntimeException('QA simulated second request failure'));
        try {
            $this->postJson('/api/projetos', ['propriedade_id' => $property['id'], 'nome' => 'QA legacy project'])->assertStatus(500);
            $this->assertDatabaseCount('propriedades', 1);
            $this->assertDatabaseCount('projetos', 0);
        } finally {
            app('events')->forget($event);
        }
    }

    public function test_other_users_including_same_tenant_cannot_convert_someone_elses_analysis(): void
    {
        $analysis = $this->analysis();
        $owner = auth()->user();
        foreach ([$owner->tenant_id, Tenant::create(['nome' => 'Other QA', 'slug' => 'other-conversion-qa'])->id] as $tenantId) {
            $this->actingAs(User::factory()->create(['tenant_id' => $tenantId]), 'sanctum');
            $this->postJson("/api/investment-analyses/{$analysis->id}/project")->assertNotFound();
        }
        $this->assertDatabaseCount('propriedades', 0);
        $this->assertDatabaseCount('projetos', 0);
    }

    public function test_tenantless_owner_is_rejected_without_partial_records(): void
    {
        $analysis = $this->analysis();
        auth()->user()->update(['tenant_id' => null]);
        $this->postJson("/api/investment-analyses/{$analysis->id}/project")->assertForbidden();
        $this->assertDatabaseCount('propriedades', 0);
    }

    public function test_deleted_project_cannot_be_silently_recreated(): void
    {
        $analysis = $this->analysis();
        $id = $this->postJson("/api/investment-analyses/{$analysis->id}/project")->assertCreated()->json('data.id');
        $this->deleteJson("/api/projetos/$id")->assertOk();
        $this->postJson("/api/investment-analyses/{$analysis->id}/project")->assertConflict();
        $this->assertDatabaseCount('projetos', 1);
        $this->assertDatabaseCount('propriedades', 1);
    }

    public function test_legacy_description_is_not_silently_adopted_or_duplicated(): void
    {
        $analysis = $this->analysis();
        $property = $this->postJson('/api/propriedades', ['apelido' => 'QA legacy'])->assertCreated()->json('data.id');
        $project = $this->postJson('/api/projetos', ['propriedade_id' => $property, 'nome' => 'QA legacy', 'descricao' => "Projeto originado da análise financeira #{$analysis->id}. ROI histórico."])->assertCreated()->json('data.id');
        $this->postJson("/api/investment-analyses/{$analysis->id}/project")->assertConflict();
        $this->assertNull($analysis->refresh()->projeto_id);
        Projeto::findOrFail($project)->update(['descricao' => "Projeto originado da análise financeira #{$analysis->id}0."]);
        $this->postJson("/api/investment-analyses/{$analysis->id}/project")->assertCreated();
        $this->assertDatabaseCount('projetos', 2);
    }

    public function test_long_titles_and_city_names_fit_destination_columns(): void
    {
        $analysis = $this->analysis();
        $analysis->opportunity->update(['title' => str_repeat('Á', 255), 'city' => str_repeat('Ç', 120)]);
        $project = $this->postJson("/api/investment-analyses/{$analysis->id}/project")->assertCreated()->json('data');
        $this->assertSame(150, mb_strlen($project['nome']));
        $this->assertSame(120, mb_strlen($project['propriedade']['apelido']));
    }

    public function test_budget_overflow_is_rejected_before_any_property_is_created(): void
    {
        $analysis = $this->analysis();
        foreach ([1, 2] as $index) {
            InvestmentCost::create(['investment_analysis_id' => $analysis->id, 'user_id' => $analysis->user_id, 'category' => 'renovation', 'description' => "QA $index", 'amount' => 999999999999, 'payment_status' => 'pending']);
        }
        $this->postJson("/api/investment-analyses/{$analysis->id}/project")->assertUnprocessable()->assertJsonValidationErrors('orcamento_total');
        $this->assertDatabaseCount('propriedades', 0);
    }

    public function test_conversion_requires_an_active_verified_owner_and_valid_route(): void
    {
        $this->postJson('/api/investment-analyses/1/project')->assertUnauthorized();
        $analysis = $this->analysis();
        $user = auth()->user();
        $url = "/api/investment-analyses/{$analysis->id}/project";
        $user->forceFill(['requires_email_verification' => true, 'email_verified_at' => null])->save();
        $this->postJson($url)->assertForbidden()->assertJsonPath('code', 'email_verification_required');
        $user->forceFill(['email_verified_at' => now(), 'ativo_flg' => false])->save();
        $this->postJson($url)->assertUnauthorized();
        $user->forceFill(['ativo_flg' => true])->save();
        $this->postJson('/api/investment-analyses/999999/project')->assertNotFound();
        $this->postJson('/api/investment-analyses/not-a-number/project')->assertNotFound();
        $this->putJson($url)->assertStatus(405);
        $this->assertDatabaseCount('propriedades', 0);
        $this->assertDatabaseCount('projetos', 0);
    }
}
