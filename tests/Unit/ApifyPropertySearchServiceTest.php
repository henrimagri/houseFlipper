<?php

namespace Tests\Unit;

use App\Services\ApifyPropertySearchService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApifyPropertySearchServiceTest extends TestCase
{
    public function test_it_reports_configuration_without_calling_apify_when_token_is_missing(): void
    {
        config()->set('services.apify.token', null);
        config()->set('services.apify.providers', [
            'zap' => ['label' => 'ZAP Imóveis', 'actor_id' => 'avorio/zap-imoveis-scraper'],
            'casamineira' => ['label' => 'Casa Mineira', 'actor_id' => null],
        ]);
        Http::fake();

        $result = app(ApifyPropertySearchService::class)->search('São Paulo', 'Perdizes', 'SP', 'apartment', 25);

        $this->assertSame([], $result['items']);
        $this->assertSame('not_configured', $result['diagnostics'][0]['state']);
        $this->assertSame('local_fallback', $result['diagnostics'][1]['state']);
        Http::assertNothingSent();
    }

    public function test_it_calls_configured_actor_and_normalizes_listing_output(): void
    {
        config()->set('services.apify.token', 'test-token');
        config()->set('services.apify.providers', [
            'zap' => ['label' => 'ZAP Imóveis', 'actor_id' => 'avorio/zap-imoveis-scraper'],
        ]);
        config()->set('services.apify.max_results_per_provider', 10);
        Http::fake([
            'api.apify.com/*' => Http::response([[
                'listing' => [
                    'id' => 'zap-1',
                    'title' => 'Apartamento em Perdizes',
                    'pricingInfos' => [['price' => 'R$ 650.000']],
                    'usableAreas' => [72],
                    'bedrooms' => [2],
                    'bathrooms' => [2],
                    'parkingSpaces' => [1],
                    'address' => ['city' => 'São Paulo', 'neighborhood' => 'Perdizes', 'stateAcronym' => 'SP'],
                ],
                'link' => 'https://www.zapimoveis.com.br/imovel/zap-1',
                'medias' => [['url' => 'https://resizedimgs.zapimoveis.com.br/photo.jpg']],
            ]], 200),
        ]);

        $result = app(ApifyPropertySearchService::class)->search('São Paulo', 'Perdizes', 'SP', 'apartment', 25);

        $this->assertCount(1, $result['items']);
        $this->assertSame(650000.0, $result['items'][0]['asking_price']);
        $this->assertSame('Perdizes', $result['items'][0]['neighborhood']);
        $this->assertSame('connected', $result['diagnostics'][0]['state']);
        $this->assertSame(1, $result['diagnostics'][0]['found']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'avorio~zap-imoveis-scraper')
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request['type'] === 'SALE'
            && $request['unitType'] === 'APARTMENT');
    }
}
