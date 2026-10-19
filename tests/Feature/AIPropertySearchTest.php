<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\PropertyPortalScraper;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

class AIPropertySearchTest extends TestCase
{
    use RefreshDatabase;

    private function authenticate(): void
    {
        $tenant = Tenant::create(['nome' => 'AI search QA', 'slug' => 'ai-search-qa']);
        $this->actingAs(User::factory()->create(['tenant_id' => $tenant->id]), 'sanctum');
    }

    public function test_scraped_items_are_returned_with_pipeline_diagnostics(): void
    {
        $this->authenticate();
        $this->mock(PropertyPortalScraper::class, function (MockInterface $mock): void {
            $mock->shouldReceive('scrape')->once()->andReturn([
                'source_name' => 'QA scraper',
                'diagnostics' => [['source' => 'OLX', 'status' => 200, 'found' => 1]],
                'items' => [[
                    'id' => 'qa-1', 'title' => 'Apartamento em Perdizes', 'address' => 'Perdizes',
                    'city' => 'São Paulo', 'neighborhood' => 'Perdizes', 'state' => 'SP',
                    'source' => 'OLX', 'source_key' => 'olx', 'source_url' => 'https://example.test/qa-1',
                    'thumbnail' => null, 'asking_price' => 500000, 'estimated_market_price' => 600000,
                    'discount_percentage' => 0, 'area_m2' => 80, 'bedrooms' => 2,
                    'bathrooms' => 1, 'parking_spaces' => 1, 'price_per_m2' => 6250,
                    'score' => 50, 'signal' => 'Atenção',
                ]],
            ]);
        });

        $response = $this->getJson('/api/ai-property-search?city=S%C3%A3o%20Paulo&neighborhood=Perdizes&state=SP&property_type=apartment&limit=250')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('source.mode', 'web_scraper');

        $this->assertSame(1, collect($response->json('source.diagnostics'))->firstWhere('source', 'Itens coletados')['found']);
        $this->assertSame(1, collect($response->json('source.diagnostics'))->firstWhere('source', 'Preço válido')['found']);
    }

