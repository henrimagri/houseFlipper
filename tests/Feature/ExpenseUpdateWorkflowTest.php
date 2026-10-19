<?php

namespace Tests\Feature;

use App\Models\CategoriaDespesa;
use App\Models\Despesa;
use App\Models\Projeto;
use App\Models\ProjetoEtapa;
use App\Models\Propriedade;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseUpdateWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_expense_can_be_edited_and_marked_paid_with_persisted_fields(): void
    {
        $tenant = Tenant::create(['nome' => 'Expense workflow', 'slug' => 'expense-workflow']);
        $this->actingAs(User::factory()->create(['tenant_id' => $tenant->id]), 'sanctum');
        $property = Propriedade::create(['apelido' => 'Imóvel QA']);
        $project = Projeto::create(['propriedade_id' => $property->id, 'nome' => 'Projeto QA']);
        $stage = ProjetoEtapa::create(['projeto_id' => $project->id, 'nome' => 'Hidráulica', 'orcamento' => 500]);
        $category = CategoriaDespesa::create(['nome' => 'Material', 'tipo' => 'reforma']);
        $expense = Despesa::create([
            'propriedade_id' => $property->id, 'projeto_id' => $project->id, 'etapa_id' => $stage->id,
            'categoria_despesa_id' => $category->id, 'descricao' => 'Despesa inicial',
            'valor' => 100, 'data_despesa' => '2026-10-15', 'status_pagamento' => 'pendente',
        ]);

        $payload = [
            'propriedade_id' => $property->id, 'projeto_id' => $project->id, 'etapa_id' => $stage->id,
            'categoria_despesa_id' => $category->id, 'descricao' => 'Despesa corrigida',
            'valor' => 123.47, 'data_despesa' => '2026-10-15', 'data_pagamento' => '2026-10-16',
            'status_pagamento' => 'pago', 'forma_pagamento' => 'pix', 'observacoes' => 'Pagamento conferido',
        ];

        $this->putJson("/api/despesas/{$expense->id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.descricao', 'Despesa corrigida')
            ->assertJsonPath('data.valor', '123.47')
            ->assertJsonPath('data.status_pagamento', 'pago')
            ->assertJsonPath('data.forma_pagamento', 'pix')
            ->assertJsonPath('data.data_pagamento', '2026-10-16')
            ->assertJsonPath('data.observacoes', 'Pagamento conferido');

        $this->assertDatabaseHas('despesas', ['id' => $expense->id, 'tenant_id' => $tenant->id, 'descricao' => 'Despesa corrigida', 'status_pagamento' => 'pago']);
        $this->assertDatabaseHas('projetos_etapas', ['id' => $stage->id, 'custo_real' => 123.47]);
        $this->assertDatabaseHas('projetos', ['id' => $project->id, 'custo_real' => 123.47]);
    }

    public function test_payment_status_keeps_payment_date_consistent_across_create_edit_and_cancel(): void
    {
        $tenant = Tenant::create(['nome' => 'Expense payment date', 'slug' => 'expense-payment-date']);
        $this->actingAs(User::factory()->create(['tenant_id' => $tenant->id]), 'sanctum');
        $category = CategoriaDespesa::create(['nome' => 'Material', 'tipo' => 'reforma']);
        $payload = [
            'categoria_despesa_id' => $category->id,
            'descricao' => 'Pagamento sem data explícita',
            'valor' => 100,
            'data_despesa' => '2026-10-15',
            'status_pagamento' => 'pago',
        ];

        $id = $this->postJson('/api/despesas', $payload)
            ->assertCreated()
            ->assertJsonPath('data.data_pagamento', now()->toDateString())
            ->json('data.id');

        $this->putJson('/api/despesas/'.$id, array_replace($payload, [
            'descricao' => 'Pagamento datado',
            'data_pagamento' => '2026-10-16',
        ]))->assertOk()->assertJsonPath('data.data_pagamento', '2026-10-16');

        $this->putJson('/api/despesas/'.$id, array_replace($payload, ['descricao' => 'Pagamento editado']))
            ->assertOk()
            ->assertJsonPath('data.descricao', 'Pagamento editado')
            ->assertJsonPath('data.data_pagamento', '2026-10-16');

        $this->putJson('/api/despesas/'.$id, array_replace($payload, [
            'status_pagamento' => 'cancelado',
            'data_pagamento' => now()->toDateString(),
        ]))->assertOk()->assertJsonPath('data.data_pagamento', null);
    }

    public function test_expense_patch_is_partial_and_still_validates_effective_relationships(): void
    {
        $tenant = Tenant::create(['nome' => 'Expense partial', 'slug' => 'expense-partial']);
        $this->actingAs(User::factory()->create(['tenant_id' => $tenant->id]), 'sanctum');
        $property = Propriedade::create(['apelido' => 'Imóvel QA']);
        $project = Projeto::create(['propriedade_id' => $property->id, 'nome' => 'Projeto A']);
        $otherProject = Projeto::create(['propriedade_id' => $property->id, 'nome' => 'Projeto B']);
        $stage = ProjetoEtapa::create(['projeto_id' => $project->id, 'nome' => 'Etapa A']);
        $otherStage = ProjetoEtapa::create(['projeto_id' => $otherProject->id, 'nome' => 'Etapa B']);
        $category = CategoriaDespesa::create(['nome' => 'Material', 'tipo' => 'reforma']);
        $expense = Despesa::create([
            'propriedade_id' => $property->id,
            'projeto_id' => $project->id,
            'etapa_id' => $stage->id,
            'categoria_despesa_id' => $category->id,
            'descricao' => 'Despesa original',
            'valor' => 100,
            'data_despesa' => '2026-10-15',
        ]);

        $this->patchJson('/api/despesas/'.$expense->id, ['observacoes' => 'Somente observação'])
            ->assertOk()
            ->assertJsonPath('data.observacoes', 'Somente observação')
            ->assertJsonPath('data.descricao', 'Despesa original')
            ->assertJsonPath('data.valor', '100.00')
            ->assertJsonPath('data.projeto_id', $project->id)
            ->assertJsonPath('data.etapa_id', $stage->id);

        $this->patchJson('/api/despesas/'.$expense->id, ['etapa_id' => $otherStage->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('etapa_id');
        $this->assertSame($stage->id, $expense->refresh()->etapa_id);

        $this->putJson('/api/despesas/'.$expense->id, ['observacoes' => 'PUT incompleto'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['categoria_despesa_id', 'descricao', 'valor', 'data_despesa']);
    }
}
