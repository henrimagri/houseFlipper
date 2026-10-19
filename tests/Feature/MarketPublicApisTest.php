<?php

namespace Tests\Feature;

use App\Services\EconomicIndicatorService;
use App\Services\MarketData\OpenGovernmentTransactionProvider;
use App\Services\PropertyMarketDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketPublicApisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_open_data_provider_normalizes_ckan_datastore_rows_for_the_neighborhood(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'dados.prefeitura.sp.gov.br/api/3/action/package_search*' => Http::response([
                'result' => ['results' => [[
                    'title' => 'ITBI',
                    'resources' => [['id' => 'res-1', 'datastore_active' => true]],
                ]]],
            ]),
            'dados.prefeitura.sp.gov.br/api/3/action/datastore_search*' => Http::response([
                'result' => ['records' => [
                    ['id' => 'right', 'valor' => 520000, 'area_m2' => 65, 'bairro' => 'Perdizes', 'cidade' => 'São Paulo'],
                    ['id' => 'wrong', 'valor' => 610000, 'area_m2' => 70, 'bairro' => 'Moema', 'cidade' => 'São Paulo'],
                    ['id' => 'broken', 'valor' => ['x'], 'area_m2' => 80],
                ]],
            ]),
            '*' => Http::response(['result' => ['results' => []]]),
        ]);

        $result = app(OpenGovernmentTransactionProvider::class)->search([
            'city' => 'São Paulo',
            'state' => 'SP',
            'neighborhood' => 'Perdizes',
        ]);

        $this->assertSame('connected', $result['status']);
        $this->assertSame('opendata_itbi', $result['key']);
        $this->assertCount(1, $result['transactions']);
        $this->assertSame('right', $result['transactions'][0]['source_reference']);
        $this->assertSame(520000.0, $result['transactions'][0]['price']);
    }

    public function test_open_data_provider_does_not_invent_rows_when_ckan_is_empty(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['result' => ['results' => []]])]);

        $result = app(OpenGovernmentTransactionProvider::class)->search([
            'city' => 'Campinas',
            'state' => 'SP',
            'neighborhood' => 'Cambuí',
        ]);

        $this->assertSame('no_dataset', $result['status']);
        $this->assertSame([], $result['transactions']);
    }

    public function test_bcb_sgs_loads_public_series_without_api_key(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.bcb.gov.br/*' => Http::response([['data' => '17/10/2026', 'valor' => '14,90']]),
        ]);

        $result = app(EconomicIndicatorService::class)->current();

        $this->assertSame('connected', $result['status']);
        $this->assertSame(14.9, collect($result['items'])->firstWhere('key', 'selic')['value']);
        $this->assertSame(14.9, collect($result['items'])->firstWhere('key', 'ivg_r')['value']);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'brasilapi.com.br'));
    }

    public function test_brasilapi_fills_selic_when_bcb_is_down(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.bcb.gov.br/*' => Http::response([], 503),
            'brasilapi.com.br/*' => Http::response([
                ['nome' => 'Selic', 'valor' => 13.75],
                ['nome' => 'CDI', 'valor' => 13.65],
                ['nome' => 'IPCA', 'valor' => 0.44],
            ]),
        ]);

        $result = app(EconomicIndicatorService::class)->current();

        $this->assertSame('connected', $result['status']);
        $this->assertSame(13.75, collect($result['items'])->firstWhere('key', 'selic')['value']);
        $this->assertNull(collect($result['items'])->firstWhere('key', 'ivg_r')['value']);
    }

    public function test_market_analysis_exposes_open_data_and_bcb_without_paid_endpoints(): void
    {
        Http::preventStrayRequests();
        config()->set('services.cartorios.endpoint', null);
        config()->set('services.itbi.endpoint', null);
        config()->set('services.itbi.sp_endpoint', null);
        config()->set('services.prefeituras.endpoint', null);
        Http::fake([
            'api.bcb.gov.br/*' => Http::response([['data' => '17/10/2026', 'valor' => '10']]),
            'dados.prefeitura.sp.gov.br/api/3/action/package_search*' => Http::response([
                'result' => ['results' => [[
                    'title' => 'ITBI',
                    'resources' => [['id' => 'res-1', 'datastore_active' => true]],
                ]]],
            ]),
            'dados.prefeitura.sp.gov.br/api/3/action/datastore_search*' => Http::response([
                'result' => ['records' => [
                    ['id' => 'sp-1', 'valor' => 480000, 'area_m2' => 60, 'bairro' => 'Perdizes', 'cidade' => 'São Paulo'],
                ]],
            ]),
            '*' => Http::response(['result' => ['results' => []]]),
        ]);

        $result = app(PropertyMarketDataService::class)->analyze([
            'city' => 'São Paulo',
            'state' => 'SP',
            'neighborhood' => 'Perdizes',
            'area_m2' => 60,
        ]);

        $this->assertGreaterThan(0, $result['summary']['confirmed_transactions_count']);
        $this->assertTrue(collect($result['providers'])->contains(fn (array $provider) => $provider['key'] === 'opendata_itbi' && $provider['status'] === 'connected'));
        $this->assertSame('connected', $result['economic_indicators']['status']);
        $this->assertFalse(collect($result['providers'])->contains(fn (array $provider) => str_contains(strtolower($provider['message']), 'api paga')));
    }
}