    public function test_upstream_failure_returns_scraper_diagnostics_without_internal_details(): void
    {
        $this->authenticate();
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response([], 503)]);
        $this->mock(PropertyPortalScraper::class, function (MockInterface $mock): void {
            $mock->shouldReceive('scrape')->once()->andReturn([
                'source_name' => 'QA scraper', 'items' => [],
                'diagnostics' => [['source' => 'Headless', 'message' => 'Falha em C:\\private\\scraper.js ao acessar http://127.0.0.1:9000 com token QA-SECRET']],
            ]);
        });

        $response = $this->getJson('/api/ai-property-search?city=S%C3%A3o%20Paulo&neighborhood=Perdizes&state=SP')
            ->assertStatus(502)
            ->assertJsonPath('message', 'Nenhuma fonte de anúncios respondeu agora.')
            ->assertJsonPath('diagnostics.0.source', 'Headless')
            ->assertJsonPath('diagnostics.0.message', 'Fonte externa indisponível.')
            ->assertJsonMissing(['exception', 'trace', 'file']);

        $this->assertStringNotContainsString('QA-SECRET', $response->getContent());
        $this->assertStringNotContainsString('127.0.0.1', $response->getContent());
        $this->assertStringNotContainsString('private', $response->getContent());
    }

    public function test_external_listing_links_and_images_follow_their_respective_url_policies(): void
    {
        $this->authenticate();
        $this->mock(PropertyPortalScraper::class, function (MockInterface $mock): void {
            $unsafeImages = [
                'data:image/svg+xml,<svg onload=alert(1)>',
                'http://images.example.test/item.jpg',
                'https://user:secret@images.example.test/item.jpg',
                'https://localhost/item.jpg',
                'https://127.0.0.1/item.jpg',
                'https://10.0.0.5/item.jpg',
                'https://images.local/item.jpg',
                'https://images.example.test:8443/item.jpg',
            ];
            $items = collect($unsafeImages)->map(fn (string $thumbnail, int $index): array => [
                'id' => 'unsafe-'.$index, 'title' => 'Apartamento em Perdizes '.$index, 'address' => 'Perdizes',
                'city' => 'São Paulo', 'neighborhood' => 'Perdizes', 'state' => 'SP',
                'source' => 'Portal externo', 'source_key' => 'other',
                'source_url' => $thumbnail, 'thumbnail' => $thumbnail,
                'asking_price' => 500000 + $index, 'estimated_market_price' => 600000,
                'discount_percentage' => 0, 'area_m2' => 80, 'bedrooms' => 2,
                'bathrooms' => 1, 'parking_spaces' => 1, 'price_per_m2' => 6250,
                'score' => 50, 'signal' => 'Atenção',
            ])->push([
                'id' => 'safe', 'title' => 'Apartamento seguro em Perdizes', 'address' => 'Perdizes',
                'city' => 'São Paulo', 'neighborhood' => 'Perdizes', 'state' => 'SP',
                'source' => 'Portal externo', 'source_key' => 'other',
                'source_url' => 'https://example.test/item', 'thumbnail' => 'https://images.example.test:443/item.jpg',
                'asking_price' => 510000, 'estimated_market_price' => 600000,
                'discount_percentage' => 0, 'area_m2' => 80, 'bedrooms' => 2,
                'bathrooms' => 1, 'parking_spaces' => 1, 'price_per_m2' => 6375,
                'score' => 50, 'signal' => 'Atenção',
            ])->all();
            $mock->shouldReceive('scrape')->once()->andReturn([
                'source_name' => 'QA scraper',
                'diagnostics' => [],
                'items' => $items,
            ]);
        });

        $response = $this->getJson('/api/ai-property-search?city=S%C3%A3o%20Paulo&neighborhood=Perdizes&state=SP')
            ->assertOk()->assertJsonCount(9, 'data');

        $items = collect($response->json('data'));
        $unsafe = $items->where('id', '!=', 'safe');
        $this->assertSame(array_fill(0, 8, null), $unsafe->pluck('thumbnail')->values()->all());
        $this->assertSame(array_fill(0, 8, null), $unsafe->pluck('source_url')->values()->all());
        $this->assertSame('https://images.example.test:443/item.jpg', $items->firstWhere('id', 'safe')['thumbnail']);
        $this->assertSame('https://example.test/item', $items->firstWhere('id', 'safe')['source_url']);
    }

    public function test_api_fallbacks_disable_redirects_and_reject_oversized_final_bodies(): void
    {
        $this->authenticate();
        $this->mock(PropertyPortalScraper::class, function (MockInterface $mock): void {
            $mock->shouldReceive('scrape')->once()->andReturn(['source_name' => 'QA', 'diagnostics' => [], 'items' => []]);
        });
        $observed = [];
        Http::preventStrayRequests();
        Http::fake(function ($request, $options) use (&$observed) {
            $observed[] = [
                'encoding' => $request->header('Accept-Encoding')[0] ?? null,
                'redirects' => $options['allow_redirects'] ?? null,
                'decode_content' => $options['decode_content'] ?? null,
                'verify' => $options['verify'] ?? null,
            ];

            return Http::response(str_repeat('x', 2_097_153));
        });

        $this->getJson('/api/ai-property-search?city=Cidade%20QA&state=SP')->assertStatus(502);

        $this->assertNotEmpty($observed);
        foreach ($observed as $options) {
            $this->assertSame('identity', $options['encoding']);
            $this->assertFalse($options['redirects']);
            $this->assertFalse($options['decode_content']);
            $this->assertTrue($options['verify']);
        }
    }

    public function test_api_fallbacks_abort_announced_and_progressive_oversize(): void
    {
        $this->authenticate();
        $this->mock(PropertyPortalScraper::class, function (MockInterface $mock): void {
            $mock->shouldReceive('scrape')->once()->andReturn(['source_name' => 'QA', 'diagnostics' => [], 'items' => []]);
        });
        $call = 0;
        Http::preventStrayRequests();
        Http::fake(function ($request, $options) use (&$call) {
            $call++;
            if ($call % 2 === 1) {
                $options['on_headers'](new PsrResponse(200, ['Content-Length' => '2097153']));
            } else {
                $options['progress'](0, 2_097_153, 0, 0);
            }

            return Http::response([]);
        });

        $this->getJson('/api/ai-property-search?city=Cidade%20QA&state=SP')->assertStatus(502);
        $this->assertGreaterThan(1, $call);
    }
}
