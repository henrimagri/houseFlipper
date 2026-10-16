<?php

namespace App\Services\RealEstateData;

use App\Contracts\ItbiDatasetProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SaoPauloItbiDatasetProvider implements ItbiDatasetProviderInterface
{
    public function providerKey(): string
    {
        return config('real_estate_data.itbi_sp.provider', 'prefeitura_sp_itbi');
    }

    public function discover(): array
    {
        if (! config('real_estate_data.itbi_sp.enabled')) {
            return [];
        }

        $sourceUrl = config('real_estate_data.itbi_sp.source_url');
        if (! $sourceUrl) {
            throw new \RuntimeException('Configure ITBI_SP_SOURCE_URL com a página oficial da Prefeitura de São Paulo.');
        }

        $response = Http::timeout(25)
            ->accept('text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8')
            ->withUserAgent('InvestFlip/1.0 (+itbi-sp-discovery)')
            ->get($sourceUrl);

        if (! $response->successful()) {
            throw new \RuntimeException('Falha ao acessar fonte ITBI SP: HTTP '.$response->status());
        }

        return $this->extractDatasets($response->body(), $sourceUrl);
    }

    /**
     * @return array<int, array{url:string, filename:string, reference_period:?string, published_at:?string, metadata:array}>
     */
    private function extractDatasets(string $html, string $sourceUrl): array
    {
        preg_match_all('/href=["\']([^"\']+\.(?:xlsx|xls|csv|ods)(?:\?[^"\']*)?)["\']/i', $html, $matches);

        return collect($matches[1] ?? [])
            ->map(fn (string $url) => $this->absoluteUrl(html_entity_decode($url), $sourceUrl))
            ->unique()
            ->map(function (string $url) {
                $filename = basename(parse_url($url, PHP_URL_PATH) ?: 'itbi-sp-dataset');

                return [
                    'url' => $url,
                    'filename' => $filename,
                    'reference_period' => $this->referencePeriodFrom($filename),
                    'published_at' => null,
                    'metadata' => [
                        'extension' => Str::lower(pathinfo($filename, PATHINFO_EXTENSION)),
                    ],
                ];
            })
            ->values()
            ->all();
    }

    private function absoluteUrl(string $url, string $baseUrl): string
    {
        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        $base = parse_url($baseUrl);
        $scheme = $base['scheme'] ?? 'https';
        $host = $base['host'] ?? '';

        if (Str::startsWith($url, '//')) {
            return $scheme.':'.$url;
        }

        if (Str::startsWith($url, '/')) {
            return $scheme.'://'.$host.$url;
        }

        $path = isset($base['path']) ? Str::beforeLast($base['path'], '/') : '';

        return $scheme.'://'.$host.$path.'/'.$url;
    }

    private function referencePeriodFrom(string $filename): ?string
    {
        $name = Str::of($filename)->lower()->ascii()->toString();

        if (preg_match('/(20\d{2})[-_\. ]?(0[1-9]|1[0-2])/', $name, $match)) {
            return $match[1].'-'.$match[2];
        }

        if (preg_match('/(0[1-9]|1[0-2])[-_\. ]?(20\d{2})/', $name, $match)) {
            return $match[2].'-'.$match[1];
        }

        if (preg_match('/(20\d{2})/', $name, $match)) {
            return $match[1];
        }

        return null;
    }
}
