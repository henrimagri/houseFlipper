<?php

namespace Tests\Feature;

use App\Models\CategoriaDespesa;
use App\Models\Despesa;
use App\Models\Projeto;
use App\Models\Propriedade;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpensePaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_expenses_are_complete_and_stable_across_pages_and_tenant_scoped(): void
    {
        $tenant = Tenant::create(['nome' => 'Pagination QA', 'slug' => 'pagination-qa']);
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->actingAs($owner, 'sanctum');
        $property = Propriedade::create(['apelido' => 'QA property']);
        $project = Projeto::create(['propriedade_id' => $property->id, 'nome' => 'QA project']);
        $category = CategoriaDespesa::create(['nome' => 'QA category', 'tipo' => 'reforma']);
        $ids = [];
        for ($i = 0; $i < 205; $i++) {
            $ids[] = Despesa::create([
                'propriedade_id' => $property->id, 'projeto_id' => $project->id,
                'categoria_despesa_id' => $category->id, 'descricao' => "QA expense $i",
                'valor' => 1, 'data_despesa' => '2026-10-16', 'status_pagamento' => 'pago',
            ])->id;
        }
        $this->getJson("/api/despesas?projeto_id={$project->id}&per_page=200")->assertUnprocessable();
        $received = [];
        foreach ([1 => 100, 2 => 100, 3 => 5] as $page => $count) {
            $response = $this->getJson("/api/despesas?projeto_id={$project->id}&per_page=100&page=$page")
                ->assertOk()->assertJsonCount($count, 'data')->assertJsonPath('meta.last_page', 3);
            array_push($received, ...array_column($response->json('data'), 'id'));
        }
        $this->assertSame(array_reverse($ids), $received);
        $otherTenant = Tenant::create(['nome' => 'Other QA', 'slug' => 'other-qa']);
        $this->actingAs(User::factory()->create(['tenant_id' => $otherTenant->id]), 'sanctum');
        $this->getJson("/api/despesas?projeto_id={$project->id}&per_page=100")
            ->assertOk()->assertJsonCount(0, 'data');
    }
}
