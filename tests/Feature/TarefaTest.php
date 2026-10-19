<?php

namespace Tests\Feature;

use App\Models\Projeto;
use App\Models\ProjetoEtapa;
use App\Models\Propriedade;
use App\Models\Tarefa;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TarefaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TarefaTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $slug, ?int $tenantId = null): User
    {
        $tenantId ??= Tenant::create(['nome' => $slug, 'slug' => $slug])->id;

        return User::factory()->create(['tenant_id' => $tenantId]);
    }

    private function project(User $user, string $name = 'Projeto QA'): Projeto
    {
        $property = Propriedade::create(['tenant_id' => $user->tenant_id, 'apelido' => 'Imóvel '.$name]);

        return Projeto::create(['tenant_id' => $user->tenant_id, 'propriedade_id' => $property->id, 'nome' => $name]);
    }

    private function payload(Projeto $project, array $overrides = []): array
    {
        return array_replace([
            'projeto_id' => $project->id,
            'etapa_id' => null,
            'responsavel_user_id' => null,
            'titulo' => 'Tarefa fictícia QA',
            'descricao' => 'Somente teste automatizado.',
            'data_prevista' => '2026-10-30',
            'data_conclusao' => null,
            'prioridade' => 'alta',
            'status' => 'pendente',
            'custo_estimado' => 1500.25,
            'custo_real' => null,
        ], $overrides);
    }

    public function test_owner_can_create_list_complete_reopen_and_soft_delete_task(): void
    {
        $owner = $this->account('task-owner');
        $project = $this->project($owner);
        $stage = ProjetoEtapa::create(['tenant_id' => $owner->tenant_id, 'projeto_id' => $project->id, 'nome' => 'Etapa QA']);
        $this->actingAs($owner, 'sanctum');

        $id = $this->postJson('/api/tarefas', $this->payload($project, ['etapa_id' => $stage->id, 'responsavel_user_id' => $owner->id]))
            ->assertCreated()->assertJsonPath('data.projeto.id', $project->id)->assertJsonPath('data.etapa.id', $stage->id)
            ->assertJsonPath('data.responsavel.id', $owner->id)->assertJsonPath('data.data_conclusao', null)->json('data.id');

        $this->getJson('/api/tarefas?projeto_id='.$project->id.'&prioridade=alta&status=pendente')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
        $this->getJson('/api/tarefas/'.$id)->assertOk()->assertJsonMissingPath('data.tenant_id')->assertJsonMissingPath('data.deletado_flg');

        $this->putJson('/api/tarefas/'.$id, $this->payload($project, ['etapa_id' => $stage->id, 'status' => 'concluida', 'data_conclusao' => '2026-10-10']))
            ->assertOk()->assertJsonPath('data.status', 'concluida')->assertJsonPath('data.data_conclusao', '2026-10-10');
        $completedEdit = $this->payload($project, ['etapa_id' => $stage->id, 'status' => 'concluida', 'titulo' => 'Tarefa editada após conclusão']);
        unset($completedEdit['data_conclusao']);
        $this->putJson('/api/tarefas/'.$id, $completedEdit)
            ->assertOk()->assertJsonPath('data.titulo', 'Tarefa editada após conclusão')->assertJsonPath('data.data_conclusao', '2026-10-10');
        $this->putJson('/api/tarefas/'.$id, $this->payload($project, ['etapa_id' => $stage->id, 'status' => 'em_andamento']))
            ->assertOk()->assertJsonPath('data.data_conclusao', null);

        $this->getJson('/api/projetos/'.$project->id)->assertOk()
            ->assertJsonPath('data.tarefas.0.id', $id)->assertJsonMissingPath('data.tarefas.0.tenant_id')->assertJsonMissingPath('data.tarefas.0.deletado_flg');

        $this->deleteJson('/api/tarefas/'.$id)->assertOk();
        $this->getJson('/api/tarefas/'.$id)->assertNotFound();
        $this->getJson('/api/projetos/'.$project->id)->assertOk()->assertJsonCount(0, 'data.tarefas');
        $this->assertDatabaseHas('tarefas', ['id' => $id, 'tenant_id' => $owner->tenant_id, 'deletado_flg' => true]);
    }

    public function test_other_tenant_cannot_read_mutate_delete_or_list_task(): void
    {
        $owner = $this->account('task-a');
        $project = $this->project($owner);
        $task = Tarefa::create(['tenant_id' => $owner->tenant_id, 'projeto_id' => $project->id, 'titulo' => 'Privada']);

        $intruder = $this->account('task-b');
        $intruderProject = $this->project($intruder, 'Projeto intruso');
        $this->actingAs($intruder, 'sanctum');
        $this->getJson('/api/tarefas/'.$task->id)->assertNotFound();
        $this->putJson('/api/tarefas/'.$task->id, $this->payload($intruderProject, ['titulo' => 'Ataque']))->assertNotFound();
        $this->deleteJson('/api/tarefas/'.$task->id)->assertNotFound();
        $this->getJson('/api/tarefas')->assertOk()->assertJsonCount(0, 'data');
        $this->assertDatabaseHas('tarefas', ['id' => $task->id, 'titulo' => 'Privada', 'deletado_flg' => false]);
    }

    public function test_task_rejects_foreign_relations_invalid_bounds_and_privileged_fields(): void
    {
        $owner = $this->account('task-validation');
        $project = $this->project($owner);
        $other = $this->account('task-validation-other');
        $otherProject = $this->project($other, 'Projeto alheio');
        $otherStage = ProjetoEtapa::create(['tenant_id' => $other->tenant_id, 'projeto_id' => $otherProject->id, 'nome' => 'Alheia']);
        $localOtherProject = $this->project($owner, 'Outro projeto local');
        $localOtherStage = ProjetoEtapa::create(['tenant_id' => $owner->tenant_id, 'projeto_id' => $localOtherProject->id, 'nome' => 'Etapa de outro projeto']);
        $this->actingAs($owner, 'sanctum');

        $this->postJson('/api/tarefas', $this->payload($project, [
            'projeto_id' => $otherProject->id, 'etapa_id' => $otherStage->id, 'responsavel_user_id' => $other->id,
            'titulo' => str_repeat('x', 201), 'descricao' => str_repeat('x', 5001), 'prioridade' => 'urgente',
            'status' => 'inventada', 'custo_estimado' => -1,
        ]))->assertUnprocessable()->assertJsonValidationErrors(['projeto_id', 'etapa_id', 'responsavel_user_id', 'titulo', 'descricao', 'prioridade', 'status', 'custo_estimado']);

        $this->postJson('/api/tarefas', $this->payload($project, ['etapa_id' => $localOtherStage->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('etapa_id');
        $this->postJson('/api/tarefas', $this->payload($project, ['data_conclusao' => '2026-10-10']))
            ->assertUnprocessable()->assertJsonValidationErrors('data_conclusao');

        $id = $this->postJson('/api/tarefas', $this->payload($project, ['tenant_id' => $other->tenant_id, 'deletado_flg' => true]))
            ->assertCreated()->json('data.id');
        $this->assertDatabaseHas('tarefas', ['id' => $id, 'tenant_id' => $owner->tenant_id, 'deletado_flg' => false]);

        $this->actingAs(User::factory()->create(['tenant_id' => null]), 'sanctum')
            ->postJson('/api/tarefas', $this->payload($project))->assertForbidden();
    }

    public function test_task_filters_and_pagination_are_validated(): void
    {
        $owner = $this->account('task-pagination');
        $project = $this->project($owner);
        $this->actingAs($owner, 'sanctum');
        foreach (range(1, 51) as $index) {
            Tarefa::create(['tenant_id' => $owner->tenant_id, 'projeto_id' => $project->id, 'titulo' => sprintf('Tarefa %02d', $index), 'prioridade' => $index === 1 ? 'critica' : 'media', 'status' => 'pendente', 'data_prevista' => '2026-10-30']);
        }

        $this->getJson('/api/tarefas?per_page=50&page=1')->assertOk()->assertJsonCount(50, 'data')->assertJsonPath('meta.last_page', 2);
        $this->getJson('/api/tarefas?busca=Tarefa%2001&prioridade=critica')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/tarefas?per_page=101')->assertUnprocessable();
        $this->getJson('/api/tarefas?status=desconhecido')->assertUnprocessable();
        $this->getJson('/api/tarefas?data_inicio=2026-11-01&data_fim=2026-10-01')->assertUnprocessable();
    }

    public function test_task_patch_is_partial_and_validates_effective_project_stage(): void
    {
        $owner = $this->account('task-partial');
        $project = $this->project($owner);
        $otherProject = $this->project($owner, 'Outro projeto');
        $stage = ProjetoEtapa::create(['tenant_id' => $owner->tenant_id, 'projeto_id' => $project->id, 'nome' => 'Etapa original']);
        $otherStage = ProjetoEtapa::create(['tenant_id' => $owner->tenant_id, 'projeto_id' => $otherProject->id, 'nome' => 'Etapa externa']);
        $task = Tarefa::create([
            'tenant_id' => $owner->tenant_id,
            'projeto_id' => $project->id,
            'etapa_id' => $stage->id,
            'titulo' => 'Tarefa concluída',
            'prioridade' => 'alta',
            'status' => 'concluida',
            'data_conclusao' => '2026-10-10',
        ]);
        $this->actingAs($owner, 'sanctum');

        $this->patchJson('/api/tarefas/'.$task->id, ['titulo' => 'Título parcial'])
            ->assertOk()
            ->assertJsonPath('data.titulo', 'Título parcial')
            ->assertJsonPath('data.status', 'concluida')
            ->assertJsonPath('data.data_conclusao', '2026-10-10')
            ->assertJsonPath('data.projeto.id', $project->id)
            ->assertJsonPath('data.etapa.id', $stage->id);

        $this->patchJson('/api/tarefas/'.$task->id, ['etapa_id' => $otherStage->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('etapa_id');
        $this->assertSame($stage->id, $task->refresh()->etapa_id);

        $this->putJson('/api/tarefas/'.$task->id, ['titulo' => 'PUT incompleto'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['projeto_id', 'prioridade', 'status']);
    }

    public function test_task_update_derives_completion_from_the_fresh_locked_row(): void
    {
        $owner = $this->account('task-stale-update');
        $project = $this->project($owner);
        $task = Tarefa::create([
            'tenant_id' => $owner->tenant_id,
            'projeto_id' => $project->id,
            'titulo' => 'Tarefa carregada antes da concorrência',
            'prioridade' => 'media',
            'status' => 'pendente',
        ]);
        $this->actingAs($owner, 'sanctum');

        $staleTask = Tarefa::findOrFail($task->id);
        Tarefa::whereKey($task->id)->update([
            'status' => 'concluida',
            'data_conclusao' => '2026-10-19',
        ]);

        app(TarefaService::class)->atualizar($staleTask, ['titulo' => 'Título concorrente preservado']);

        $this->assertDatabaseHas('tarefas', [
            'id' => $task->id,
            'titulo' => 'Título concorrente preservado',
            'status' => 'concluida',
            'data_conclusao' => '2026-10-19',
        ]);
    }
}
