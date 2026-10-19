<?php

namespace Tests\Feature;

use App\Models\CategoriaDespesa;
use App\Models\Despesa;
use App\Models\Projeto;
use App\Models\Propriedade;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Venda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropriedadeTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $slug, ?int $tenantId = null): User
    {
        $tenantId ??= Tenant::create(['nome' => $slug, 'slug' => $slug])->id;

        return User::factory()->create(['tenant_id' => $tenantId]);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'apelido' => 'Imóvel fictício QA',
            'tipo' => 'apartamento',
            'area_m2' => 82.45,
            'quartos' => 2,
            'banheiros' => 2,
            'vagas' => 1,
            'valor_aquisicao' => 350000.25,
            'data_aquisicao' => '2026-10-01',
            'valor_venda_alvo' => 490000.75,
            'status' => 'prospeccao',
            'observacoes' => 'Registro descartável de teste.',
            'endereco' => ['cep' => '01001000', 'logradouro' => 'Praça da Sé', 'numero' => '1', 'bairro' => 'Sé', 'cidade' => 'São Paulo', 'estado' => 'SP', 'pais' => 'Brasil'],
        ], $overrides);
    }

    public function test_owner_can_create_list_update_and_soft_delete_property(): void
    {
        $owner = $this->account('property-owner');
        $this->actingAs($owner, 'sanctum');

        $id = $this->postJson('/api/propriedades', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.apelido', 'Imóvel fictício QA')
            ->assertJsonPath('data.endereco.cidade', 'São Paulo')
            ->json('data.id');

        $this->getJson('/api/propriedades?busca=Im%C3%B3vel&status=prospeccao&per_page=25')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
        $this->getJson('/api/propriedades/'.$id)->assertOk()->assertJsonMissingPath('data.tenant_id');

        $this->putJson('/api/propriedades/'.$id, $this->payload([
            'apelido' => 'Imóvel atualizado', 'status' => 'adquirida',
            'endereco' => ['cidade' => 'Campinas', 'estado' => 'SP', 'pais' => 'Brasil'],
        ]))->assertOk()->assertJsonPath('data.apelido', 'Imóvel atualizado')->assertJsonPath('data.endereco.cidade', 'Campinas');

        $this->deleteJson('/api/propriedades/'.$id)->assertOk();
        $this->getJson('/api/propriedades/'.$id)->assertNotFound();
        $this->assertDatabaseHas('propriedades', ['id' => $id, 'tenant_id' => $owner->tenant_id, 'deletado_flg' => true]);
    }

    public function test_other_tenant_cannot_read_mutate_delete_or_list_property(): void
    {
        $owner = $this->account('property-a');
        $this->actingAs($owner, 'sanctum');
        $property = Propriedade::create(['apelido' => 'Privado', 'status' => 'prospeccao']);

        $this->actingAs($this->account('property-b'), 'sanctum');
        $this->getJson('/api/propriedades/'.$property->id)->assertNotFound();
        $this->putJson('/api/propriedades/'.$property->id, $this->payload(['apelido' => 'Ataque']))->assertNotFound();
        $this->deleteJson('/api/propriedades/'.$property->id)->assertNotFound();
        $this->getJson('/api/propriedades')->assertOk()->assertJsonCount(0, 'data');
        $this->assertDatabaseHas('propriedades', ['id' => $property->id, 'apelido' => 'Privado', 'deletado_flg' => false]);
    }

    public function test_property_with_active_project_cannot_be_deleted(): void
    {
        $owner = $this->account('property-project');
        $this->actingAs($owner, 'sanctum');
        $property = Propriedade::create(['apelido' => 'Imóvel em uso', 'status' => 'adquirida']);
        $project = Projeto::create([
            'tenant_id' => $owner->tenant_id,
            'propriedade_id' => $property->id,
            'nome' => 'Projeto ativo',
            'status' => 'em_andamento',
        ]);

        $this->deleteJson('/api/propriedades/'.$property->id)
            ->assertStatus(409)
            ->assertExactJson(['message' => 'A propriedade possui registros ativos e não pode ser excluída.']);

        $this->assertDatabaseHas('propriedades', ['id' => $property->id, 'deletado_flg' => false]);
        $this->assertDatabaseHas('projetos', ['id' => $project->id, 'deletado_flg' => false]);

        $project->update(['deletado_flg' => true]);
        $this->deleteJson('/api/propriedades/'.$property->id)->assertOk();
        $this->assertDatabaseHas('propriedades', ['id' => $property->id, 'deletado_flg' => true]);
    }

    public function test_property_with_active_sale_or_expense_cannot_be_deleted(): void
    {
        $owner = $this->account('property-financial-records');
        $this->actingAs($owner, 'sanctum');
        $saleProperty = Propriedade::create(['apelido' => 'Imóvel vendido', 'status' => 'vendida']);
        $expenseProperty = Propriedade::create(['apelido' => 'Imóvel com despesa', 'status' => 'adquirida']);
        $sale = Venda::create([
            'tenant_id' => $owner->tenant_id,
            'propriedade_id' => $saleProperty->id,
            'valor_venda' => 500000,
            'status' => 'fechada',
        ]);
        $category = CategoriaDespesa::create([
            'tenant_id' => $owner->tenant_id,
            'nome' => 'Auditoria',
            'tipo' => 'outros',
        ]);
        $expense = Despesa::create([
            'tenant_id' => $owner->tenant_id,
            'propriedade_id' => $expenseProperty->id,
            'categoria_despesa_id' => $category->id,
            'descricao' => 'Despesa vinculada',
            'valor' => 100,
            'data_despesa' => '2026-10-17',
        ]);

        foreach ([$saleProperty, $expenseProperty] as $property) {
            $this->deleteJson('/api/propriedades/'.$property->id)
                ->assertStatus(409)
                ->assertExactJson(['message' => 'A propriedade possui registros ativos e não pode ser excluída.']);
            $this->assertDatabaseHas('propriedades', ['id' => $property->id, 'deletado_flg' => false]);
        }

        $sale->update(['deletado_flg' => true]);
        $expense->update(['deletado_flg' => true]);
        $this->deleteJson('/api/propriedades/'.$saleProperty->id)->assertOk();
        $this->deleteJson('/api/propriedades/'.$expenseProperty->id)->assertOk();
    }

    public function test_property_rejects_invalid_bounds_and_ignores_privileged_fields(): void
    {
        $owner = $this->account('property-validation');
        $other = Tenant::create(['nome' => 'Other', 'slug' => 'property-other']);
        $this->actingAs($owner, 'sanctum');

        $this->postJson('/api/propriedades', $this->payload([
            'area_m2' => -1, 'quartos' => 1001, 'banheiros' => -1, 'vagas' => 1001,
            'observacoes' => str_repeat('x', 5001), 'status' => 'inventado',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['area_m2', 'quartos', 'banheiros', 'vagas', 'observacoes', 'status']);

        $id = $this->postJson('/api/propriedades', $this->payload(['tenant_id' => $other->id, 'deletado_flg' => true]))
            ->assertCreated()->json('data.id');
        $this->assertDatabaseHas('propriedades', ['id' => $id, 'tenant_id' => $owner->tenant_id, 'deletado_flg' => false]);

        $this->actingAs(User::factory()->create(['tenant_id' => null]), 'sanctum')
            ->postJson('/api/propriedades', $this->payload())->assertForbidden();
    }

    public function test_property_filters_and_pagination_are_validated(): void
    {
        $this->actingAs($this->account('property-pagination'), 'sanctum');
        foreach (range(1, 26) as $index) {
            Propriedade::create(['apelido' => sprintf('Imóvel %02d', $index), 'status' => 'prospeccao']);
        }

        $this->getJson('/api/propriedades?per_page=25&page=1')->assertOk()->assertJsonCount(25, 'data')->assertJsonPath('meta.last_page', 2);
        $this->getJson('/api/propriedades?per_page=101')->assertUnprocessable();
        $this->getJson('/api/propriedades?status=desconhecido')->assertUnprocessable();
        $this->getJson('/api/propriedades?busca='.str_repeat('x', 121))->assertUnprocessable();
    }

    public function test_property_patch_updates_only_sent_fields(): void
    {
        $owner = $this->account('property-partial-update');
        $this->actingAs($owner, 'sanctum');
        $propertyId = $this->postJson('/api/propriedades', $this->payload())
            ->assertCreated()
            ->json('data.id');

        $this->patchJson('/api/propriedades/'.$propertyId, ['observacoes' => 'Somente observação alterada'])
            ->assertOk()
            ->assertJsonPath('data.observacoes', 'Somente observação alterada')
            ->assertJsonPath('data.apelido', 'Imóvel fictício QA')
            ->assertJsonPath('data.valor_aquisicao', '350000.25')
            ->assertJsonPath('data.endereco.cidade', 'São Paulo');

        $this->putJson('/api/propriedades/'.$propertyId, ['observacoes' => 'PUT incompleto'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('apelido');
    }
}
