<?php

namespace Tests\Feature;

use App\Models\CategoriaDespesa;
use App\Models\Propriedade;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchemaBoundValidationTest extends TestCase
{
    use RefreshDatabase;

    private function authenticate(): void
    {
        $tenant = Tenant::create(['nome' => 'Schema bounds QA', 'slug' => 'schema-bounds-qa']);
        $this->actingAs(User::factory()->create(['tenant_id' => $tenant->id]), 'sanctum');
    }

    public function test_persisted_property_areas_cannot_exceed_decimal_column_capacity(): void
    {
        $this->authenticate();

        $this->postJson('/api/propriedades', [
            'apelido' => 'Área acima do banco',
            'area_m2' => 100000000,
        ])->assertUnprocessable()->assertJsonValidationErrors('area_m2');

        $this->postJson('/api/opportunities', [
            'title' => 'Área acima do banco',
            'property_type' => 'house',
            'source' => 'manual',
            'area_m2' => 100000000,
        ])->assertUnprocessable()->assertJsonValidationErrors('area_m2');

        $this->assertDatabaseCount('propriedades', 0);
        $this->assertDatabaseCount('opportunities', 0);
    }

    public function test_market_analysis_rejects_out_of_domain_numbers_and_unknown_fields(): void
    {
        $this->authenticate();

        $this->postJson('/api/property-market-analysis', [
            'selected' => [
                'area_m2' => 100000000,
                'bedrooms' => 101,
                'bathrooms' => 101,
                'parking_spaces' => 101,
                'asking_price' => 1000000000000,
                'unexpected' => str_repeat('x', 100),
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'selected',
            'selected.area_m2',
            'selected.bedrooms',
            'selected.bathrooms',
            'selected.parking_spaces',
            'selected.asking_price',
        ]);
    }

    public function test_free_text_fields_respect_the_persisted_text_budget(): void
    {
        $this->authenticate();
        $property = Propriedade::create(['apelido' => 'Text bounds']);
        $category = CategoriaDespesa::create(['nome' => 'Text bounds', 'tipo' => 'outros']);
        $oversized = str_repeat('x', 5001);

        $this->postJson('/api/projetos', [
            'propriedade_id' => $property->id,
            'nome' => 'Project',
            'descricao' => $oversized,
            'etapas' => [['nome' => 'Stage', 'descricao' => $oversized]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['descricao', 'etapas.0.descricao']);

        $this->postJson('/api/despesas', [
            'categoria_despesa_id' => $category->id,
            'descricao' => 'Expense',
            'valor' => 1,
            'data_despesa' => '2026-10-17',
            'observacoes' => $oversized,
        ])->assertUnprocessable()->assertJsonValidationErrors('observacoes');

        $this->postJson('/api/vendas', [
            'propriedade_id' => $property->id,
            'valor_venda' => 1,
            'status' => 'proposta',
            'observacoes' => $oversized,
        ])->assertUnprocessable()->assertJsonValidationErrors('observacoes');

        $this->postJson('/api/opportunities', [
            'title' => 'Opportunity',
            'property_type' => 'house',
            'source' => 'manual',
            'description' => $oversized,
            'notes' => $oversized,
        ])->assertUnprocessable()->assertJsonValidationErrors(['description', 'notes']);

        $this->assertDatabaseCount('projetos', 0);
        $this->assertDatabaseCount('despesas', 0);
        $this->assertDatabaseCount('vendas', 0);
        $this->assertDatabaseCount('opportunities', 0);
    }

    public function test_stage_order_and_search_price_respect_numeric_capacity(): void
    {
        $this->authenticate();
        $property = Propriedade::create(['apelido' => 'Numeric bounds']);

        $this->postJson('/api/projetos', [
            'propriedade_id' => $property->id,
            'nome' => 'Project',
            'etapas' => [['nome' => 'Stage', 'ordem' => 2147483648]],
        ])->assertUnprocessable()->assertJsonValidationErrors('etapas.0.ordem');

        $this->getJson('/api/ai-property-search?max_price=1000000000000')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('max_price');

        $this->assertDatabaseCount('projetos', 0);
        $this->assertDatabaseCount('projetos_etapas', 0);
    }
}
