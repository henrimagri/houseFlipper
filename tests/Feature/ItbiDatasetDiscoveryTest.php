<?php

namespace Tests\Feature;

use App\Services\RealEstateData\SaoPauloItbiDatasetProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ItbiDatasetDiscoveryTest extends TestCase
{
    public function test_relative_links_use_final_page_location_after_redirect(): void
    {
        config()->set('real_estate_data.itbi_sp.enabled', true);
        config()->set('real_estate_data.itbi_sp.source_url', 'https://source.test/catalog/index.html');
        Http::preventStrayRequests();
        Http::fake([
            'https://source.test/catalog/index.html' => Http::response('', 302, ['Location' => '/archive/2026/index.html']),
            'https://source.test/archive/2026/index.html' => Http::response('<a href="../data.csv">ITBI</a>'),
        ]);
        $datasets = app(SaoPauloItbiDatasetProvider::class)->discover();
        $this->assertSame('https://source.test/archive/data.csv', $datasets[0]['url']);
    }

    public function test_configured_source_host_is_allowed_and_relative_datasets_are_preserved(): void
    {
        config()->set('real_estate_data.itbi_sp.enabled', true);
        config()->set('real_estate_data.itbi_sp.source_url', 'https://source.test/catalog/index.html');
        config()->set('real_estate_data.itbi_sp.allowed_download_hosts', []);
        Http::preventStrayRequests();
        Http::fake(['source.test/*' => Http::response('<a href="data-2026-01.csv">ITBI</a><a href="/annual.ods">ODS</a>')]);
        $datasets = app(SaoPauloItbiDatasetProvider::class)->discover();
        $this->assertSame('https://source.test/catalog/data-2026-01.csv', $datasets[0]['url']);
        $this->assertSame('2026-01', $datasets[0]['reference_period']);
        $this->assertSame('https://source.test/annual.ods', $datasets[1]['url']);
    }

    public function test_discovery_does_not_follow_redirect_outside_configured_hosts(): void
    {
        config()->set('real_estate_data.itbi_sp.enabled', true);
        config()->set('real_estate_data.itbi_sp.source_url', 'https://source.test/index.html');
        config()->set('real_estate_data.itbi_sp.allowed_download_hosts', []);
        Http::preventStrayRequests();
        Http::fake(['source.test/*' => Http::response('', 302, ['Location' => 'https://other.test/index.html'])]);
        try {
            app(SaoPauloItbiDatasetProvider::class)->discover();
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('não permitido', $exception->getMessage());
            Http::assertSentCount(1);

            return;
        }
        $this->fail('Discovery redirect must be rejected.');
    }
}
