<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteIdentifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_resource_identifiers_do_not_reach_integer_controller_arguments(): void
    {
        $tenant = Tenant::create(['nome' => 'Audit', 'slug' => 'audit']);
        $this->actingAs(User::factory()->create(['tenant_id' => $tenant->id]), 'sanctum');
        foreach (['not-a-number', '-1', '999999999999999999999999999999'] as $id) {
            foreach (['propriedades', 'projetos', 'despesas', 'vendas', 'tarefas', 'documentos', 'fornecedores', 'eventos-calendario'] as $resource) {
                $this->getJson("/api/$resource/$id")->assertNotFound();
                $this->deleteJson("/api/$resource/$id")->assertNotFound();
            }
            foreach (['opportunities', 'investment-analyses'] as $resource) {
                $this->getJson("/api/$resource/$id")->assertNotFound();
            }
            $this->deleteJson("/api/financing-details/$id")->assertNotFound();
            $this->deleteJson("/api/investment-costs/$id")->assertNotFound();
            $this->putJson("/api/investment-results/$id", [])->assertNotFound();
            $this->getJson("/api/propriedades/$id/roi")->assertNotFound();
            $this->getJson("/api/projetos/$id/resumo")->assertNotFound();
            $this->postJson("/api/projetos/$id/etapas", ['nome' => 'Audit'])->assertNotFound();
            $this->patchJson("/api/projetos-etapas/$id", ['status' => 'pendente'])->assertNotFound();
        }
    }
}
