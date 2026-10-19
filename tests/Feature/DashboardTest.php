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

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_uses_real_tenant_data_and_ignores_cancelled_costs_and_foreign_records(): void
    {
        $a = Tenant::create(['nome' => 'A', 'slug' => 'a']);
        $b = Tenant::create(['nome' => 'B', 'slug' => 'b']);
        $property = Propriedade::create(['tenant_id' => $a->id, 'apelido' => 'Audit', 'valor_aquisicao' => 100000, 'data_aquisicao' => now()]);
        $category = CategoriaDespesa::create(['tenant_id' => $a->id, 'nome' => 'Audit']);
        foreach (['pago' => 10000, 'cancelado' => 99000] as $status => $amount) {
            Despesa::create(['tenant_id' => $a->id, 'categoria_despesa_id' => $category->id, 'propriedade_id' => $property->id, 'descricao' => 'Audit', 'valor' => $amount, 'data_despesa' => now(), 'status_pagamento' => $status]);
        }
        Projeto::create(['tenant_id' => $a->id, 'propriedade_id' => $property->id, 'nome' => 'Audit', 'status' => 'em_andamento']);
        Venda::create(['tenant_id' => $a->id, 'propriedade_id' => $property->id, 'valor_venda' => 150000, 'valor_comissao' => 5000, 'status' => 'fechada', 'data_fechamento' => now()]);
        Propriedade::create(['tenant_id' => $b->id, 'apelido' => 'Foreign', 'valor_aquisicao' => 999999]);
        $this->actingAs(User::factory()->create(['tenant_id' => $a->id]), 'sanctum')->getJson('/api/dashboard')->assertOk()
            ->assertJsonPath('kpis.total_invested', 110000)->assertJsonPath('kpis.realized_profit', 35000)
            ->assertJsonPath('kpis.sales_revenue', 150000)->assertJsonPath('kpis.sold_properties', 1)
            ->assertJsonPath('kpis.active_projects', 1)->assertJsonCount(6, 'performance')->assertJsonCount(1, 'activities');
        $this->actingAs(User::factory()->create(['tenant_id' => $b->id]), 'sanctum')->getJson('/api/dashboard')->assertOk()
            ->assertJsonPath('kpis.sales_revenue', 0)->assertJsonCount(0, 'projects');
    }

    public function test_prospect_asking_price_is_not_counted_as_invested_capital(): void
    {
        $tenant = Tenant::create(['nome' => 'Prospect QA', 'slug' => 'prospect-qa-'.uniqid()]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        Propriedade::create([
            'tenant_id' => $tenant->id,
            'apelido' => 'Apto Aldeota QA',
            'valor_aquisicao' => 480000,
            'status' => 'prospeccao',
        ]);
        Propriedade::create([
            'tenant_id' => $tenant->id,
            'apelido' => 'Casa comprada',
            'valor_aquisicao' => 150000,
            'data_aquisicao' => now(),
            'status' => 'adquirida',
        ]);
        $category = CategoriaDespesa::create(['tenant_id' => $tenant->id, 'nome' => 'Obra']);
        Despesa::create([
            'tenant_id' => $tenant->id,
            'categoria_despesa_id' => $category->id,
            'descricao' => 'Material',
            'valor' => 25000,
            'data_despesa' => now(),
            'status_pagamento' => 'pago',
        ]);

        $this->actingAs($user, 'sanctum')->getJson('/api/dashboard')->assertOk()
            ->assertJsonPath('kpis.total_invested', 175000)
            ->assertJsonPath('composition.0.valor', 150000)
            ->assertJsonPath('composition.1.valor', 25000);
    }
}
