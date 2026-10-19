<?php

namespace App\Services\RealEstateData;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;

class ItbiRemoteFileClient
{
    public function get(string $url, int $timeout = 120, ?int $maxBytes = null, ?string &$finalUrl = null): Response
    {
        $maxBytes = max(1, $maxBytes ?? (int) config('real_estate_data.itbi_sp.max_download_bytes', 256000 * 1024));
        for ($redirects = 0; $redirects <= 3; $redirects++) {
            $this->assertAllowed($url);
            $response = Http::timeout($timeout)
                ->withOptions([
                    'allow_redirects' => false,
                    'decode_content' => false,
                    'on_headers' => function (ResponseInterface $response) use ($maxBytes) {
                        $this->assertSize((float) $response->getHeaderLine('Content-Length'), $maxBytes);
                    },
                    'progress' => function ($total, $received) use ($maxBytes) {
                        $this->assertSize(max($total, $received), $maxBytes);
                    },
                ])
                ->withHeaders(['Accept-Encoding' => 'identity'])
                ->accept('*/*')
                ->withUserAgent('InvestFlip/1.0 (+itbi-sp)')
                ->get($url);

            $size = $response->toPsrResponse()->getBody()->getSize();
            if ($size === null) {
                throw new \RuntimeException('Não foi possível verificar o tamanho do arquivo ITBI.');
            }
            $this->assertSize($size, $maxBytes);

            if (! in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                $finalUrl = $url;

                return $response;
            }

            $location = $response->header('Location');
            if (! $location) {
                throw new \RuntimeException('Redirecionamento ITBI sem destino.');
            }
            $url = (string) UriResolver::resolve(new Uri($url), new Uri($location));
        }

        throw new \RuntimeException('Limite de redirecionamentos ITBI excedido.');
    }

    private function assertSize(int|float $size, int $maxBytes): void
    {
        if ($size > $maxBytes) {
            throw new \RuntimeException('Resposta ITBI excede o limite de transferência.');
        }
    }

    private function assertAllowed(string $url): void
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        $sourceHost = parse_url((string) config('real_estate_data.itbi_sp.source_url'), PHP_URL_HOST);
        $allowedHosts = array_map(fn ($host) => strtolower(trim($host)), config('real_estate_data.itbi_sp.allowed_download_hosts', []));
        if ($sourceHost) {
            $allowedHosts[] = strtolower($sourceHost);
        }
        if (! $parts || ($parts['scheme'] ?? '') !== 'https'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
            || (isset($parts['port']) && $parts['port'] !== 443)
            || filter_var($host, FILTER_VALIDATE_IP)
            || ! filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
            || ! str_contains($host, '.')
            || preg_match('/(?:^|\.)(?:localhost|local|internal)$/i', $host)
            || ! in_array($host, $allowedHosts, true)) {
            throw new \RuntimeException('Destino ITBI não permitido. Configure os hosts oficiais autorizados.');
        }
    }
}
