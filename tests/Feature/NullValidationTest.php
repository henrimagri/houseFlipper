<?php

namespace Tests\Feature;

use App\Models\CategoriaDespesa;
use App\Models\Opportunity;
use App\Models\Propriedade;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NullValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_null_for_nonnullable_fields_is_validation_error(): void
    {
        $tenant = Tenant::create(['nome' => 'Audit', 'slug' => 'audit']);
        $this->actingAs(User::factory()->create(['tenant_id' => $tenant->id]), 'sanctum');
        $property = Propriedade::create(['apelido' => 'Audit']);
        $category = CategoriaDespesa::create(['nome' => 'Audit']);
        $cases = [
            ['/api/propriedades', ['apelido' => 'Audit', 'status' => null]],
            ['/api/propriedades', ['apelido' => 'Audit', 'endereco' => ['cidade' => 'Teste', 'pais' => null]]],
            ['/api/projetos', ['nome' => 'Audit', 'propriedade_id' => $property->id, 'status' => null]],
            ['/api/projetos', ['nome' => 'Audit', 'propriedade_id' => $property->id, 'orcamento_total' => null]],
            ['/api/projetos', ['nome' => 'Audit', 'propriedade_id' => $property->id, 'etapas' => [['nome' => 'Audit', 'status' => null]]]],
            ['/api/despesas', ['categoria_despesa_id' => $category->id, 'descricao' => 'Audit', 'valor' => 1, 'data_despesa' => '2026-10-16', 'status_pagamento' => null]],
            ['/api/vendas', ['propriedade_id' => $property->id, 'valor_venda' => 1, 'status' => null]],
            ['/api/vendas', ['propriedade_id' => $property->id, 'valor_venda' => 1, 'valor_comissao' => null]],
            ['/api/opportunities', ['title' => 'Audit', 'property_type' => 'house', 'source' => 'manual', 'status' => null]],
        ];
        foreach ($cases as [$uri, $payload]) {
            $this->postJson($uri, $payload)->assertUnprocessable();
        }
    }

    public function test_empty_optional_sale_cost_is_normalized_to_zero(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
        $opportunity = Opportunity::create(['user_id' => $user->id, 'title' => 'Audit', 'property_type' => 'house', 'source' => 'manual']);
        $id = $this->postJson("/api/opportunities/{$opportunity->id}/investment-analysis", ['purchase_price' => 100000, 'down_payment' => 20000, 'expected_sale_price' => 150000, 'expected_months_until_sale' => 6])->assertCreated()->json('data.id');
        $this->postJson("/api/investment-analyses/$id/result", ['sale_price' => 150000, 'sale_costs' => null, 'flip_duration_months' => 6])->assertOk()->assertJsonPath('sale_costs', 0);
    }
}
