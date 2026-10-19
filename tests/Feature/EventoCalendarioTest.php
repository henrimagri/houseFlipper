<?php

namespace Tests\Feature;

use App\Models\EventoCalendario;
use App\Models\Projeto;
use App\Models\Propriedade;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventoCalendarioTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $slug): User
    {
        $tenant = Tenant::create(['nome' => $slug, 'slug' => $slug]);

        return User::factory()->create(['tenant_id' => $tenant->id]);
    }

    private function project(User $user): Projeto
    {
        $property = Propriedade::create(['tenant_id' => $user->tenant_id, 'apelido' => 'Imóvel agenda']);

        return Projeto::create(['tenant_id' => $user->tenant_id, 'propriedade_id' => $property->id, 'nome' => 'Projeto agenda']);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'titulo' => 'Vistoria fictícia',
            'descricao' => 'Evento exclusivo do teste automatizado.',
            'categoria' => 'visita',
            'inicio_em' => '2026-10-20T09:00:00-03:00',
            'fim_em' => '2026-10-20T10:00:00-03:00',
            'dia_inteiro' => false,
            'local' => 'Imóvel QA',
            'endereco' => 'Rua de Teste, 123',
            'status' => 'agendado',
        ], $overrides);
    }

    public function test_owner_can_create_filter_update_and_soft_delete_event(): void
    {
        $owner = $this->account('calendar-owner');
        $project = $this->project($owner);
        $this->actingAs($owner, 'sanctum');

        $id = $this->postJson('/api/eventos-calendario', $this->payload(['projeto_id' => $project->id]))
            ->assertCreated()
            ->assertJsonPath('data.titulo', 'Vistoria fictícia')
            ->assertJsonPath('data.projeto.id', $project->id)
            ->assertJsonMissingPath('data.tenant_id')
            ->json('data.id');

        $this->getJson('/api/eventos-calendario?data_inicio=2026-10-01&data_fim=2026-10-31&categoria=visita&status=agendado&projeto_id='.$project->id)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
        $this->getJson('/api/eventos-calendario?data_inicio=2026-11-01&data_fim=2026-11-30')
            ->assertOk()->assertJsonCount(0, 'data');

        $this->putJson('/api/eventos-calendario/'.$id, $this->payload([
            'projeto_id' => null, 'titulo' => 'Vistoria concluída', 'status' => 'concluido', 'fim_em' => null,
        ]))->assertOk()->assertJsonPath('data.titulo', 'Vistoria concluída')->assertJsonPath('data.projeto_id', null);
        $this->getJson('/api/eventos-calendario/'.$id)->assertOk()->assertJsonPath('data.status', 'concluido');
        $this->deleteJson('/api/eventos-calendario/'.$id)->assertOk();
        $this->getJson('/api/eventos-calendario/'.$id)->assertNotFound();
        $this->assertDatabaseHas('eventos_calendario', ['id' => $id, 'deletado_flg' => true]);
    }

    public function test_other_tenant_cannot_discover_read_mutate_or_delete_event(): void
    {
        $owner = $this->account('calendar-a');
        $this->actingAs($owner, 'sanctum');
        $event = EventoCalendario::create($this->payload());

        $this->actingAs($this->account('calendar-b'), 'sanctum');
        $this->getJson('/api/eventos-calendario?data_inicio=2026-10-01&data_fim=2026-10-31')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/eventos-calendario/'.$event->id)->assertNotFound();
        $this->putJson('/api/eventos-calendario/'.$event->id, $this->payload(['titulo' => 'Ataque']))->assertNotFound();
        $this->deleteJson('/api/eventos-calendario/'.$event->id)->assertNotFound();
        $this->assertDatabaseHas('eventos_calendario', ['id' => $event->id, 'titulo' => 'Vistoria fictícia', 'deletado_flg' => false]);
    }

    public function test_event_rejects_invalid_range_foreign_project_and_privileged_fields(): void
    {
        $owner = $this->account('calendar-validation');
        $foreign = $this->account('calendar-foreign');
        $foreignProject = $this->project($foreign);
        $this->actingAs($owner, 'sanctum');

        $this->postJson('/api/eventos-calendario', $this->payload([
            'categoria' => 'injetada', 'status' => 'admin', 'fim_em' => '2026-10-19T10:00:00-03:00', 'projeto_id' => $foreignProject->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors(['categoria', 'status', 'fim_em', 'projeto_id']);

        $id = $this->postJson('/api/eventos-calendario', $this->payload([
            'tenant_id' => $foreign->tenant_id, 'criado_por' => $foreign->id, 'deletado_flg' => true,
        ]))->assertCreated()->json('data.id');
        $event = EventoCalendario::withoutGlobalScopes()->findOrFail($id);
        $this->assertSame($owner->tenant_id, $event->tenant_id);
        $this->assertSame($owner->id, $event->criado_por);
        $this->assertFalse($event->deletado_flg);

        $this->getJson('/api/eventos-calendario')->assertUnprocessable()->assertJsonValidationErrors(['data_inicio', 'data_fim']);
        $this->getJson('/api/eventos-calendario?data_inicio=2026-10-31&data_fim=2026-10-01')->assertUnprocessable()->assertJsonValidationErrors('data_fim');
        $this->getJson('/api/eventos-calendario?data_inicio=2026-10-01&data_fim=2026-10-31&categoria=desconhecida')->assertUnprocessable()->assertJsonValidationErrors('categoria');

        $this->actingAs(User::factory()->create(['tenant_id' => null]), 'sanctum')
            ->postJson('/api/eventos-calendario', $this->payload())->assertForbidden();
    }

    public function test_event_overlap_and_pagination_are_correct(): void
    {
        $owner = $this->account('calendar-range');
        $this->actingAs($owner, 'sanctum');
        foreach (range(1, 101) as $index) {
            EventoCalendario::create($this->payload([
                'titulo' => sprintf('Evento %03d', $index),
                'inicio_em' => '2026-10-'.sprintf('%02d', (($index - 1) % 28) + 1).' 09:00:00',
                'fim_em' => null,
            ]));
        }
        EventoCalendario::create($this->payload([
            'titulo' => 'Evento atravessando o mês',
            'inicio_em' => '2026-09-30 23:00:00',
            'fim_em' => '2026-10-01 02:00:00',
        ]));

        $this->getJson('/api/eventos-calendario?data_inicio=2026-10-01&data_fim=2026-10-31&per_page=100&page=1')
            ->assertOk()->assertJsonCount(100, 'data')->assertJsonPath('meta.last_page', 2);
        $this->getJson('/api/eventos-calendario?data_inicio=2026-10-01&data_fim=2026-10-31&per_page=100&page=2')
            ->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/eventos-calendario?data_inicio=2026-10-01&data_fim=2026-10-01')
            ->assertOk()->assertJsonFragment(['titulo' => 'Evento atravessando o mês']);
        $this->getJson('/api/eventos-calendario?data_inicio=2026-10-01&data_fim=2026-10-31&per_page=101')->assertUnprocessable();
    }

    public function test_event_patch_is_partial_and_validates_the_effective_date_range(): void
    {
        $owner = $this->account('calendar-patch');
        $this->actingAs($owner, 'sanctum');
        $event = EventoCalendario::create($this->payload());

        $this->patchJson('/api/eventos-calendario/'.$event->id, ['titulo' => 'Título alterado'])
            ->assertOk()
            ->assertJsonPath('data.titulo', 'Título alterado');
        $event->refresh();
        $this->assertSame('visita', $event->categoria);
        $this->assertSame('2026-10-20 09:00:00', $event->inicio_em->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-20 10:00:00', $event->fim_em->format('Y-m-d H:i:s'));

        $this->patchJson('/api/eventos-calendario/'.$event->id, ['fim_em' => '2026-10-20T08:59:59-03:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('fim_em');
        $this->patchJson('/api/eventos-calendario/'.$event->id, ['inicio_em' => '2026-10-20T10:00:01-03:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('fim_em');
        $this->assertSame('2026-10-20 09:00:00', $event->fresh()->inicio_em->format('Y-m-d H:i:s'));

        $this->putJson('/api/eventos-calendario/'.$event->id, ['titulo' => 'Incompleto'])
            ->assertUnprocessable()->assertJsonValidationErrors(['categoria', 'inicio_em']);
    }
}
