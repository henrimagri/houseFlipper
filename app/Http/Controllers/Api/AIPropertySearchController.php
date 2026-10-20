<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PropertyPortalScraper;
use App\Services\PropertyValuationService;
use App\Support\ExternalImageUrlPolicy;
use App\Support\ExternalUrlPolicy;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class AIPropertySearchController extends Controller
{
    private const MAX_PROVIDER_RESPONSE_BYTES = 2_097_152;

    public function __construct(
        private readonly PropertyPortalScraper $scraper,
        private readonly PropertyValuationService $valuationService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'city' => ['nullable', 'string', 'max:120'],
            'neighborhood' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'size:2'],
            'property_type' => ['nullable', 'string', 'in:apartment,house,commercial'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'min_discount' => ['nullable', 'numeric', 'min:0', 'max:90'],
            'min_bedrooms' => ['nullable', 'integer', 'min:0', 'max:20'],
            'min_bathrooms' => ['nullable', 'integer', 'min:0', 'max:20'],
            'min_parking' => ['nullable', 'integer', 'min:0', 'max:20'],
            'min_area_m2' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:250'],
        ]);

        $city = trim($data['city'] ?? '');
        $neighborhood = trim($data['neighborhood'] ?? '');
        $state = strtoupper(trim($data['state'] ?? 'SP')) ?: 'SP';
        $propertyType = $data['property_type'] ?? 'apartment';
        $propertyLabel = $this->propertySearchLabel($propertyType);
        $query = trim($propertyLabel.' venda '.$neighborhood.' '.$city.' '.$state);
        $limit = min(250, (int) ($data['limit'] ?? 250));

        $scraped = $this->scraper->scrape($city, $neighborhood, $limit, $state, $propertyType);
        $scraperDiagnostics = $this->publicDiagnostics($scraped['diagnostics'] ?? []);
        $providerResponses = collect();
        if (count($scraped['items'])) {
            $providerResponses->push(['provider' => 'scraper', 'name' => $scraped['source_name'], 'items' => $scraped['items'], 'diagnostics' => $scraperDiagnostics]);
        }

        $mercadoLivre = $this->fetchMercadoLivre($query, min(50, $limit));
        if ($mercadoLivre !== null) {
            $mercadoLivre['diagnostics'] = [['source' => 'Mercado Livre', 'state' => 'connected', 'found' => count($mercadoLivre['items']), 'status' => 200]];
            $providerResponses->push($mercadoLivre);
        }

        if ($providerResponses->isEmpty() && ($nestoria = $this->fetchNestoria($city, $neighborhood, $limit, $state, $propertyType)) !== null) {
            $providerResponses->push($nestoria);
        }

        if ($providerResponses->isEmpty()) {
            return response()->json([
                'message' => 'Nenhuma fonte de anúncios respondeu agora.',
                'details' => 'Tente novamente em instantes ou configure uma credencial de conector oficial.',
                'diagnostics' => $scraperDiagnostics,
            ], 502);
        }

        $normalizedItems = $providerResponses->flatMap(function (array $providerResponse) use ($city, $neighborhood, $state) {
            return collect($providerResponse['items'])->map(function (array $item) use ($providerResponse, $city, $neighborhood, $state): array {
                    $normalized = match ($providerResponse['provider']) {
                        'scraper' => $item,
                        'nestoria' => $this->normalizeNestoriaItem($item, $city, $neighborhood, $state),
                        default => $this->normalizeMercadoLivreItem($item, $city, $neighborhood, $state),
                    };
                    $normalized['source_url'] = $this->validHttpUrl($normalized['source_url'] ?? null);
                    $normalized['thumbnail'] = $this->validImageUrl($normalized['thumbnail'] ?? null);

                    return $normalized;
                });
        })->unique(fn (array $item) => $item['source_url'] ?: $item['source'].'|'.$item['title'].'|'.$item['asking_price'])->values();
        $typeMatchedItems = $normalizedItems->filter(fn (array $item) => $this->matchesRequestedPropertyType($item, $propertyType));
        $locationMatchedItems = $typeMatchedItems->filter(fn (array $item) => $this->matchesRequestedLocation($item, $city, $neighborhood, $state));
        $pricedItems = $locationMatchedItems->filter(fn (array $item) => $item['asking_price'] > 0);
        $providerDiagnostics = $providerResponses
            ->flatMap(fn (array $response) => $this->publicDiagnostics($response['diagnostics'] ?? []))
            ->values()
            ->all();
        $sourceDiagnostics = $pricedItems->groupBy('source')->map(fn ($sourceItems, string $source) => [
            'source' => $source,
            'state' => 'connected',
            'found' => $sourceItems->count(),
        ])->values()->all();
        $pipelineDiagnostics = [
            ['source' => 'Itens coletados', 'found' => $normalizedItems->count()],
            ['source' => 'Tipo compatível', 'found' => $typeMatchedItems->count()],
            ['source' => 'Local compatível', 'found' => $locationMatchedItems->count()],
            ['source' => 'Preço válido', 'found' => $pricedItems->count()],
        ];

        $items = $pricedItems
            ->map(function (array $item) use ($propertyType) {
                $item['property_type'] = $propertyType;

                return $item;
            })
            ->pipe(fn ($items) => $this->valuationService->applyComparableMarketEstimates($items))
            ->when(isset($data['max_price']) && (float) $data['max_price'] > 0, fn ($items) => $items->filter(fn (array $item) => $item['asking_price'] <= (float) $data['max_price']))
            ->when(isset($data['min_discount']) && (float) $data['min_discount'] > 0, fn ($items) => $items->filter(fn (array $item) => $item['discount_percentage'] >= (float) $data['min_discount']))
            ->when(isset($data['min_bedrooms']) && (int) $data['min_bedrooms'] > 0, fn ($items) => $items->filter(fn (array $item) => (int) ($item['bedrooms'] ?? 0) >= (int) $data['min_bedrooms']))
            ->when(isset($data['min_bathrooms']) && (int) $data['min_bathrooms'] > 0, fn ($items) => $items->filter(fn (array $item) => (int) ($item['bathrooms'] ?? 0) >= (int) $data['min_bathrooms']))
            ->when(isset($data['min_parking']) && (int) $data['min_parking'] > 0, fn ($items) => $items->filter(fn (array $item) => (int) ($item['parking_spaces'] ?? 0) >= (int) $data['min_parking']))
            ->when(isset($data['min_area_m2']) && (float) $data['min_area_m2'] > 0, fn ($items) => $items->filter(fn (array $item) => (float) ($item['area_m2'] ?? 0) >= (float) $data['min_area_m2']))
            ->sortByDesc('score')
            ->values();

        $stats = [
            'total' => $items->count(),
            'best_score' => (int) ($items->max('score') ?? 0),
            'average_discount' => round((float) $items->avg('discount_percentage'), 2),
            'average_price' => round((float) $items->avg('asking_price'), 2),
        ];

        return response()->json([
            'data' => $items,
            'valuation_model' => ['version' => PropertyValuationService::VERSION, 'type' => 'rules_and_comparables', 'trained_statistical_model' => false],
            'stats' => $stats,
            'source' => [
                'name' => $providerResponses->pluck('name')->unique()->implode(' + '),
                'mode' => $providerResponses->count() > 1 ? 'aggregate' : ($providerResponses->first()['provider'] === 'scraper' ? 'web_scraper' : 'public_api'),
                'query' => $query,
                'diagnostics' => array_merge($providerDiagnostics, $sourceDiagnostics, $pipelineDiagnostics),
            ],
        ]);
    }

    private function fetchNestoria(string $city, string $neighborhood, int $limit, string $state, string $propertyType): ?array
    {
        $places = collect([
            trim($neighborhood.' '.$city),
            $neighborhood,
            $city,
            $city ? trim($city.' '.$state) : null,
            'São Paulo',
        ])->filter()->unique()->values();

        $listings = collect();

        foreach ($places as $place) {
            if ($listings->count() >= $limit) {
                break;
            }

            try {
                $response = $this->guardedProviderRequest()
                    ->acceptJson()
                    ->timeout(10)
                    ->get('https://api.nestoria.com.br/api', [
                        'country' => 'br',
                        'action' => 'search_listings',
                        'encoding' => 'json',
                        'listing_type' => 'buy',
                        'property_type' => $propertyType === 'house' ? 'house' : 'flat',
                        'place_name' => $place,
                        'number_of_results' => $limit,
                    ]);
                $this->assertProviderResponseSize($response);
            } catch (\Throwable) {
                continue;
            }

            $items = $response->json('response.listings', []);
            if ($response->successful() && ! empty($items)) {
                $listings = $listings
                    ->merge($items)
                    ->unique(fn (array $item) => $item['lister_url'] ?? $item['guid'] ?? $item['title'] ?? Str::uuid())
                    ->take($limit)
                    ->values();
            }
        }

        if ($listings->isEmpty()) {
            return null;
        }

        return ['provider' => 'nestoria', 'name' => 'Nestoria/Lifull Brasil', 'items' => $listings->all()];
    }

    private function fetchMercadoLivre(string $query, int $limit): ?array
    {
        try {
            $request = $this->guardedProviderRequest()
                ->acceptJson()
                ->withHeaders(['User-Agent' => 'InvestFlip/1.0'])
                ->timeout(10);

            if ($token = config('services.mercadolivre.token')) {
                $request = $request->withToken($token);
            }

            $response = $request->get('https://api.mercadolibre.com/sites/MLB/search', [
                'q' => $query ?: 'imóvel venda',
                'limit' => $limit,
                'sort' => 'relevance',
            ]);
            $this->assertProviderResponseSize($response);
        } catch (\Throwable) {
            return null;
        }

        $results = $response->json('results', []);
        if (! $response->successful() || empty($results)) {
            return null;
        }

        return ['provider' => 'mercadolivre', 'name' => 'Mercado Livre Brasil', 'items' => $results];
    }

    private function normalizeNestoriaItem(array $item, string $city, string $neighborhood, string $state): array
    {
        $rawPrice = (float) ($item['price'] ?? 0);
        $area = (float) ($item['size'] ?? 0);
        $bedrooms = (int) ($item['bedroom_number'] ?? 0);
        $bathrooms = (int) ($item['bathroom_number'] ?? 0);
        $marketPrice = $this->estimateMarketPrice($rawPrice, $area, $item['title'] ?? '');
        $discount = $marketPrice > 0 ? max(0, ($marketPrice - $rawPrice) / $marketPrice * 100) : 0;
        $score = $this->score($discount, $area, $bedrooms, $rawPrice);

        return [
            'id' => (string) ($item['lister_url'] ?? $item['guid'] ?? Str::uuid()),
            'title' => $item['title'] ?? 'Imóvel anunciado',
            'address' => $item['datasource_name'] ?? $neighborhood,
            'city' => $city ?: ($item['summary'] ?? ''),
            'neighborhood' => $neighborhood ?: ($item['keywords'] ?? ''),
            'state' => $state,
            'source' => 'Nestoria/Lifull',
            'source_key' => 'other',
            'source_url' => $item['lister_url'] ?? null,
            'thumbnail' => $this->validImageUrl($item['img_url'] ?? null),
            'asking_price' => round($rawPrice, 2),
            'estimated_market_price' => round($marketPrice, 2),
            'discount_percentage' => round($discount, 2),
            'area_m2' => $area ?: null,
            'bedrooms' => $bedrooms ?: null,
            'bathrooms' => $bathrooms ?: null,
            'parking_spaces' => null,
            'price_per_m2' => $area > 0 ? round($rawPrice / $area, 2) : null,
            'score' => $score,
            'signal' => $score >= 80 ? 'Boa oportunidade' : ($score >= 65 ? 'Negociar' : 'Atenção'),
        ];
    }

    private function normalizeMercadoLivreItem(array $item, string $city, string $neighborhood, string $state): array
    {
        $attributes = collect($item['attributes'] ?? [])->keyBy('id');
        $sellerAddress = $item['seller_address'] ?? [];
        $location = $sellerAddress['search_location'] ?? [];
        $rawPrice = (float) ($item['price'] ?? 0);
        $area = $this->numericAttribute($attributes, ['COVERED_AREA', 'TOTAL_AREA', 'PROPERTY_TOTAL_AREA', 'LIVING_AREA']);
        $bedrooms = (int) $this->numericAttribute($attributes, ['BEDROOMS', 'ROOMS']);
        $bathrooms = (int) $this->numericAttribute($attributes, ['FULL_BATHROOMS', 'BATHROOMS']);
        $parking = (int) $this->numericAttribute($attributes, ['PARKING_LOTS', 'GARAGES']);
        $marketPrice = $this->estimateMarketPrice($rawPrice, $area, $item['title'] ?? '');
        $discount = $marketPrice > 0 ? max(0, ($marketPrice - $rawPrice) / $marketPrice * 100) : 0;
        $score = $this->score($discount, $area, $bedrooms, $rawPrice);

        return [
            'id' => (string) ($item['id'] ?? Str::uuid()),
            'title' => $item['title'] ?? 'Imóvel anunciado',
            'address' => Arr::get($sellerAddress, 'address_line') ?: Arr::get($location, 'neighborhood.name') ?: $neighborhood,
            'city' => Arr::get($location, 'city.name') ?: Arr::get($sellerAddress, 'city.name') ?: $city,
            'neighborhood' => Arr::get($location, 'neighborhood.name') ?: $neighborhood,
            'state' => Arr::get($location, 'state.id') ? Str::after(Arr::get($location, 'state.id'), 'BR-') : $state,
            'source' => 'Mercado Livre',
            'source_key' => 'other',
            'source_url' => $item['permalink'] ?? null,
            'thumbnail' => $this->validImageUrl($item['thumbnail'] ?? null),
            'asking_price' => round($rawPrice, 2),
            'estimated_market_price' => round($marketPrice, 2),
            'discount_percentage' => round($discount, 2),
            'area_m2' => $area ?: null,
            'bedrooms' => $bedrooms ?: null,
            'bathrooms' => $bathrooms ?: null,
            'parking_spaces' => $parking ?: null,
            'price_per_m2' => $area > 0 ? round($rawPrice / $area, 2) : null,
            'score' => $score,
            'signal' => $score >= 80 ? 'Boa oportunidade' : ($score >= 65 ? 'Negociar' : 'Atenção'),
        ];
    }

    private function numericAttribute($attributes, array $keys): float
    {
        foreach ($keys as $key) {
            $attribute = $attributes->get($key);
            $value = $attribute['value_struct']['number'] ?? $attribute['value_name'] ?? null;
            if ($value !== null && preg_match('/[\d,.]+/', (string) $value, $matches)) {
                return (float) str_replace(',', '.', str_replace('.', '', $matches[0]));
            }
        }

        return 0;
    }

    private function estimateMarketPrice(float $askingPrice, float $area, string $title): float
    {
        $renovationWords = Str::contains(Str::lower($title), ['reforma', 'reformar', 'antigo', 'oportunidade']);
        $uplift = $renovationWords ? 1.22 : 1.14;
        if ($area >= 70) {
            $uplift += 0.04;
        }

        return $askingPrice > 0 ? $askingPrice * $uplift : 0;
    }

    private function score(float $discount, float $area, int $bedrooms, float $askingPrice): int
    {
        $score = 45 + min(35, $discount * 1.4);
        $score += $area >= 50 && $area <= 110 ? 8 : 0;
        $score += $bedrooms >= 2 ? 6 : 0;
        $score += $askingPrice > 0 && $askingPrice <= 700000 ? 6 : 0;

        return (int) max(0, min(100, round($score)));
    }

    private function validImageUrl(mixed $url): ?string
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

        if (Str::startsWith($url, '//')) {
            $url = 'https:'.$url;
        }

        return ExternalImageUrlPolicy::allows($url) ? $url : null;
    }

    private function validHttpUrl(mixed $url): ?string
    {
        $url = trim((string) $url);

        return ExternalUrlPolicy::allows($url) ? $url : null;
    }

    /**
     * Keep upstream diagnostics useful without returning exception text, URLs or
     * filesystem paths supplied by external processes and providers.
     */
    private function publicDiagnostics(mixed $diagnostics): array
    {
        if (! is_array($diagnostics)) {
            return [];
        }

        return collect($diagnostics)
            ->filter(fn (mixed $diagnostic): bool => is_array($diagnostic))
            ->take(25)
            ->map(function (array $diagnostic): array {
                $source = Str::of((string) ($diagnostic['source'] ?? 'Fonte externa'))
                    ->replaceMatches('/[\x00-\x1F\x7F]+/u', ' ')
                    ->squish()
                    ->limit(80, '')
                    ->toString();

                $public = ['source' => $source !== '' ? $source : 'Fonte externa'];

                if (isset($diagnostic['status']) && is_numeric($diagnostic['status'])) {
                    $public['status'] = max(0, min(599, (int) $diagnostic['status']));
                }

                if (isset($diagnostic['found']) && is_numeric($diagnostic['found'])) {
                    $public['found'] = max(0, min(250, (int) $diagnostic['found']));
                }

                if (in_array($diagnostic['state'] ?? null, ['connected', 'not_configured', 'local_fallback', 'unavailable'], true)) {
                    $public['state'] = $diagnostic['state'];
                }

                if (array_key_exists('message', $diagnostic)) {
                    $public['message'] = 'Fonte externa indisponível.';
                }

                return $public;
            })
            ->values()
            ->all();
    }

    private function guardedProviderRequest(): PendingRequest
    {
        return Http::withHeaders(['Accept-Encoding' => 'identity'])->withOptions([
            'verify' => true,
            'allow_redirects' => false,
            'decode_content' => false,
            'on_headers' => function (ResponseInterface $response): void {
                $length = trim($response->getHeaderLine('Content-Length'));
                if ($length !== '' && ctype_digit($length) && (int) $length > self::MAX_PROVIDER_RESPONSE_BYTES) {
                    throw new RuntimeException('Resposta externa excede o limite permitido.');
                }
            },
            'progress' => function (int $downloadTotal, int $downloadedBytes): void {
                if ($downloadTotal > self::MAX_PROVIDER_RESPONSE_BYTES || $downloadedBytes > self::MAX_PROVIDER_RESPONSE_BYTES) {
                    throw new RuntimeException('Resposta externa excede o limite permitido.');
                }
            },
        ]);
    }

    private function assertProviderResponseSize(Response $response): void
    {
        if (strlen($response->body()) > self::MAX_PROVIDER_RESPONSE_BYTES) {
            throw new RuntimeException('Resposta externa excede o limite permitido.');
        }
    }

    private function matchesRequestedPropertyType(array $item, string $propertyType): bool
    {
        $text = Str::of(implode(' ', [
            $item['title'] ?? '',
            $item['address'] ?? '',
        ]))->lower()->ascii()->toString();

        return match ($propertyType) {
            'house' => Str::contains($text, ['casa', 'sobrado', 'condominio fechado']),
            'commercial' => Str::contains($text, ['comercial', 'sala', 'loja', 'galpao', 'predio comercial']),
            default => ! Str::contains($text, ['sobrado', 'casa ', ' casa', 'terreno', 'lote', 'comercial', 'sala comercial', 'loja', 'galpao']),
        };
    }

    private function matchesRequestedLocation(array $item, string $city, string $neighborhood, string $state): bool
    {
        $text = Str::of(implode(' ', [
            $item['title'] ?? '',
            $item['address'] ?? '',
            $item['neighborhood'] ?? '',
            $item['city'] ?? '',
            $item['state'] ?? '',
            $item['source_url'] ?? '',
        ]))->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();

        $neighborhoodNeedle = Str::of($neighborhood)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();
        $cityNeedle = Str::of($city)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();
        $stateNeedle = Str::of($state)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();

        if ($neighborhoodNeedle !== '') {
            return Str::contains($text, $neighborhoodNeedle);
        }

        if ($cityNeedle !== '') {
            return Str::contains($text, $cityNeedle) || ($stateNeedle !== '' && Str::contains($text, $stateNeedle));
        }

        return true;
    }

    private function propertySearchLabel(string $propertyType): string
    {
        return match ($propertyType) {
            'house' => 'casa',
            'commercial' => 'imóvel comercial',
            default => 'apartamento',
        };
    }
}
