<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class PropertyPortalScraper
{
    public function scrape(string $city, string $neighborhood, int $limit = 250, string $state = 'SP', string $propertyType = 'apartment'): array
    {
        $limit = min(250, max(1, $limit));
        $state = strtoupper(trim($state)) ?: 'SP';
        $propertyType = in_array($propertyType, ['apartment', 'house', 'commercial'], true) ? $propertyType : 'apartment';
        $region = trim($neighborhood.' '.$city) ?: 'São Paulo';
        $headless = $this->scrapeWithHeadlessBrowser($city, $neighborhood, $limit, $state, $propertyType);

        if (count($headless['items']) > 0) {
            return [
                'items' => collect($headless['items'])
                    ->filter(fn (array $item) => $this->rawItemMatchesLocation($item, $city, $neighborhood, $state))
                    ->map(fn (array $item) => $this->normalizeHeadlessItem($item, $city, $neighborhood, $state))
                    ->sortByDesc('score')
                    ->take($limit)
                    ->values()
                    ->all(),
                'diagnostics' => $headless['diagnostics'],
                'source_name' => 'Web Scraper Headless: ZAP, VivaReal, OLX, ImovelWeb, Chaves na Mão, Casa Mineira, Loft, QuintoAndar, Mercado Livre e Facebook Marketplace',
            ];
        }

        $targets = $this->targets($city, $neighborhood, $region, $state, $propertyType);
        $items = collect();
        $diagnostics = [];

        foreach ($targets as $target) {
            if ($items->count() >= $limit) {
                break;
            }

            $result = $this->fetch($target['url']);
            if (! $result['ok']) {
                $diagnostics[] = ['source' => $target['name'], 'status' => $result['status'], 'message' => $result['message']];
                continue;
            }

            $parsed = $this->parseHtml($result['body'], $target, $city, $neighborhood, $state)
                ->take(max(0, $limit - $items->count()));

            $diagnostics[] = ['source' => $target['name'], 'status' => $result['status'], 'found' => $parsed->count()];
            $items = $items->merge($parsed);
        }

        $items = $items
            ->filter(fn (array $item) => $item['asking_price'] > 0)
            ->filter(fn (array $item) => ($item['location_confirmed'] ?? true) === true)
            ->unique(fn (array $item) => $item['source_url'] ?: $item['title'].'-'.$item['asking_price'])
            ->sortByDesc('score')
            ->take($limit)
            ->values();

        return [
            'items' => $items->all(),
            'diagnostics' => $diagnostics,
            'source_name' => 'Web Scraper: ZAP, VivaReal, OLX, ImovelWeb, Chaves na Mão, Casa Mineira, Loft, QuintoAndar, Mercado Livre e Facebook Marketplace',
        ];
    }

    private function scrapeWithHeadlessBrowser(string $city, string $neighborhood, int $limit, string $state, string $propertyType): array
    {
        $script = base_path('frontend/scripts/portal-scraper.mjs');
        if (! file_exists($script)) {
            return ['items' => [], 'diagnostics' => [['source' => 'Headless', 'message' => 'Script não encontrado']]];
        }

        $process = new Process([
            'node',
            $script,
            json_encode(['city' => $city, 'neighborhood' => $neighborhood, 'state' => $state, 'propertyType' => $propertyType, 'limit' => $limit], JSON_UNESCAPED_UNICODE),
        ], base_path('frontend'), ['CHROME_PATH' => '/usr/bin/google-chrome'], null, 120);

        try {
            $process->run();
        } catch (\Throwable $exception) {
            return ['items' => [], 'diagnostics' => [['source' => 'Headless', 'message' => $exception->getMessage()]]];
        }

        if (! $process->isSuccessful()) {
            return ['items' => [], 'diagnostics' => [['source' => 'Headless', 'message' => trim($process->getErrorOutput()) ?: 'Falha ao executar navegador']]];
        }

        $decoded = json_decode($process->getOutput(), true);

        return [
            'items' => is_array($decoded['items'] ?? null) ? $decoded['items'] : [],
            'diagnostics' => is_array($decoded['diagnostics'] ?? null) ? $decoded['diagnostics'] : [],
        ];
    }

    private function normalizeHeadlessItem(array $item, string $city, string $neighborhood, string $state): array
    {
        $price = (float) ($item['asking_price'] ?? 0);
        $area = (float) ($item['area_m2'] ?? 0);
        $bedrooms = (int) ($item['bedrooms'] ?? 0);
        $market = $this->estimateMarketPrice($price, $area, (string) ($item['text'] ?? $item['title'] ?? ''));
        $discount = $market > 0 ? max(0, ($market - $price) / $market * 100) : 0;
        $score = $this->score($discount, $area, $bedrooms, $price);

        return [
            'id' => sha1(($item['source'] ?? '').'|'.($item['source_url'] ?? '').'|'.($item['title'] ?? '').'|'.$price),
            'title' => $item['title'] ?? 'Imóvel anunciado',
            'address' => $item['address'] ?? ($neighborhood ?: $city ?: 'Região pesquisada'),
            'city' => $item['city'] ?: $city,
            'neighborhood' => $item['neighborhood'] ?: $neighborhood,
            'state' => $item['state'] ?? $state,
            'source' => $item['source'] ?? 'Web Scraper',
            'source_key' => $item['source_key'] ?? 'other',
            'source_url' => $item['source_url'] ?? null,
            'thumbnail' => $this->validImageUrl($item['thumbnail'] ?? null, $item['source_url'] ?? null),
            'location_confirmed' => $this->rawItemMatchesLocation($item, $city, $neighborhood, $state),
            'asking_price' => round($price, 2),
            'estimated_market_price' => round($market, 2),
            'discount_percentage' => round($discount, 2),
            'area_m2' => $area ?: null,
            'bedrooms' => $bedrooms ?: null,
            'bathrooms' => isset($item['bathrooms']) ? (int) $item['bathrooms'] : null,
            'parking_spaces' => isset($item['parking_spaces']) ? (int) $item['parking_spaces'] : null,
            'price_per_m2' => $area > 0 ? round($price / $area, 2) : null,
            'score' => $score,
            'signal' => $score >= 80 ? 'Boa oportunidade' : ($score >= 65 ? 'Negociar' : 'Atenção'),
        ];
    }

    private function targets(string $city, string $neighborhood, string $region, string $state, string $propertyType): array
    {
        $citySlug = $this->slug($city ?: $region);
        $neighborhoodSlug = $this->slug($neighborhood);
        $stateSlug = strtolower($state);
        $regionQuery = urlencode(trim($region));
        $terms = $this->propertyTerms($propertyType);
        $zapLocation = $neighborhoodSlug ? "{$stateSlug}+{$citySlug}+{$neighborhoodSlug}" : "{$stateSlug}+{$citySlug}";
        $pathLocation = $neighborhoodSlug ? "{$citySlug}/{$neighborhoodSlug}" : $citySlug;
        $dashLocation = $neighborhoodSlug ? "{$neighborhoodSlug}-{$citySlug}" : $citySlug;

        return [
            [
                'name' => 'ZAP Imóveis',
                'source_key' => 'zap',
                'url' => "https://www.zapimoveis.com.br/venda/{$terms['zap']}/{$zapLocation}/",
            ],
            [
                'name' => 'VivaReal',
                'source_key' => 'vivareal',
                'url' => "https://www.vivareal.com.br/venda/{$stateSlug}/{$pathLocation}/{$terms['vivareal']}/",
            ],
            [
                'name' => 'OLX',
                'source_key' => 'olx',
                'url' => "https://www.olx.com.br/imoveis/venda/{$terms['olx']}/estado-{$stateSlug}?q={$regionQuery}",
            ],
            [
                'name' => 'ImovelWeb',
                'source_key' => 'other',
                'url' => "https://www.imovelweb.com.br/{$terms['imovelweb']}-venda-{$dashLocation}-{$stateSlug}.html",
            ],
            [
                'name' => 'Chaves na Mão',
                'source_key' => 'other',
                'url' => "https://www.chavesnamao.com.br/{$terms['chaves']}-a-venda/{$stateSlug}-{$citySlug}/",
            ],
            [
                'name' => 'Casa Mineira',
                'source_key' => 'realtor',
                'url' => "https://www.casamineira.com.br/venda/{$terms['casamineira']}/{$citySlug}_{$stateSlug}",
            ],
            [
                'name' => 'Loft',
                'source_key' => 'other',
                'url' => "https://loft.com.br/venda/imoveis/{$stateSlug}/{$citySlug}/{$terms['loft']}",
            ],
            [
                'name' => 'QuintoAndar',
                'source_key' => 'quintoandar',
                'url' => "https://www.quintoandar.com.br/comprar/imovel/{$citySlug}-{$stateSlug}-brasil/{$terms['quintoandar']}",
            ],
            [
                'name' => 'Mercado Livre',
                'source_key' => 'other',
                'url' => 'https://lista.mercadolivre.com.br/imoveis/'.$terms['mercadolivre'].'-venda-'.$this->slug($region.' '.$state),
            ],
            [
                'name' => 'Facebook Marketplace',
                'source_key' => 'facebook',
                'url' => 'https://www.facebook.com/marketplace/search/?query='.urlencode($terms['query'].' venda '.$region.' '.$state),
            ],
        ];
    }

    private function fetch(string $url): array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
            ])->timeout(15)->get($url);
        } catch (\Throwable $exception) {
            return ['ok' => false, 'status' => 0, 'message' => $exception->getMessage(), 'body' => ''];
        }

        $body = $response->body();
        $blocked = Str::contains(Str::lower($body), ['captcha', 'suspicious traffic', 'access denied', 'robot']);

        return [
            'ok' => $response->successful() && ! $blocked && strlen($body) > 500,
            'status' => $response->status(),
            'message' => $blocked ? 'Bloqueado por anti-bot/captcha' : ($response->successful() ? 'OK' : 'Falha HTTP'),
            'body' => $body,
        ];
    }

    private function parseHtml(string $html, array $target, string $city, string $neighborhood, string $state): Collection
    {
        $fromJson = $this->parseJsonLd($html, $target, $city, $neighborhood, $state);
        $fromDom = $this->parseDomCards($html, $target, $city, $neighborhood, $state);

        return $fromJson
            ->merge($fromDom)
            ->unique(fn (array $item) => $item['source_url'] ?: $item['title'].'-'.$item['asking_price'])
            ->values();
    }

    private function parseJsonLd(string $html, array $target, string $city, string $neighborhood, string $state): Collection
    {
        preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches);
        $items = collect();

        foreach ($matches[1] ?? [] as $json) {
            $decoded = json_decode(html_entity_decode(trim($json)), true);
            foreach ($this->flattenJsonLd($decoded) as $node) {
                $price = $this->extractPrice((string) data_get($node, 'offers.price', data_get($node, 'price', '')));
                $title = data_get($node, 'name') ?: data_get($node, 'headline');
                $url = data_get($node, 'url');

                if (! $title || ! $price) {
                    continue;
                }

                $items->push($this->normalize([
                    'title' => $title,
                    'text' => json_encode($node, JSON_UNESCAPED_UNICODE),
                    'price' => $price,
                    'url' => $this->absoluteUrl($url, $target['url']),
                    'thumbnail' => data_get($node, 'image.0', data_get($node, 'image')),
                ], $target, $city, $neighborhood, $state));
            }
        }

        return $items;
    }

    private function parseDomCards(string $html, array $target, string $city, string $neighborhood, string $state): Collection
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);
        $anchors = $xpath->query('//a[@href]');
        $items = collect();

        foreach ($anchors as $anchor) {
            if (! $anchor instanceof DOMElement) {
                continue;
            }

            $href = $anchor->getAttribute('href');
            $text = trim(preg_replace('/\s+/', ' ', $anchor->textContent));
            $card = $this->nearbyText($anchor);
            $combined = trim($text.' '.$card);
            $price = $this->extractPrice($combined);

            if (! $price || ! $this->looksLikeListing($href, $combined)) {
                continue;
            }

            $items->push($this->normalize([
                'title' => $this->extractTitle($text, $combined),
                'text' => $combined,
                'price' => $price,
                'url' => $this->absoluteUrl($href, $target['url']),
                'thumbnail' => $this->nearbyImage($anchor),
            ], $target, $city, $neighborhood, $state));
        }

        return $items;
    }

    private function normalize(array $raw, array $target, string $city, string $neighborhood, string $state): array
    {
        $text = (string) ($raw['text'] ?? '');
        $price = (float) $raw['price'];
        $area = $this->extractNumber($text, '/(\d+(?:[,.]\d+)?)\s*m²/iu');
        $bedrooms = (int) $this->extractNumber($text, '/(\d+)\s*(?:quarto|dormitório|dorm)/iu');
        $bathrooms = (int) $this->extractNumber($text, '/(\d+)\s*(?:banheiro|banh)/iu');
        $parking = (int) $this->extractNumber($text, '/(\d+)\s*(?:vaga|garagem)/iu');
        $market = $this->estimateMarketPrice($price, $area, $text);
        $discount = $market > 0 ? max(0, ($market - $price) / $market * 100) : 0;
        $score = $this->score($discount, $area, $bedrooms, $price);
        $locationConfirmed = $this->rawTextMatchesLocation(
            implode(' ', [$raw['title'] ?? '', $raw['text'] ?? '', $raw['url'] ?? '']),
            $city,
            $neighborhood,
            $state
        );

        return [
            'id' => sha1($target['name'].'|'.($raw['url'] ?? '').'|'.$raw['title'].'|'.$price),
            'title' => Str::limit(trim($raw['title'] ?: 'Imóvel anunciado'), 120, ''),
            'address' => $neighborhood ?: $city ?: 'Região pesquisada',
            'city' => $city,
            'neighborhood' => $neighborhood,
            'state' => $state,
            'source' => $target['name'],
            'source_key' => $target['source_key'],
            'source_url' => $raw['url'] ?? null,
            'thumbnail' => $this->validImageUrl($raw['thumbnail'] ?? null, $raw['url'] ?? $target['url']),
            'location_confirmed' => $locationConfirmed,
            'asking_price' => round($price, 2),
            'estimated_market_price' => round($market, 2),
            'discount_percentage' => round($discount, 2),
            'area_m2' => $area ?: null,
            'bedrooms' => $bedrooms ?: null,
            'bathrooms' => $bathrooms ?: null,
            'parking_spaces' => $parking ?: null,
            'price_per_m2' => $area > 0 ? round($price / $area, 2) : null,
            'score' => $score,
            'signal' => $score >= 80 ? 'Boa oportunidade' : ($score >= 65 ? 'Negociar' : 'Atenção'),
        ];
    }

    private function flattenJsonLd(mixed $node): array
    {
        if (! is_array($node)) {
            return [];
        }

        $items = [];
        if (isset($node['@type']) || isset($node['offers'])) {
            $items[] = $node;
        }

        foreach (['@graph', 'itemListElement'] as $key) {
            foreach ((array) ($node[$key] ?? []) as $child) {
                $items = array_merge($items, $this->flattenJsonLd($child['item'] ?? $child));
            }
        }

        if (array_is_list($node)) {
            foreach ($node as $child) {
                $items = array_merge($items, $this->flattenJsonLd($child));
            }
        }

        return $items;
    }

    private function nearbyText(DOMElement $element): string
    {
        $node = $element;
        for ($i = 0; $i < 4 && $node->parentNode instanceof DOMElement; $i++) {
            $node = $node->parentNode;
            $text = trim(preg_replace('/\s+/', ' ', $node->textContent));
            if (Str::contains($text, 'R$') && mb_strlen($text) < 2500) {
                return $text;
            }
        }

        return '';
    }

    private function nearbyImage(DOMElement $element): ?string
    {
        $node = $element;
        for ($i = 0; $i < 4 && $node->parentNode instanceof DOMElement; $i++) {
            $node = $node->parentNode;
            foreach ($node->getElementsByTagName('img') as $image) {
                $src = $image->getAttribute('src') ?: $image->getAttribute('data-src');
                $imageUrl = $this->validImageUrl($src, '');
                if ($imageUrl) {
                    return $imageUrl;
                }
            }
        }

        return null;
    }

    private function extractPrice(string $text): float
    {
        if (! preg_match('/R\$\s*([\d\.]+)(?:,(\d{2}))?/u', $text, $match)) {
            return 0;
        }

        return (float) str_replace('.', '', $match[1]).(isset($match[2]) ? ((float) ('0.'.$match[2])) : 0);
    }

    private function extractNumber(string $text, string $pattern): float
    {
        if (! preg_match($pattern, $text, $match)) {
            return 0;
        }

        return (float) str_replace(',', '.', $match[1]);
    }

    private function extractTitle(string $text, string $combined): string
    {
        $candidate = trim($text) ?: trim(Str::before($combined, 'R$'));

        return $candidate ?: 'Imóvel anunciado';
    }

    private function looksLikeListing(string $href, string $text): bool
    {
        $haystack = Str::lower($href.' '.$text);

        return Str::contains($haystack, ['apartamento', 'casa', 'comercial', 'sala', 'loja', 'imovel', 'imóvel', 'venda']) && Str::contains($text, 'R$');
    }

    private function absoluteUrl(?string $url, string $base): ?string
    {
        if (! $url) {
            return null;
        }

        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        $parts = parse_url($base);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');

        return $origin.'/'.ltrim($url, '/');
    }

    private function validImageUrl(mixed $url, ?string $base = null): ?string
    {
        if (is_array($url)) {
            $url = $url[0] ?? null;
        }

        $url = trim((string) $url);
        if ($url === '' || Str::startsWith($url, ['data:', 'blob:'])) {
            return null;
        }

        if (Str::contains(Str::lower($url), ['logo', 'avatar', 'sprite', 'placeholder'])) {
            return null;
        }

        if (Str::contains($url, ',')) {
            $url = trim(explode(',', $url)[0]);
        }

        if (Str::contains($url, ' ')) {
            $url = trim(explode(' ', $url)[0]);
        }

        if (! Str::startsWith($url, ['http://', 'https://', '//', '/'])) {
            return null;
        }

        if (Str::startsWith($url, '//')) {
            return 'https:'.$url;
        }

        if (Str::startsWith($url, '/') && $base) {
            return $this->absoluteUrl($url, $base);
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($url);
        if (! empty($parts['query'])) {
            parse_str($parts['query'], $query);
            foreach (['url', 'image', 'img', 'src'] as $key) {
                if (! empty($query[$key]) && is_string($query[$key]) && filter_var($query[$key], FILTER_VALIDATE_URL)) {
                    return $query[$key];
                }
            }
        }

        return $url;
    }

    private function rawItemMatchesLocation(array $item, string $city, string $neighborhood, string $state): bool
    {
        return $this->rawTextMatchesLocation(
            implode(' ', [
                $item['title'] ?? '',
                $item['text'] ?? '',
                $item['address'] ?? '',
                $item['neighborhood'] ?? '',
                $item['city'] ?? '',
                $item['state'] ?? '',
                $item['source_url'] ?? '',
            ]),
            $city,
            $neighborhood,
            $state
        );
    }

    private function rawTextMatchesLocation(string $text, string $city, string $neighborhood, string $state): bool
    {
        $haystack = $this->normalizeSearchText($text);
        $cityNeedle = $this->normalizeSearchText($city);
        $neighborhoodNeedle = $this->normalizeSearchText($neighborhood);
        $stateNeedle = $this->normalizeSearchText($state);

        if ($neighborhoodNeedle !== '') {
            return Str::contains($haystack, $neighborhoodNeedle);
        }

        if ($cityNeedle !== '') {
            return Str::contains($haystack, $cityNeedle) || ($stateNeedle !== '' && Str::contains($haystack, $stateNeedle));
        }

        return true;
    }

    private function normalizeSearchText(?string $value): string
    {
        return Str::of((string) $value)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }

    private function estimateMarketPrice(float $askingPrice, float $area, string $title): float
    {
        $renovationWords = Str::contains(Str::lower($title), ['reforma', 'reformar', 'antigo', 'oportunidade']);
        $uplift = $renovationWords ? 1.22 : 1.12;
        if ($area >= 70) {
            $uplift += 0.04;
        }

        return $askingPrice > 0 ? $askingPrice * $uplift : 0;
    }

    private function score(float $discount, float $area, int $bedrooms, float $askingPrice): int
    {
        $score = 42 + min(35, $discount * 1.5);
        $score += $area >= 45 && $area <= 120 ? 8 : 0;
        $score += $bedrooms >= 2 ? 6 : 0;
        $score += $askingPrice > 0 && $askingPrice <= 750000 ? 7 : 0;

        return (int) max(0, min(100, round($score)));
    }

    private function propertyTerms(string $propertyType): array
    {
        return match ($propertyType) {
            'house' => [
                'query' => 'casa',
                'zap' => 'casas',
                'vivareal' => 'casa_residencial',
                'olx' => 'casas',
                'imovelweb' => 'casas',
                'chaves' => 'casas',
                'casamineira' => 'casa',
                'loft' => 'casas',
                'quintoandar' => 'casa',
                'mercadolivre' => 'casa',
            ],
            'commercial' => [
                'query' => 'imóvel comercial',
                'zap' => 'imoveis-comerciais',
                'vivareal' => 'imovel_comercial',
                'olx' => 'comerciais',
                'imovelweb' => 'comerciais',
                'chaves' => 'comerciais',
                'casamineira' => 'imovel-comercial',
                'loft' => 'comerciais',
                'quintoandar' => 'comercial',
                'mercadolivre' => 'imovel-comercial',
            ],
            default => [
                'query' => 'apartamento',
                'zap' => 'apartamentos',
                'vivareal' => 'apartamento_residencial',
                'olx' => 'apartamentos',
                'imovelweb' => 'apartamentos',
                'chaves' => 'apartamentos',
                'casamineira' => 'apartamento',
                'loft' => 'apartamentos',
                'quintoandar' => 'apartamento',
                'mercadolivre' => 'apartamento',
            ],
        };
    }

    private function slug(string $value): string
    {
        return Str::of($value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '-')->trim('-')->toString();
    }
}
