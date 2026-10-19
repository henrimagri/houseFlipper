<?php

namespace Tests\Feature;

use App\Models\Projeto;
use App\Models\Propriedade;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Venda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemporalValidationTest extends TestCase
{
    use RefreshDatabase;

    private function authenticate(): Propriedade
    {
        $tenant = Tenant::create(['nome' => 'Temporal QA', 'slug' => 'temporal-qa']);
        $this->actingAs(User::factory()->create(['tenant_id' => $tenant->id]), 'sanctum');

        return Propriedade::create(['apelido' => 'Imóvel temporal']);
    }

    public function test_project_rejects_stage_end_before_stage_start(): void
    {
        $property = $this->authenticate();

        $this->postJson('/api/projetos', [
            'propriedade_id' => $property->id,
            'nome' => 'Projeto temporal',
            'etapas' => [[
                'nome' => 'Etapa invertida',
                'data_inicio_prevista' => '2026-11-10',
                'data_termino_prevista' => '2026-11-09',
            ]],
        ])->assertUnprocessable()->assertJsonValidationErrors('etapas.0.data_termino_prevista');

        $this->assertDatabaseCount('projetos', 0);
        $this->assertDatabaseCount('projetos_etapas', 0);
    }

    public function test_sale_rejects_closing_or_deed_before_preceding_milestones(): void
    {
        $property = $this->authenticate();

        $this->postJson('/api/vendas', [
            'propriedade_id' => $property->id,
            'valor_venda' => 500000,
            'data_proposta' => '2026-11-10',
            'data_fechamento' => '2026-11-09',
            'data_escritura' => '2026-11-08',
        ])->assertUnprocessable()->assertJsonValidationErrors(['data_fechamento', 'data_escritura']);

        $this->assertDatabaseCount('vendas', 0);
    }

    public function test_sale_update_compares_new_date_with_existing_milestones(): void
    {
        $property = $this->authenticate();
        $sale = Venda::create([
            'propriedade_id' => $property->id,
            'valor_venda' => 500000,
            'data_proposta' => '2026-11-10',
            'data_fechamento' => '2026-11-11',
            'data_escritura' => '2026-11-12',
        ]);

        $this->putJson('/api/vendas/'.$sale->id, [
            'propriedade_id' => $property->id,
            'valor_venda' => 500000,
            'data_escritura' => '2026-11-09',
        ])->assertUnprocessable()->assertJsonValidationErrors('data_escritura');

        $this->assertSame('2026-11-12', $sale->refresh()->data_escritura->toDateString());

        $this->putJson('/api/vendas/'.$sale->id, [
            'propriedade_id' => $property->id,
            'valor_venda' => 500000,
            'data_escritura' => '2026-11-13',
        ])->assertOk();
        $this->assertSame('2026-11-13', $sale->refresh()->data_escritura->toDateString());
    }

    public function test_final_sale_status_requires_its_corresponding_milestone_dates(): void
    {
        $property = $this->authenticate();
        $base = [
            'propriedade_id' => $property->id,
            'valor_venda' => 500000,
            'data_proposta' => '2026-11-10',
        ];

        $this->postJson('/api/vendas', array_merge($base, ['status' => 'fechada']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('data_fechamento');

        $this->postJson('/api/vendas', array_merge($base, [
            'status' => 'escriturada',
            'data_fechamento' => '2026-11-11',
        ]))->assertUnprocessable()->assertJsonValidationErrors('data_escritura');

        $this->assertDatabaseCount('vendas', 0);

        $saleId = $this->postJson('/api/vendas', array_merge($base, [
            'status' => 'fechada',
            'data_fechamento' => '2026-11-11',
        ]))->assertCreated()->json('data.id');

        $this->putJson('/api/vendas/'.$saleId, array_merge($base, [
            'status' => 'escriturada',
            'data_fechamento' => '2026-11-11',
        ]))->assertUnprocessable()->assertJsonValidationErrors('data_escritura');

        $this->putJson('/api/vendas/'.$saleId, array_merge($base, [
            'status' => 'escriturada',
            'data_fechamento' => '2026-11-11',
            'data_escritura' => '2026-11-12',
        ]))->assertOk();
    }

    public function test_project_completion_persists_and_clears_the_actual_end_date_with_status(): void
    {
        $property = $this->authenticate();
        $projectId = $this->postJson('/api/projetos', [
            'propriedade_id' => $property->id,
            'nome' => 'Projeto concluível',
            'data_inicio' => '2026-10-01',
        ])->assertCreated()->json('data.id');

        $base = [
            'propriedade_id' => $property->id,
            'nome' => 'Projeto concluível',
            'data_inicio' => '2026-10-01',
        ];

        $this->putJson('/api/projetos/'.$projectId, $base + [
            'status' => 'concluido',
            'data_termino_real' => '2026-10-17',
        ])->assertOk()->assertJsonPath('data.data_termino_real', '2026-10-17');

        $this->putJson('/api/projetos/'.$projectId, $base + ['status' => 'em_andamento'])
            ->assertOk()
            ->assertJsonPath('data.data_termino_real', null);

        $this->putJson('/api/projetos/'.$projectId, $base + [
            'status' => 'em_andamento',
            'data_termino_real' => '2026-10-17',
        ])->assertUnprocessable()->assertJsonValidationErrors('data_termino_real');

        $this->putJson('/api/projetos/'.$projectId, $base + [
            'status' => 'concluido',
            'data_termino_real' => '2026-09-30',
        ])->assertUnprocessable()->assertJsonValidationErrors('data_termino_real');
    }

    public function test_project_partial_update_compares_planned_dates_with_persisted_values(): void
    {
        $property = $this->authenticate();
        $projectId = $this->postJson('/api/projetos', [
            'propriedade_id' => $property->id,
            'nome' => 'Cronograma parcial',
            'data_inicio' => '2026-11-10',
            'data_termino_prevista' => '2026-11-20',
        ])->assertCreated()->json('data.id');
        $base = ['propriedade_id' => $property->id, 'nome' => 'Cronograma parcial'];

        $this->putJson('/api/projetos/'.$projectId, $base + ['data_termino_prevista' => '2026-11-09'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('data_termino_prevista');
        $this->putJson('/api/projetos/'.$projectId, $base + ['data_inicio' => '2026-11-21'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('data_termino_prevista');

        $this->assertSame('2026-11-10', Projeto::findOrFail($projectId)->data_inicio->toDateString());
        $this->assertSame('2026-11-20', Projeto::findOrFail($projectId)->data_termino_prevista->toDateString());
    }
}
