<?php

namespace Tests\Feature;

use App\Models\CategoriaDespesa;
use App\Models\Despesa;
use App\Models\Documento;
use App\Models\EventoCalendario;
use App\Models\Projeto;
use App\Models\ProjetoEtapa;
use App\Models\Propriedade;
use App\Models\Tarefa;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DespesaService;
use App\Services\EventoCalendarioService;
use App\Services\ProjetoService;
use App\Services\TarefaService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $slug): User
    {
        $tenant = Tenant::create(['nome' => $slug, 'slug' => $slug]);

        return User::factory()->create(['tenant_id' => $tenant->id]);
    }

    public function test_legacy_crud_and_cross_tenant_access(): void
    {
        $owner = $this->account('owner');
        $intruder = $this->account('intruder');
        $this->actingAs($owner, 'sanctum');
        $property = $this->postJson('/api/propriedades', ['apelido' => 'Audit house', 'valor_aquisicao' => 100000, 'endereco' => ['cidade' => 'Teste']])->assertCreated()->json('data');
        $project = $this->postJson('/api/projetos', ['propriedade_id' => $property['id'], 'nome' => 'Audit work'])->assertCreated()->json('data');
        $category = CategoriaDespesa::create(['nome' => 'Audit', 'tipo' => 'reforma']);
        $expensePayload = ['categoria_despesa_id' => $category->id, 'propriedade_id' => $property['id'], 'projeto_id' => $project['id'], 'descricao' => 'Audit', 'valor' => 200, 'data_despesa' => '2026-10-16'];
        $expense = $this->postJson('/api/despesas', $expensePayload)->assertCreated()->json('data');
        $salePayload = ['propriedade_id' => $property['id'], 'valor_venda' => 150000, 'status' => 'fechada', 'data_fechamento' => '2026-10-16'];
        $sale = $this->postJson('/api/vendas', $salePayload)->assertCreated()->json('data');
        $resources = ['propriedades' => [$property['id'], ['apelido' => 'Updated']], 'projetos' => [$project['id'], ['propriedade_id' => $property['id'], 'nome' => 'Updated']], 'despesas' => [$expense['id'], $expensePayload], 'vendas' => [$sale['id'], $salePayload]];
        foreach ($resources as $resource => [$id, $payload]) {
            $this->getJson("/api/$resource/$id")->assertOk();
            $this->putJson("/api/$resource/$id", $payload)->assertOk();
        }
        $this->actingAs($intruder, 'sanctum');
        foreach ($resources as $resource => [$id, $payload]) {
            $this->getJson("/api/$resource")->assertOk()->assertJsonCount(0, 'data');
            $this->getJson("/api/$resource/$id")->assertNotFound();
            $this->deleteJson("/api/$resource/$id")->assertNotFound();
        }
        $this->getJson("/api/propriedades/{$property['id']}/roi")->assertNotFound();
        $this->getJson("/api/projetos/{$project['id']}/resumo")->assertNotFound();
        $this->postJson('/api/projetos', ['propriedade_id' => $property['id'], 'nome' => 'Invalid'])->assertUnprocessable();
        $this->actingAs($owner, 'sanctum');
        foreach (array_reverse($resources, true) as $resource => [$id]) {
            $this->deleteJson("/api/$resource/$id")->assertOk();
            $this->getJson("/api/$resource/$id")->assertNotFound();
        }
    }

    public function test_expense_cannot_mix_projects_and_stages(): void
    {
        $this->actingAs($this->account('owner'), 'sanctum');
        $property = Propriedade::create(['apelido' => 'Audit']);
        $a = Projeto::create(['propriedade_id' => $property->id, 'nome' => 'A']);
        $b = Projeto::create(['propriedade_id' => $property->id, 'nome' => 'B']);
        $stage = ProjetoEtapa::create(['projeto_id' => $b->id, 'nome' => 'Stage']);
        $category = CategoriaDespesa::create(['nome' => 'Audit', 'tipo' => 'reforma']);
        $this->postJson('/api/despesas', [
            'categoria_despesa_id' => $category->id, 'propriedade_id' => $property->id,
            'projeto_id' => $a->id, 'etapa_id' => $stage->id,
            'descricao' => 'Mismatch', 'valor' => 100, 'data_despesa' => '2026-10-16',
        ])->assertUnprocessable();
    }

    public function test_cancelled_expense_does_not_inflate_project_cost_or_property_roi(): void
    {
        $this->actingAs($this->account('owner'), 'sanctum');
        $property = Propriedade::create(['apelido' => 'Audit', 'valor_aquisicao' => 100000]);
        $project = Projeto::create(['propriedade_id' => $property->id, 'nome' => 'Audit']);
        $category = CategoriaDespesa::create(['nome' => 'Audit', 'tipo' => 'reforma']);
        $payload = ['categoria_despesa_id' => $category->id, 'propriedade_id' => $property->id, 'projeto_id' => $project->id, 'descricao' => 'Audit', 'valor' => 500, 'data_despesa' => '2026-10-16', 'status_pagamento' => 'pago'];
        $id = $this->postJson('/api/despesas', $payload)->assertCreated()->json('data.id');
        $this->getJson("/api/projetos/{$project->id}/resumo")->assertOk()->assertJsonPath('custo_real', 500);
        $this->putJson("/api/despesas/$id", array_replace($payload, ['status_pagamento' => 'cancelado']))->assertOk();
        $this->getJson("/api/projetos/{$project->id}/resumo")->assertOk()->assertJsonPath('custo_real', 0);
        $this->getJson("/api/propriedades/{$property->id}/roi")->assertOk()->assertJsonPath('total_despesas', 0);

        $this->putJson("/api/despesas/$id", $payload)->assertOk();
        $this->getJson("/api/projetos/{$project->id}/resumo")->assertOk()->assertJsonPath('custo_real', 500);
        $this->deleteJson("/api/despesas/$id")->assertOk();
        $this->getJson("/api/projetos/{$project->id}/resumo")->assertOk()->assertJsonPath('custo_real', 0);
        $this->getJson("/api/propriedades/{$property->id}/roi")->assertOk()->assertJsonPath('total_despesas', 0);
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('kpis.total_invested', 100000);
    }

    public function test_cancelled_or_deleted_sale_does_not_leave_property_sold(): void
    {
        $this->actingAs($this->account('owner'), 'sanctum');
        $property = Propriedade::create(['apelido' => 'Audit', 'status' => 'a_venda']);
        $payload = ['propriedade_id' => $property->id, 'valor_venda' => 100000, 'status' => 'fechada', 'data_fechamento' => '2026-10-16'];
        $id = $this->postJson('/api/vendas', $payload)->assertCreated()->json('data.id');
        $this->assertSame('vendida', $property->refresh()->status);
        $this->putJson("/api/vendas/$id", array_replace($payload, ['status' => 'cancelada']))->assertOk();
        $this->assertSame('a_venda', $property->refresh()->status);
        $this->putJson("/api/vendas/$id", $payload)->assertOk();
        $this->assertSame('vendida', $property->refresh()->status);
        $this->deleteJson("/api/vendas/$id")->assertOk();
        $this->assertSame('a_venda', $property->refresh()->status);
    }

    public function test_moving_the_only_final_sale_updates_both_properties(): void
    {
        $this->actingAs($this->account('owner'), 'sanctum');
        $a = Propriedade::create(['apelido' => 'A', 'status' => 'a_venda']);
        $b = Propriedade::create(['apelido' => 'B', 'status' => 'a_venda']);
        $payload = ['propriedade_id' => $a->id, 'valor_venda' => 100000, 'status' => 'fechada', 'data_fechamento' => '2026-10-16'];
        $id = $this->postJson('/api/vendas', $payload)->assertCreated()->json('data.id');
        $this->postJson('/api/vendas', $payload)->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->putJson("/api/vendas/$id", array_replace($payload, ['propriedade_id' => $b->id]))->assertOk();
        $this->assertSame('a_venda', $a->refresh()->status);
        $this->assertSame('vendida', $b->refresh()->status);
        $this->assertDatabaseCount('vendas', 1);
    }

    public function test_multiple_open_proposals_are_allowed_but_only_one_can_be_finalized(): void
    {
        $this->actingAs($this->account('sale-finalization'), 'sanctum');
        $property = Propriedade::create(['apelido' => 'Venda única', 'status' => 'a_venda']);
        $payload = ['propriedade_id' => $property->id, 'valor_venda' => 100000, 'status' => 'proposta'];
        $first = $this->postJson('/api/vendas', $payload)->assertCreated()->json('data.id');
        $second = $this->postJson('/api/vendas', $payload)->assertCreated()->json('data.id');

        $this->putJson("/api/vendas/$first", array_replace($payload, ['status' => 'fechada', 'data_fechamento' => '2026-10-16']))->assertOk();
        $this->putJson("/api/vendas/$second", array_replace($payload, ['status' => 'escriturada', 'data_fechamento' => '2026-10-16', 'data_escritura' => '2026-10-17']))
            ->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertDatabaseHas('vendas', ['id' => $first, 'status' => 'fechada']);
        $this->assertDatabaseHas('vendas', ['id' => $second, 'status' => 'proposta']);
        $this->assertSame('vendida', $property->refresh()->status);
    }

    public function test_project_summary_ignores_proposed_and_cancelled_sales_until_finalization(): void
    {
        $this->actingAs($this->account('project-sale-summary'), 'sanctum');
        $property = Propriedade::create(['apelido' => 'Resumo de venda', 'valor_aquisicao' => 100000]);
        $project = Projeto::create(['propriedade_id' => $property->id, 'nome' => 'Projeto com proposta']);
        $payload = ['propriedade_id' => $property->id, 'valor_venda' => 160000, 'status' => 'proposta'];
        $proposal = $this->postJson('/api/vendas', $payload)->assertCreated()->json('data.id');
        $this->postJson('/api/vendas', array_replace($payload, ['status' => 'cancelada', 'valor_venda' => 999999]))->assertCreated();

        $this->getJson("/api/projetos/{$project->id}/resumo")
            ->assertOk()
            ->assertJsonPath('roi.tem_venda', false)
            ->assertJsonPath('roi.valor_liquido_venda', 0);

        $this->putJson("/api/vendas/$proposal", array_replace($payload, ['status' => 'fechada', 'data_fechamento' => '2026-10-16']))->assertOk();
        $this->getJson("/api/projetos/{$project->id}/resumo")
            ->assertOk()
            ->assertJsonPath('roi.tem_venda', true)
            ->assertJsonPath('roi.valor_liquido_venda', 160000);
    }

    public function test_property_sold_status_cannot_diverge_from_its_final_sale(): void
    {
        $this->actingAs($this->account('property-sale-status'), 'sanctum');

        $this->postJson('/api/propriedades', ['apelido' => 'Venda inexistente', 'status' => 'vendida'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $propertyId = $this->postJson('/api/propriedades', ['apelido' => 'Venda real', 'status' => 'a_venda'])
            ->assertCreated()->json('data.id');
        $this->postJson('/api/vendas', [
            'propriedade_id' => $propertyId,
            'valor_venda' => 150000,
            'status' => 'fechada',
            'data_fechamento' => '2026-10-17',
        ])->assertCreated();

        $this->putJson('/api/propriedades/'.$propertyId, ['apelido' => 'Venda real', 'status' => 'a_venda'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
        $this->assertSame('vendida', Propriedade::findOrFail($propertyId)->status);
        $this->putJson('/api/propriedades/'.$propertyId, ['apelido' => 'Venda real editada', 'status' => 'vendida'])
            ->assertOk()
            ->assertJsonPath('data.apelido', 'Venda real editada');

        $otherId = $this->postJson('/api/propriedades', ['apelido' => 'Sem venda', 'status' => 'a_venda'])
            ->assertCreated()->json('data.id');
        $this->putJson('/api/propriedades/'.$otherId, ['apelido' => 'Sem venda', 'status' => 'vendida'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
        $this->assertSame('a_venda', Propriedade::findOrFail($otherId)->status);
    }

    public function test_sale_patch_updates_only_sent_fields(): void
    {
        $this->actingAs($this->account('sale-partial-update'), 'sanctum');
        $property = Propriedade::create(['apelido' => 'Venda parcial']);
        $saleId = $this->postJson('/api/vendas', [
            'propriedade_id' => $property->id,
            'valor_venda' => 250000,
            'status' => 'proposta',
            'comprador_nome' => 'Comprador preservado',
        ])->assertCreated()->json('data.id');

        $this->patchJson('/api/vendas/'.$saleId, ['observacoes' => 'Somente observação alterada'])
            ->assertOk()
            ->assertJsonPath('data.observacoes', 'Somente observação alterada')
            ->assertJsonPath('data.valor_venda', '250000.00')
            ->assertJsonPath('data.propriedade_id', $property->id)
            ->assertJsonPath('data.status', 'proposta')
            ->assertJsonPath('data.comprador_nome', 'Comprador preservado');

        $this->putJson('/api/vendas/'.$saleId, ['observacoes' => 'PUT incompleto'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['propriedade_id', 'valor_venda']);
    }

    public function test_deleted_project_rejects_stage_creation_and_mutation(): void
    {
        $this->actingAs($this->account('owner'), 'sanctum');
        $property = Propriedade::create(['apelido' => 'Audit']);
        $project = Projeto::create(['propriedade_id' => $property->id, 'nome' => 'Audit']);
        $stage = ProjetoEtapa::create(['projeto_id' => $project->id, 'nome' => 'Audit']);
        $this->deleteJson("/api/projetos/{$project->id}")->assertOk();
        $this->postJson("/api/projetos/{$project->id}/etapas", ['nome' => 'Invalid'])->assertNotFound();
        $this->patchJson("/api/projetos-etapas/{$stage->id}", ['orcamento' => 100])->assertNotFound();
        $this->assertDatabaseMissing('projetos_etapas', ['nome' => 'Invalid']);
    }

    public function test_deleting_project_hides_its_active_stages_tasks_and_calendar_events(): void
    {
        $user = $this->account('project-dependent-records');
        $this->actingAs($user, 'sanctum');
        $property = Propriedade::create(['apelido' => 'Audit']);
        $project = Projeto::create(['propriedade_id' => $property->id, 'nome' => 'Audit']);
        $stage = ProjetoEtapa::create(['projeto_id' => $project->id, 'nome' => 'Stage']);
        $task = Tarefa::create([
            'projeto_id' => $project->id,
            'etapa_id' => $stage->id,
            'titulo' => 'Task',
            'prioridade' => 'media',
            'status' => 'pendente',
        ]);
        $event = EventoCalendario::create([
            'projeto_id' => $project->id,
            'criado_por' => $user->id,
            'titulo' => 'Event',
            'categoria' => 'obra',
            'inicio_em' => '2026-10-17 10:00:00',
        ]);

        $this->deleteJson("/api/projetos/{$project->id}")->assertOk();

        $this->getJson('/api/tarefas')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/eventos-calendario?data_inicio=2026-10-01&data_fim=2026-10-31')->assertOk()->assertJsonCount(0, 'data');
        $this->assertDatabaseHas('projetos_etapas', ['id' => $stage->id, 'deletado_flg' => true]);
        $this->assertDatabaseHas('tarefas', ['id' => $task->id, 'deletado_flg' => true]);
        $this->assertDatabaseHas('eventos_calendario', ['id' => $event->id, 'deletado_flg' => true]);
    }

    public function test_failed_project_delete_rolls_back_all_dependent_records(): void
    {
        $user = $this->account('project-delete-rollback');
        $this->actingAs($user, 'sanctum');
        $property = Propriedade::create(['apelido' => 'Audit']);
        $project = Projeto::create(['propriedade_id' => $property->id, 'nome' => 'Audit']);
        $stage = ProjetoEtapa::create(['projeto_id' => $project->id, 'nome' => 'Stage']);
        $task = Tarefa::create([
            'projeto_id' => $project->id,
            'etapa_id' => $stage->id,
            'titulo' => 'Task',
            'prioridade' => 'media',
            'status' => 'pendente',
        ]);
        $event = EventoCalendario::create([
            'projeto_id' => $project->id,
            'criado_por' => $user->id,
            'titulo' => 'Event',
            'categoria' => 'obra',
            'inicio_em' => '2026-10-17 10:00:00',
        ]);
        $modelEvent = 'eloquent.updating: '.Projeto::class;
        app('events')->listen($modelEvent, fn () => throw new \RuntimeException('simulated project delete failure'));

        try {
            $this->deleteJson("/api/projetos/{$project->id}")->assertStatus(500);
            $this->assertDatabaseHas('projetos', ['id' => $project->id, 'deletado_flg' => false]);
            $this->assertDatabaseHas('projetos_etapas', ['id' => $stage->id, 'deletado_flg' => false]);
            $this->assertDatabaseHas('tarefas', ['id' => $task->id, 'deletado_flg' => false]);
            $this->assertDatabaseHas('eventos_calendario', ['id' => $event->id, 'deletado_flg' => false]);
        } finally {
            app('events')->forget($modelEvent);
        }
    }

    public function test_project_with_active_financial_or_document_records_cannot_be_deleted(): void
    {
        $user = $this->account('project-protected-history');
        $this->actingAs($user, 'sanctum');
        $property = Propriedade::create(['apelido' => 'Audit']);
        $category = CategoriaDespesa::create(['nome' => 'Audit', 'tipo' => 'outros']);
        $expenseProject = Projeto::create(['propriedade_id' => $property->id, 'nome' => 'Expense history']);
        $documentProject = Projeto::create(['propriedade_id' => $property->id, 'nome' => 'Document history']);
        $expense = Despesa::create([
            'propriedade_id' => $property->id,
            'projeto_id' => $expenseProject->id,
            'categoria_despesa_id' => $category->id,
            'descricao' => 'Historical expense',
            'valor' => 100,
            'data_despesa' => '2026-10-17',
        ]);
        $document = Documento::create([
            'projeto_id' => $documentProject->id,
            'enviado_por' => $user->id,
            'nome_original' => 'history.pdf',
            'caminho' => 'documents/history.pdf',
            'mime_type' => 'application/pdf',
            'tamanho_bytes' => 10,
            'categoria' => 'contrato',
        ]);

        foreach ([$expenseProject, $documentProject] as $project) {
            $this->deleteJson("/api/projetos/{$project->id}")
                ->assertStatus(409)
                ->assertExactJson(['message' => 'O projeto possui registros financeiros ou documentos ativos e não pode ser excluído.']);
            $this->assertDatabaseHas('projetos', ['id' => $project->id, 'deletado_flg' => false]);
        }

        $expense->update(['deletado_flg' => true]);
        $document->update(['deletado_flg' => true]);
        $this->deleteJson("/api/projetos/{$expenseProject->id}")->assertOk();
        $this->deleteJson("/api/projetos/{$documentProject->id}")->assertOk();
    }

    public function test_services_recheck_locked_parents_after_request_validation(): void
    {
        $this->actingAs($this->account('parent-lock-recheck'), 'sanctum');
        $deletedProperty = Propriedade::create(['apelido' => 'Deleted property']);
        $deletedProperty->forceFill(['deletado_flg' => true])->save();

        try {
            app(ProjetoService::class)->criar([
                'propriedade_id' => $deletedProperty->id,
                'nome' => 'Must not be created',
            ]);
            $this->fail('A criação deveria revalidar a propriedade sob lock.');
        } catch (ModelNotFoundException) {
            $this->assertDatabaseMissing('projetos', ['nome' => 'Must not be created']);
        }

        $property = Propriedade::create(['apelido' => 'Active property']);
        $deletedProject = Projeto::create(['propriedade_id' => $property->id, 'nome' => 'Deleted project']);
        $deletedProject->update(['deletado_flg' => true]);
        $category = CategoriaDespesa::create(['nome' => 'Audit', 'tipo' => 'outros']);

        try {
            app(DespesaService::class)->criar([
                'propriedade_id' => $property->id,
                'projeto_id' => $deletedProject->id,
                'categoria_despesa_id' => $category->id,
                'descricao' => 'Must not be created',
                'valor' => 100,
                'data_despesa' => '2026-10-17',
            ]);
            $this->fail('A criação deveria revalidar o projeto sob lock.');
        } catch (ModelNotFoundException) {
            $this->assertDatabaseMissing('despesas', ['descricao' => 'Must not be created']);
        }

        $source = Propriedade::create(['apelido' => 'Project source']);
        $deletedDestination = Propriedade::create(['apelido' => 'Deleted destination']);
        $movingProject = Projeto::create(['propriedade_id' => $source->id, 'nome' => 'Must not move']);
        $deletedDestination->forceFill(['deletado_flg' => true])->save();

        try {
            app(ProjetoService::class)->atualizar($movingProject, [
                'propriedade_id' => $deletedDestination->id,
                'nome' => 'Must not move',
            ]);
            $this->fail('A atualização deveria revalidar o imóvel de destino sob lock.');
        } catch (ModelNotFoundException) {
            $this->assertSame($source->id, $movingProject->refresh()->propriedade_id);
        }
    }

    public function test_operational_services_recheck_project_after_request_validation(): void
    {
        $user = $this->account('operational-parent-recheck');
        $this->actingAs($user, 'sanctum');
        $property = Propriedade::create(['apelido' => 'Active property']);
        $project = Projeto::create(['propriedade_id' => $property->id, 'nome' => 'Deleted project']);
        $project->update(['deletado_flg' => true]);

        try {
            app(TarefaService::class)->criar([
                'projeto_id' => $project->id,
                'titulo' => 'Must not be created',
                'prioridade' => 'media',
                'status' => 'pendente',
            ]);
            $this->fail('A tarefa deveria revalidar o projeto sob lock.');
        } catch (ModelNotFoundException) {
            $this->assertDatabaseMissing('tarefas', ['titulo' => 'Must not be created']);
        }

        try {
            app(EventoCalendarioService::class)->criar([
                'projeto_id' => $project->id,
                'titulo' => 'Must not be created',
                'categoria' => 'obra',
                'inicio_em' => '2026-10-17 10:00:00',
            ], $user);
            $this->fail('O evento deveria revalidar o projeto sob lock.');
        } catch (ModelNotFoundException) {
            $this->assertDatabaseMissing('eventos_calendario', ['titulo' => 'Must not be created']);
        }
    }

    public function test_stage_status_and_progress_match_supported_states_and_integer_storage(): void
    {
        $this->actingAs($this->account('owner'), 'sanctum');
        $property = Propriedade::create(['apelido' => 'Audit']);
        $project = Projeto::create(['propriedade_id' => $property->id, 'nome' => 'Audit']);
        $stage = ProjetoEtapa::create(['projeto_id' => $project->id, 'nome' => 'Audit']);
        $this->postJson("/api/projetos/{$project->id}/etapas", ['nome' => 'Invalid', 'status' => 'unrecognized'])->assertUnprocessable();
        $this->patchJson("/api/projetos-etapas/{$stage->id}", ['status' => 'unrecognized'])->assertUnprocessable();
        $this->patchJson("/api/projetos-etapas/{$stage->id}", ['progresso_pct' => 99.9])->assertUnprocessable();
        $this->patchJson("/api/projetos-etapas/{$stage->id}", ['status' => 'em_andamento', 'progresso_pct' => 50])->assertOk();
    }

    public function test_stage_completion_status_and_progress_cannot_diverge(): void
    {
        $this->actingAs($this->account('stage-completion'), 'sanctum');
        $property = Propriedade::create(['apelido' => 'Audit']);
        $project = Projeto::create(['propriedade_id' => $property->id, 'nome' => 'Audit']);

        $created = $this->postJson("/api/projetos/{$project->id}/etapas", [
            'nome' => 'Concluída na criação',
            'status' => 'concluida',
        ])->assertCreated();
        $created->assertJsonPath('status', 'concluida')->assertJsonPath('progresso_pct', 100);
        $stageId = $created->json('id');

        $this->patchJson("/api/projetos-etapas/{$stageId}", [
            'status' => 'concluida',
            'progresso_pct' => 50,
        ])->assertUnprocessable()->assertJsonValidationErrors('progresso_pct');

        $this->patchJson("/api/projetos-etapas/{$stageId}", ['status' => 'em_andamento'])
            ->assertOk()
            ->assertJsonPath('status', 'em_andamento')
            ->assertJsonPath('progresso_pct', 0);

        $this->patchJson("/api/projetos-etapas/{$stageId}", ['progresso_pct' => 100])
            ->assertOk()
            ->assertJsonPath('status', 'concluida')
            ->assertJsonPath('progresso_pct', 100);

        $this->patchJson("/api/projetos-etapas/{$stageId}", [
            'status' => 'bloqueada',
            'progresso_pct' => 100,
        ])->assertUnprocessable()->assertJsonValidationErrors('progresso_pct');

        $nested = $this->postJson('/api/projetos', [
            'propriedade_id' => $property->id,
            'nome' => 'Projeto com etapa concluída',
            'etapas' => [[
                'nome' => 'Etapa aninhada concluída',
                'status' => 'concluida',
            ]],
        ])->assertCreated();
        $nested->assertJsonPath('data.etapas.0.status', 'concluida')
            ->assertJsonPath('data.etapas.0.progresso_pct', 100);
    }

    public function test_stage_budgets_exclude_deleted_stages_and_roll_back_with_project_failure(): void
    {
        $this->actingAs($this->account('owner'), 'sanctum');
        $property = Propriedade::create(['apelido' => 'Audit']);
        $project = Projeto::create(['propriedade_id' => $property->id, 'nome' => 'Audit']);
        ProjetoEtapa::create(['projeto_id' => $project->id, 'nome' => 'Deleted', 'orcamento' => 100, 'deletado_flg' => true]);
        $id = $this->postJson("/api/projetos/{$project->id}/etapas", ['nome' => 'Active', 'orcamento' => 20])->assertCreated()->json('id');
        $this->assertSame('20.00', $project->refresh()->orcamento_total);
        $this->getJson("/api/projetos/{$project->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.etapas')
            ->assertJsonPath('data.etapas.0.id', $id);
        $this->getJson("/api/projetos/{$project->id}/resumo")
            ->assertOk()
            ->assertJsonCount(1, 'etapas')
            ->assertJsonPath('etapas.0.id', $id);
        $this->patchJson("/api/projetos-etapas/$id", ['orcamento' => 40])->assertOk();
        $this->assertSame('40.00', $project->refresh()->orcamento_total);
        $event = 'eloquent.updating: '.Projeto::class;
        app('events')->listen($event, fn () => throw new \RuntimeException('Audit simulated project failure'));
        try {
            $this->postJson("/api/projetos/{$project->id}/etapas", ['nome' => 'Rollback', 'orcamento' => 70])->assertStatus(500);
            $this->assertDatabaseMissing('projetos_etapas', ['nome' => 'Rollback']);
            $this->patchJson("/api/projetos-etapas/$id", ['orcamento' => 90])->assertStatus(500);
            $this->assertSame('40.00', ProjetoEtapa::findOrFail($id)->orcamento);
            $this->assertSame('40.00', $project->refresh()->orcamento_total);
        } finally {
            app('events')->forget($event);
        }
    }
}
