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

class FinancialLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_ledger_is_tenant_scoped_and_ignores_cancelled_foreign_and_non_final_records(): void
    {
        $owner = $this->account('ledger-a');
        $foreign = $this->account('ledger-b');
        $category = CategoriaDespesa::create(['tenant_id' => $owner->tenant_id, 'nome' => 'Obra']);
        $property = Propriedade::create(['tenant_id' => $owner->tenant_id, 'apelido' => 'Casa Sé', 'valor_aquisicao' => 100000, 'data_aquisicao' => '2026-03-10']);
        $project = Projeto::create(['tenant_id' => $owner->tenant_id, 'propriedade_id' => $property->id, 'nome' => 'Reforma Sé']);
        Despesa::create(['tenant_id' => $owner->tenant_id, 'categoria_despesa_id' => $category->id, 'propriedade_id' => $property->id, 'projeto_id' => $project->id, 'descricao' => 'Tinta', 'valor' => 10000, 'data_despesa' => '2026-04-02', 'status_pagamento' => 'pago']);
        Despesa::create(['tenant_id' => $owner->tenant_id, 'categoria_despesa_id' => $category->id, 'propriedade_id' => $property->id, 'projeto_id' => $project->id, 'descricao' => 'Cancelada', 'valor' => 99000, 'data_despesa' => '2026-04-03', 'status_pagamento' => 'cancelado']);
        Venda::create(['tenant_id' => $owner->tenant_id, 'propriedade_id' => $property->id, 'valor_venda' => 150000, 'valor_comissao' => 5000, 'status' => 'fechada', 'data_fechamento' => '2026-05-20']);
        Venda::create(['tenant_id' => $owner->tenant_id, 'propriedade_id' => $property->id, 'valor_venda' => 999999, 'status' => 'proposta', 'data_proposta' => '2026-05-01']);
        Propriedade::create(['tenant_id' => $foreign->tenant_id, 'apelido' => 'Imóvel alheio', 'valor_aquisicao' => 999999, 'data_aquisicao' => '2026-03-10']);

        $this->actingAs($owner, 'sanctum')->getJson('/api/reports/ledger')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('totals.inflows', 145000)
            ->assertJsonPath('totals.outflows', 110000)
            ->assertJsonPath('totals.balance', 35000)
            ->assertJsonPath('data.0.type', 'sale')
            ->assertJsonPath('data.1.type', 'expense')
            ->assertJsonPath('data.1.project', 'Reforma Sé')
            ->assertJsonPath('data.2.type', 'acquisition')
            ->assertJsonMissingPath('data.0.tenant_id');

        $this->actingAs($foreign, 'sanctum')->getJson('/api/reports/ledger')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.description', 'Aquisição: Imóvel alheio')
            ->assertJsonPath('totals.outflows', 999999);
    }

    public function test_ledger_filters_by_period_and_project_and_paginates_the_same_filtered_set(): void
    {
        $owner = $this->account('ledger-filter');
        $this->actingAs($owner, 'sanctum');
        $category = CategoriaDespesa::create(['tenant_id' => $owner->tenant_id, 'nome' => 'Obra']);
        $first = $this->propertyWithProject($owner, 'Casa 1', 'Projeto 1', '2026-01-15');
        $second = $this->propertyWithProject($owner, 'Casa 2', 'Projeto 2', '2026-06-15');
        Despesa::create(['tenant_id' => $owner->tenant_id, 'categoria_despesa_id' => $category->id, 'propriedade_id' => $first['property']->id, 'projeto_id' => $first['project']->id, 'descricao' => 'Piso', 'valor' => 2000, 'data_despesa' => '2026-02-01', 'status_pagamento' => 'pago']);
        Despesa::create(['tenant_id' => $owner->tenant_id, 'categoria_despesa_id' => $category->id, 'propriedade_id' => $second['property']->id, 'projeto_id' => $second['project']->id, 'descricao' => 'Telhado', 'valor' => 3000, 'data_despesa' => '2026-07-01', 'status_pagamento' => 'pago']);
        Venda::create(['tenant_id' => $owner->tenant_id, 'propriedade_id' => $first['property']->id, 'valor_venda' => 120000, 'status' => 'fechada', 'data_fechamento' => '2026-03-10']);

        $period = $this->getJson('/api/reports/ledger?data_inicio=2026-02-01&data_fim=2026-03-31')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('filters.data_inicio', '2026-02-01')
            ->assertJsonPath('filters.data_fim', '2026-03-31')
            ->assertJsonPath('totals.inflows', 120000)
            ->assertJsonPath('totals.outflows', 2000);
        $this->assertSame(['sale', 'expense'], array_column($period->json('data'), 'type'));

        $project = $this->getJson('/api/reports/ledger?projeto_id='.$first['project']->id.'&per_page=2&page=1')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('filters.projeto_id', $first['project']->id)
            ->assertJsonPath('totals.balance', 18000);
        $this->assertSame(['sale', 'expense'], array_column($project->json('data'), 'type'));

        $this->getJson('/api/reports/ledger?projeto_id='.$first['project']->id.'&per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'acquisition')
            ->assertJsonPath('totals.balance', 18000);

        $foreign = $this->account('ledger-foreign');
        $this->actingAs($foreign, 'sanctum')->getJson('/api/reports/ledger?projeto_id='.$first['project']->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('projeto_id');
    }

    public function test_ledger_rejects_invalid_filters_and_users_without_tenant(): void
    {
        $owner = $this->account('ledger-validation');
        $this->actingAs($owner, 'sanctum');

        $this->getJson('/api/reports/ledger?data_inicio=2026-03-01&data_fim=2026-02-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('data_fim');
        $this->getJson('/api/reports/ledger?per_page=101')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->getJson('/api/reports/ledger?page=1000001')->assertUnprocessable()->assertJsonValidationErrors('page');
        $this->getJson('/api/reports/ledger?projeto_id=0')->assertUnprocessable()->assertJsonValidationErrors('projeto_id');

        $this->actingAs(User::factory()->create(['tenant_id' => null]), 'sanctum')
            ->getJson('/api/reports/ledger')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/reports/ledger')->assertUnauthorized();
    }

    public function test_dashboard_summary_stays_unfiltered_while_ledger_accepts_period(): void
    {
        $owner = $this->account('ledger-dashboard');
        $this->actingAs($owner, 'sanctum');
        Propriedade::create(['tenant_id' => $owner->tenant_id, 'apelido' => 'Antiga', 'valor_aquisicao' => 80000, 'data_aquisicao' => '2025-01-10']);
        Propriedade::create(['tenant_id' => $owner->tenant_id, 'apelido' => 'Recente', 'valor_aquisicao' => 20000, 'data_aquisicao' => now()->toDateString()]);

        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('kpis.total_invested', 100000);
        $this->getJson('/api/reports/ledger?data_inicio='.now()->toDateString().'&data_fim='.now()->toDateString())
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('totals.outflows', 20000);
    }

    private function account(string $slug): User
    {
        $tenant = Tenant::create(['nome' => $slug, 'slug' => $slug]);

        return User::factory()->create(['tenant_id' => $tenant->id]);
    }

    private function propertyWithProject(User $owner, string $propertyName, string $projectName, string $acquiredAt): array
    {
        $property = Propriedade::create(['tenant_id' => $owner->tenant_id, 'apelido' => $propertyName, 'valor_aquisicao' => 100000, 'data_aquisicao' => $acquiredAt]);
        $project = Projeto::create(['tenant_id' => $owner->tenant_id, 'propriedade_id' => $property->id, 'nome' => $projectName]);

        return ['property' => $property, 'project' => $project];
    }
}
