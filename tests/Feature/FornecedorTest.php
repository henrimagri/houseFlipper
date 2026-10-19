<?php

namespace Tests\Feature;

use App\Models\Fornecedor;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FornecedorTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $slug, ?int $tenantId = null): User
    {
        $tenantId ??= Tenant::create(['nome' => $slug, 'slug' => $slug])->id;

        return User::factory()->create(['tenant_id' => $tenantId]);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'nome' => 'Fornecedor fictício',
            'categoria' => 'Materiais',
            'contato' => 'Contato QA',
            'telefone' => '(11) 99999-0000',
            'email' => 'fornecedor@example.test',
            'avaliacao' => 4.5,
            'observacoes' => 'Somente teste automatizado.',
            'ativo_flg' => true,
        ], $overrides);
    }

    public function test_owner_can_create_list_update_and_soft_delete_supplier(): void
    {
        $owner = $this->account('supplier-owner');
        $this->actingAs($owner, 'sanctum');

        $id = $this->postJson('/api/fornecedores', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.nome', 'Fornecedor fictício')
            ->assertJsonPath('data.avaliacao', '4.5')
            ->json('data.id');

        $this->getJson('/api/fornecedores?busca=Fornecedor&categoria=Materiais&status=ativo')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
        $this->getJson('/api/fornecedores/'.$id)->assertOk()->assertJsonMissingPath('data.tenant_id');
        $this->putJson('/api/fornecedores/'.$id, $this->payload([
            'nome' => 'Fornecedor atualizado', 'email' => null, 'telefone' => null, 'ativo_flg' => false,
        ]))->assertOk()->assertJsonPath('data.nome', 'Fornecedor atualizado')->assertJsonPath('data.email', null);
        $this->getJson('/api/fornecedores?status=inativo')->assertOk()->assertJsonCount(1, 'data');
        $this->deleteJson('/api/fornecedores/'.$id)->assertOk();
        $this->getJson('/api/fornecedores/'.$id)->assertNotFound();
        $this->assertDatabaseHas('fornecedores', ['id' => $id, 'tenant_id' => $owner->tenant_id, 'deletado_flg' => true]);
    }

    public function test_other_tenant_cannot_read_mutate_or_delete_supplier(): void
    {
        $owner = $this->account('supplier-a');
        $this->actingAs($owner, 'sanctum');
        $supplier = Fornecedor::create($this->payload());

        $this->actingAs($this->account('supplier-b'), 'sanctum');
        $this->getJson('/api/fornecedores/'.$supplier->id)->assertNotFound();
        $this->putJson('/api/fornecedores/'.$supplier->id, $this->payload(['nome' => 'Ataque']))->assertNotFound();
        $this->deleteJson('/api/fornecedores/'.$supplier->id)->assertNotFound();
        $this->getJson('/api/fornecedores')->assertOk()->assertJsonCount(0, 'data');

        $this->assertDatabaseHas('fornecedores', ['id' => $supplier->id, 'nome' => 'Fornecedor fictício', 'deletado_flg' => false]);
    }

    public function test_supplier_rejects_invalid_or_privileged_fields_and_tenantless_user(): void
    {
        $owner = $this->account('supplier-validation');
        $otherTenant = Tenant::create(['nome' => 'Other', 'slug' => 'supplier-other']);
        $this->actingAs($owner, 'sanctum');
        $this->postJson('/api/fornecedores', $this->payload([
            'email' => 'inválido', 'telefone' => 'abc', 'avaliacao' => 5.1,
        ]))->assertUnprocessable()->assertJsonValidationErrors(['email', 'telefone', 'avaliacao']);
        $this->assertDatabaseCount('fornecedores', 0);

        $id = $this->postJson('/api/fornecedores', $this->payload(['tenant_id' => $otherTenant->id, 'deletado_flg' => true]))
            ->assertCreated()->json('data.id');
        $this->assertDatabaseHas('fornecedores', ['id' => $id, 'tenant_id' => $owner->tenant_id, 'deletado_flg' => false]);

        $this->actingAs(User::factory()->create(['tenant_id' => null]), 'sanctum')
            ->postJson('/api/fornecedores', $this->payload())->assertForbidden();
    }

    public function test_supplier_pagination_and_filters_are_validated(): void
    {
        $this->actingAs($this->account('supplier-pagination'), 'sanctum');
        foreach (range(1, 26) as $index) {
            Fornecedor::create($this->payload(['nome' => sprintf('Fornecedor %02d', $index), 'email' => null]));
        }
        $this->getJson('/api/fornecedores?per_page=25&page=1')->assertOk()->assertJsonCount(25, 'data')->assertJsonPath('meta.last_page', 2);
        $this->getJson('/api/fornecedores?per_page=101')->assertUnprocessable();
        $this->getJson('/api/fornecedores?status=desconhecido')->assertUnprocessable();
    }

    public function test_supplier_patch_is_partial_and_put_remains_complete(): void
    {
        $this->actingAs($this->account('supplier-patch'), 'sanctum');
        $supplier = Fornecedor::create($this->payload());

        $this->patchJson('/api/fornecedores/'.$supplier->id, ['avaliacao' => 3.5])
            ->assertOk()
            ->assertJsonPath('data.avaliacao', '3.5')
            ->assertJsonPath('data.nome', 'Fornecedor fictício')
            ->assertJsonPath('data.categoria', 'Materiais');

        $supplier->refresh();
        $this->assertSame('Contato QA', $supplier->contato);
        $this->assertSame('fornecedor@example.test', $supplier->email);
        $this->assertTrue($supplier->ativo_flg);

        $this->putJson('/api/fornecedores/'.$supplier->id, ['avaliacao' => 2])
            ->assertUnprocessable()->assertJsonValidationErrors(['nome', 'categoria']);
    }
}
