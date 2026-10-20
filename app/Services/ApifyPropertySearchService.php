<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ApifyPropertySearchService
{
    private const ENDPOINT = 'https://api.apify.com/v2/acts/%s/run-sync-get-dataset-items';

    public function search(string $city, string $neighborhood, string $state, string $propertyType, int $limit): array
    {
        $providers = collect(config('services.apify.providers', []));
        $token = trim((string) config('services.apify.token'));

        if ($token === '') {
            return [
                'items' => [],
                'diagnostics' => $providers->map(fn (array $provider, string $key) => $this->diagnostic(
                    $provider['label'],
                    0,
                    empty($provider['actor_id']) ? 'local_fallback' : 'not_configured'
                ))->values()->all(),
            ];
        }

        $runnable = $providers->filter(fn (array $provider) => trim((string) ($provider['actor_id'] ?? '')) !== '');
        $perProvider = min((int) config('services.apify.max_results_per_provider', 25), max(1, $limit));
        try {
            $responses = Http::pool(function (Pool $pool) use ($runnable, $token, $city, $neighborhood, $state, $propertyType, $perProvider) {
                return $runnable->mapWithKeys(function (array $provider, string $key) use ($pool, $token, $city, $neighborhood, $state, $propertyType, $perProvider) {
                    $actorId = str_replace('/', '~', trim((string) $provider['actor_id']));

                    return [$key => $pool->as($key)
                        ->withToken($token)
                        ->acceptJson()
                        ->withOptions(['allow_redirects' => false, 'verify' => true])
                        ->timeout((int) config('services.apify.request_timeout', 55))
                        ->post(sprintf(self::ENDPOINT, rawurlencode($actorId)).'?timeout='.(int) config('services.apify.actor_timeout', 45), $this->inputFor(
                            $key, $city, $neighborhood, $state, $propertyType, $perProvider
                        ))];
                })->all();
            });
        } catch (\Throwable) {
            $responses = [];
        }

        $items = collect();
        $diagnostics = collect();

        foreach ($providers as $key => $provider) {
            $label = (string) $provider['label'];
            if (empty($provider['actor_id'])) {
                $diagnostics->push($this->diagnostic($label, 0, 'local_fallback'));
                continue;
            }

            $response = $responses[$key] ?? null;
            if (! $response instanceof Response || ! $response->successful()) {
                $diagnostics->push($this->diagnostic($label, 0, 'unavailable', $response instanceof Response ? $response->status() : 0));
                continue;
            }

            $rows = $response->json();
            $rows = is_array($rows) && array_is_list($rows) ? $rows : [];
            $normalized = collect($rows)
                ->filter(fn (mixed $row) => is_array($row))
                ->map(fn (array $row) => $this->normalize($row, $key, $label, $city, $neighborhood, $state))
                ->filter(fn (?array $row) => $row !== null)
                ->take($perProvider);

            $items = $items->merge($normalized);
            $diagnostics->push($this->diagnostic($label, $normalized->count(), 'connected', $response->status()));
        }

        return ['items' => $items->values()->all(), 'diagnostics' => $diagnostics->values()->all()];
    }

    private function inputFor(string $provider, string $city, string $neighborhood, string $state, string $propertyType, int $limit): array
    {
        $location = trim(implode(' ', array_filter([$neighborhood, $city, $state])));
        $url = $this->portalUrl($provider, $city, $neighborhood, $state, $propertyType);

        return match ($provider) {
            'zap' => array_filter([
                'type' => 'SALE',
                'city' => $city,
                'state' => $this->stateName($state),
                'page' => 1,
                'unitType' => match ($propertyType) { 'house' => 'HOME', 'commercial' => 'BUSINESS', default => 'APARTMENT' },
                'unitUsage' => $propertyType === 'commercial' ? 'COMMERCIAL' : 'RESIDENTIAL',
            ], fn (mixed $value) => $value !== ''),
            'vivareal' => [
                'searchTerm' => trim(implode(', ', array_filter([$neighborhood, $city]))),
                'business' => 'SALE',
                'unitType' => match ($propertyType) { 'house' => 'HOME', 'commercial' => 'ANY', default => 'APARTMENT' },
                'maxListings' => $limit,
                'withDetails' => false,
            ],
            'loft' => ['city' => Str::slug($city), 'searchTerm' => $neighborhood, 'transaction' => 'sale', 'maxItems' => $limit],
            'quintoandar' => [
                'operation' => 'buy',
                'location' => trim("$city, $state", ', '),
                'propertyType' => match ($propertyType) { 'house' => 'house', 'commercial' => 'any', default => 'apartment' },
                'maxResults' => $limit,
                'includePhotos' => false,
            ],
            'facebook' => ['startUrls' => [['url' => $url]], 'resultsLimit' => $limit],
            default => ['startUrls' => [['url' => $url]], 'maxItems' => $limit, 'search' => $location],
        };
    }

    private function portalUrl(string $provider, string $city, string $neighborhood, string $state, string $propertyType): string
    {
        $citySlug = Str::slug($city);
        $neighborhoodSlug = Str::slug($neighborhood);
        $stateSlug = Str::lower($state);
        $dashPlace = $neighborhoodSlug !== '' ? "$neighborhoodSlug-$citySlug" : "$citySlug-$stateSlug";
        $type = match ($propertyType) { 'house' => 'casas', 'commercial' => 'comerciais', default => 'apartamentos' };

        return match ($provider) {
            'vivareal' => "https://www.vivareal.com.br/venda/$stateSlug/$citySlug/",
            'imovelweb' => "https://www.imovelweb.com.br/$type-venda-$dashPlace.html",
            'casamineira' => "https://www.casamineira.com.br/venda/".$this->propertyLabel($propertyType)."/{$citySlug}_{$stateSlug}",
            'facebook' => 'https://www.facebook.com/marketplace/search/?query='.rawurlencode($this->propertyLabel($propertyType).' venda '.trim("$neighborhood $city $state")),
            default => '',
        };
    }

    private function normalize(array $row, string $key, string $label, string $city, string $neighborhood, string $state): ?array
    {
        $listing = is_array($row['listing'] ?? null) ? $row['listing'] : (is_array($row['content'] ?? null) ? $row['content'] : $row);
        $price = $this->number($this->first($listing, ['price', 'pricing.amount', 'pricingInfos.0.price', 'business.salePrice', 'listingPrice.amount', 'salePrice', 'amount', 'formattedPrice']));
        if ($price <= 0) return null;

        $address = $this->first($listing, ['address.formattedAddress', 'address.street', 'address', 'location.address', 'locationText', 'street', 'subtitle']);
        $itemCity = $this->first($listing, ['address.city', 'location.city', 'city', 'location.cityName']) ?: $city;
        $itemNeighborhood = $this->first($listing, ['address.neighborhood', 'location.neighborhood', 'neighborhood', 'district']) ?: $neighborhood;
        $itemState = $this->first($listing, ['address.stateAcronym', 'address.state', 'location.state', 'state', 'region']) ?: $state;
        $url = $this->first($row, ['url', 'link', 'listingUrl', 'propertyUrl', 'facebookUrl'])
            ?: $this->first($listing, ['url', 'link', 'permalink']);
        $image = $this->first($row, ['imageUrl', 'thumbnail', 'image', 'photo', 'photos.0', 'medias.0.url'])
            ?: $this->first($listing, ['imageUrl', 'thumbnail', 'coverImage', 'images.0', 'photos.0', 'medias.0.url']);

        return [
            'title' => (string) ($this->first($listing, ['title', 'name', 'headline', 'description']) ?: 'Imóvel anunciado'),
            'text' => json_encode($listing, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'address' => is_scalar($address) ? (string) $address : trim("$itemNeighborhood, $itemCity"),
            'city' => is_scalar($itemCity) ? (string) $itemCity : $city,
            'neighborhood' => is_scalar($itemNeighborhood) ? (string) $itemNeighborhood : $neighborhood,
            'state' => is_scalar($itemState) ? (string) $itemState : $state,
            'source' => $label,
            'source_key' => $this->sourceKey($key),
            'source_url' => is_string($url) ? $url : null,
            'thumbnail' => is_string($image) ? $image : null,
            'asking_price' => $price,
            'area_m2' => $this->number($this->first($listing, ['usableAreas.0', 'area', 'areaM2', 'area_m2', 'usableArea', 'totalArea', 'size'])),
            'bedrooms' => (int) $this->number($this->first($listing, ['bedrooms.0', 'bedrooms', 'bedroomCount', 'rooms'])),
            'bathrooms' => (int) $this->number($this->first($listing, ['bathrooms.0', 'bathrooms', 'bathroomCount'])),
            'parking_spaces' => (int) $this->number($this->first($listing, ['parkingSpaces.0', 'parkingSpaces', 'parking_spaces', 'parking', 'garageSpaces'])),
        ];
    }

    private function first(array $data, array $paths): mixed
    {
        foreach ($paths as $path) {
            $value = Arr::get($data, $path);
            if ($value !== null && $value !== '' && $value !== []) return $value;
        }
        return null;
    }

    private function number(mixed $value): float
    {
        if (is_array($value)) $value = reset($value);
        if (is_numeric($value)) return (float) $value;
        $digits = preg_replace('/[^\d,.-]/', '', (string) $value);
        if ($digits === '') return 0;
        if (str_contains($digits, ',')) {
            $digits = str_replace(',', '.', str_replace('.', '', $digits));
        } elseif (preg_match('/^\d{1,3}(?:\.\d{3})+$/', $digits)) {
            $digits = str_replace('.', '', $digits);
        }
        return (float) $digits;
    }

    private function diagnostic(string $source, int $found, string $state, int $status = 0): array
    {
        return array_filter(['source' => $source, 'found' => $found, 'state' => $state, 'status' => $status]);
    }

    private function sourceKey(string $key): string
    {
        return match ($key) { 'zap' => 'zap', 'vivareal' => 'vivareal', 'quintoandar' => 'quintoandar', 'facebook' => 'facebook', 'casamineira' => 'realtor', default => 'other' };
    }

    private function propertyLabel(string $type): string
    {
        return match ($type) { 'house' => 'casa', 'commercial' => 'imovel-comercial', default => 'apartamento' };
    }

    private function stateName(string $state): string
    {
        return [
            'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas', 'BA' => 'Bahia',
            'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo', 'GO' => 'Goiás',
            'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul', 'MG' => 'Minas Gerais',
            'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná', 'PE' => 'Pernambuco', 'PI' => 'Piauí',
            'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte', 'RS' => 'Rio Grande do Sul',
            'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina', 'SP' => 'São Paulo',
            'SE' => 'Sergipe', 'TO' => 'Tocantins',
        ][$state] ?? $state;
    }
}
