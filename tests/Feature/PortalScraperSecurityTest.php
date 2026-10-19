<?php

namespace Tests\Feature;

use App\Services\PropertyPortalScraper;
use App\Support\RemoteUrlPolicy;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\TestCase;

class PortalScraperSecurityTest extends TestCase
{
    public function test_php_fallback_does_not_follow_redirect_to_internal_destination(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://www.olx.com.br/*' => Http::response('', 302, ['Location' => 'https://127.0.0.1/private']),
        ]);

        $result = $this->fetch('https://www.olx.com.br/imoveis');

        $this->assertFalse($result['ok']);
        $this->assertSame('Serviço externo indisponível.', $result['message']);
        Http::assertSentCount(1);
    }

    public function test_php_fallback_follows_only_validated_public_portal_redirects(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://www.olx.com.br/*' => Http::response('', 302, ['Location' => 'https://www.zapimoveis.com.br/listing']),
            'https://www.zapimoveis.com.br/*' => Http::response(str_repeat('listing ', 100), 200),
        ]);

        $result = $this->fetch('https://www.olx.com.br/imoveis');

        $this->assertTrue($result['ok']);
        $this->assertSame(200, $result['status']);
        Http::assertSentCount(2);
    }

    public function test_php_fallback_rejects_oversized_portal_html(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://www.olx.com.br/*' => Http::response(str_repeat('A', 5 * 1024 * 1024 + 1), 200),
        ]);

        $result = $this->fetch('https://www.olx.com.br/imoveis');

        $this->assertFalse($result['ok']);
        $this->assertSame('Serviço externo indisponível.', $result['message']);
        $this->assertSame('', $result['body']);
    }

    private function fetch(string $url): array
    {
        $policy = new RemoteUrlPolicy(fn () => ['93.184.216.34']);
        $method = new ReflectionMethod(PropertyPortalScraper::class, 'fetch');

        return $method->invoke(new PropertyPortalScraper($policy), $url);
    }
}
