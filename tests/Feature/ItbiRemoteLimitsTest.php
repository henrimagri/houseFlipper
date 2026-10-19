<?php

namespace Tests\Feature;

use App\Services\RealEstateData\ItbiRemoteFileClient;
use App\Services\RealEstateData\SaoPauloItbiDatasetProvider;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ItbiRemoteLimitsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('real_estate_data.itbi_sp.allowed_download_hosts', ['audit.test']);
        config()->set('real_estate_data.itbi_sp.max_download_bytes', 4);
    }

    public function test_exact_size_is_allowed_and_body_over_limit_is_rejected(): void
    {
        Http::fake(['audit.test/*' => Http::sequence()->push('1234')->push('12345', 200, ['Content-Length' => '3'])]);
        $this->assertSame('1234', app(ItbiRemoteFileClient::class)->get('https://audit.test/data.csv')->body());
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('limite de transferência');
        app(ItbiRemoteFileClient::class)->get('https://audit.test/data.csv');
    }

    public function test_headers_callback_rejects_announced_size_before_body_processing(): void
    {
        Http::fake(function ($request, $options) {
            $this->assertFalse($options['allow_redirects']);
            $this->assertFalse($options['decode_content']);
            $this->assertSame('identity', $request->header('Accept-Encoding')[0]);
            $options['on_headers'](new Response(200, ['Content-Length' => '5']));
            $this->fail('Oversized header must interrupt the transfer.');
        });
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('limite de transferência');
        app(ItbiRemoteFileClient::class)->get('https://audit.test/data.csv');
    }

    public function test_progress_callback_rejects_received_bytes_without_content_length(): void
    {
        Http::fake(function ($request, $options) {
            $options['progress'](0, 5, 0, 0);
            $this->fail('Received bytes must interrupt the transfer.');
        });
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('limite de transferência');
        app(ItbiRemoteFileClient::class)->get('https://audit.test/data.csv');
    }

    public function test_discovery_uses_its_separate_size_limit(): void
    {
        config()->set('real_estate_data.itbi_sp.enabled', true);
        config()->set('real_estate_data.itbi_sp.source_url', 'https://audit.test/index.html');
        config()->set('real_estate_data.itbi_sp.max_download_bytes', 100);
        config()->set('real_estate_data.itbi_sp.max_discovery_bytes', 4);
        Http::fake(['audit.test/*' => Http::response('12345')]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('limite de transferência');
        app(SaoPauloItbiDatasetProvider::class)->discover();
    }
}
