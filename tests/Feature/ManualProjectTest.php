<?php

namespace Tests\Feature;

use App\Models\ProjetoEtapa;
use App\Models\Propriedade;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualProjectTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $slug): User
    {
        return User::factory()->create(['tenant_id' => Tenant::create(['nome' => $slug, 'slug' => $slug])->id]);
    }

    public function test_manual_project_payload_creates_default_stages_and_preserves_fields(): void
    {
        $owner = $this->account('manual-project-owner');
        $this->actingAs($owner, 'sanctum');
        $property = Propriedade::create(['apelido' => 'Imóvel fictício']);
        $response = $this->postJson('/api/projetos', [
            'propriedade_id' => $property->id,
            'nome' => 'Reforma QA',
            'descricao' => 'Projeto manual fictício',
            'orcamento_total' => 123.47,
            'data_inicio' => '2026-10-16',
            'data_termino_prevista' => '2026-12-16',
            'status' => 'em_andamento',
        ])->assertCreated()
            ->assertJsonPath('data.nome', 'Reforma QA')
            ->assertJsonPath('data.orcamento_total', '123.47')
            ->assertJsonPath('data.status', 'em_andamento')
            ->assertJsonPath('data.propriedade.id', $property->id)
            ->assertJsonCount(4, 'data.etapas');

        $project = $response->json('data');
        $this->assertDatabaseHas('projetos', ['id' => $project['id'], 'tenant_id' => $owner->tenant_id, 'descricao' => 'Projeto manual fictício']);
        $this->assertDatabaseCount('work_budgets', 4);
        $this->assertDatabaseCount('projetos_etapas', 4);
    }

    public function test_project_stage_responses_omit_tenant_and_internal_relation_fields(): void
    {
        $owner = $this->account('project-stage-public-contract');
        $this->actingAs($owner, 'sanctum');
        $property = Propriedade::create(['apelido' => 'Imóvel de contrato']);
        $project = $this->postJson('/api/projetos', [
            'propriedade_id' => $property->id,
            'nome' => 'Projeto de contrato',
        ])->assertCreated()
            ->assertJsonPath('data.etapas.0.nome', 'Demolição');

        foreach (['tenant_id', 'projeto_id', 'deletado_flg', 'data_inclusao', 'data_modificacao'] as $field) {
            $project->assertJsonMissingPath("data.etapas.0.{$field}");
        }

        $projectId = $project->json('data.id');
        $created = $this->postJson("/api/projetos/{$projectId}/etapas", [
            'nome' => 'Etapa adicional',
            'orcamento' => 5000,
        ])->assertCreated()
            ->assertJsonPath('nome', 'Etapa adicional');

        foreach (['tenant_id', 'projeto_id', 'deletado_flg', 'data_inclusao', 'data_modificacao'] as $field) {
            $created->assertJsonMissingPath($field);
        }

        $updated = $this->patchJson('/api/projetos-etapas/'.$created->json('id'), ['orcamento' => 6000])
            ->assertOk()
            ->assertJsonPath('orcamento', '6000.00');

        foreach (['tenant_id', 'projeto_id', 'deletado_flg', 'data_inclusao', 'data_modificacao'] as $field) {
            $updated->assertJsonMissingPath($field);
        }
    }

    public function test_manual_project_rejects_invalid_dates_and_another_tenants_property(): void
    {
        $owner = $this->account('manual-project-a');
        $this->actingAs($owner, 'sanctum');
        $property = Propriedade::create(['apelido' => 'Imóvel A']);
        $payload = ['propriedade_id' => $property->id, 'nome' => 'Projeto QA', 'data_inicio' => '2026-10-16', 'data_termino_prevista' => '2026-10-15'];
        $this->postJson('/api/projetos', $payload)->assertUnprocessable()->assertJsonValidationErrors('data_termino_prevista');
        $this->assertDatabaseCount('projetos', 0);

        $this->actingAs($this->account('manual-project-b'), 'sanctum');
        $this->postJson('/api/projetos', array_replace($payload, ['data_termino_prevista' => '2026-10-17']))
            ->assertUnprocessable()->assertJsonValidationErrors('propriedade_id');
        $this->assertDatabaseCount('projetos', 0);
    }

    public function test_project_update_rejects_nested_fields_that_the_endpoint_cannot_apply(): void
    {
        $owner = $this->account('manual-project-update-contract');
        $this->actingAs($owner, 'sanctum');
        $property = Propriedade::create(['apelido' => 'Imóvel de atualização']);
        $project = $this->postJson('/api/projetos', [
            'propriedade_id' => $property->id,
            'nome' => 'Projeto original',
            'orcamento_total' => 100,
        ])->assertCreated()->json('data');
        $stage = ProjetoEtapa::findOrFail($project['etapas'][0]['id']);

        $this->putJson('/api/projetos/'.$project['id'], [
            'propriedade_id' => $property->id,
            'nome' => 'Nome que não deve ser aplicado',
            'orcamento_total' => 200,
            'etapas' => [[
                'nome' => 'Etapa silenciosamente ignorada',
                'orcamento' => 999,
            ]],
            'orcamento_categorias' => ['demolicao' => 999],
        ])->assertUnprocessable()->assertJsonValidationErrors(['etapas', 'orcamento_categorias']);

        $this->assertDatabaseHas('projetos', [
            'id' => $project['id'],
            'nome' => 'Projeto original',
            'orcamento_total' => 100,
        ]);
        $this->assertDatabaseHas('projetos_etapas', [
            'id' => $stage->id,
            'nome' => $stage->nome,
            'orcamento' => $stage->orcamento,
        ]);
    }

    public function test_project_patch_updates_only_sent_fields(): void
    {
        $owner = $this->account('manual-project-partial-update');
        $this->actingAs($owner, 'sanctum');
        $property = Propriedade::create(['apelido' => 'Imóvel parcial']);
        $project = $this->postJson('/api/projetos', [
            'propriedade_id' => $property->id,
            'nome' => 'Nome original',
            'descricao' => 'Descrição preservada',
            'orcamento_total' => 123,
        ])->assertCreated()->json('data');

        $this->patchJson('/api/projetos/'.$project['id'], ['nome' => 'Nome parcial'])
            ->assertOk()
            ->assertJsonPath('data.nome', 'Nome parcial')
            ->assertJsonPath('data.descricao', 'Descrição preservada')
            ->assertJsonPath('data.propriedade.id', $property->id)
            ->assertJsonPath('data.orcamento_total', '123.00');
    }
}
