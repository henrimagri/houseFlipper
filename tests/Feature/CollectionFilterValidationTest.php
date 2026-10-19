<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionFilterValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_collection_endpoints_reject_unknown_filter_values(): void
    {
        $tenant = Tenant::create(['nome' => 'Filter QA', 'slug' => 'filter-qa']);
        $this->actingAs(User::factory()->create(['tenant_id' => $tenant->id]), 'sanctum');

        $cases = [
            ['/api/projetos?status=desconhecido', 'status'],
            ['/api/vendas?status=desconhecido', 'status'],
            ['/api/despesas?status_pagamento=desconhecido', 'status_pagamento'],
            ['/api/categorias-despesa?tipo=desconhecido', 'tipo'],
            ['/api/opportunities?status=desconhecido', 'status'],
            ['/api/opportunities?property_type=desconhecido', 'property_type'],
            ['/api/opportunities?source=desconhecido', 'source'],
            ['/api/opportunities?favorite=talvez', 'favorite'],
        ];

        foreach ($cases as [$url, $field]) {
            $this->getJson($url)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }

    public function test_opportunity_price_filter_rejects_inverted_range(): void
    {
        $tenant = Tenant::create(['nome' => 'Range QA', 'slug' => 'range-qa']);
        $this->actingAs(User::factory()->create(['tenant_id' => $tenant->id]), 'sanctum');

        $this->getJson('/api/opportunities?min_price=200000&max_price=100000')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('max_price');
    }

    public function test_collection_pagination_rejects_page_that_can_overflow_offset(): void
    {
        $tenant = Tenant::create(['nome' => 'Pagination bound QA', 'slug' => 'pagination-bound-qa']);
        $this->actingAs(User::factory()->create(['tenant_id' => $tenant->id]), 'sanctum');

        $this->getJson('/api/projetos?per_page=100&page=9223372036854775807')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('page');
    }

    public function test_collection_endpoints_reject_array_values_for_scalar_filters(): void
    {
        $tenant = Tenant::create(['nome' => 'Filter type QA', 'slug' => 'filter-type-qa']);
        $this->actingAs(User::factory()->create(['tenant_id' => $tenant->id]), 'sanctum');

        $cases = [
            ['/api/projetos?status[]=em_andamento', 'status'],
            ['/api/vendas?propriedade_id[]=1', 'propriedade_id'],
            ['/api/despesas?data_inicio[]=2026-01-01', 'data_inicio'],
            ['/api/categorias-despesa?tipo[]=obra', 'tipo'],
            ['/api/fornecedores?busca[]=audit', 'busca'],
            ['/api/documentos?categoria[]=contrato', 'categoria'],
            ['/api/opportunities?city[]=Sao%20Paulo', 'city'],
            ['/api/reports/ledger?data_inicio[]=2026-01-01', 'data_inicio'],
            ['/api/reports/ledger?projeto_id[]=1', 'projeto_id'],
        ];

        foreach ($cases as [$url, $field]) {
            $this->getJson($url)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }
}
