<?php

namespace App\Services;

use App\Support\ExternalUrlPolicy;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class NeighborhoodContextService
{
    private const RADIUS_METERS = 4000;

    private const MAX_EXTERNAL_RESPONSE_BYTES = 2_097_152;

    public function analyze(array $subject): array
    {
        $queries = $this->queryCandidatesFrom($subject);
        $query = $queries[0] ?? 'Brasil';

        if ($key = config('services.google_maps.key')) {
            $google = $this->fromGoogle($query, $key);
            if (($google['status'] ?? null) === 'connected') {
                return $google;
            }

            $fallback = $this->fromOpenStreetMap($queries);
            $fallback['google_status'] = $google;

            return $fallback;
        }

        return $this->fromOpenStreetMap($queries);
    }

    private function fromGoogle(string $query, string $key): array
    {
        try {
            $geocode = $this->guardedHttp()->timeout(5)
                ->acceptJson()
                ->get($this->assertPublicHttpsEndpoint('https://maps.googleapis.com/maps/api/geocode/json'), [
                    'address' => $query,
                    'region' => 'br',
                    'key' => $key,
                ]);
            $this->assertResponseSize($geocode);

            if (! $geocode->successful() || $geocode->json('status') !== 'OK') {
                $message = $geocode->json('error_message') ?: 'Google Maps não retornou coordenadas para o bairro/endereço pesquisado.';

                return $this->unavailable('google_places', $message);
            }

            $location = $geocode->json('results.0.geometry.location');
            $lat = (float) data_get($location, 'lat');
            $lng = (float) data_get($location, 'lng');

            $categories = [
                'transport' => ['label' => 'Transporte', 'types' => ['subway_station', 'train_station', 'transit_station']],
                'education' => ['label' => 'Educação', 'types' => ['school', 'university']],
                'health' => ['label' => 'Saúde', 'types' => ['hospital', 'doctor', 'pharmacy']],
                'shopping' => ['label' => 'Mercado e comércio', 'types' => ['supermarket', 'shopping_mall']],
                'leisure' => ['label' => 'Lazer', 'types' => ['park', 'gym']],
            ];

            $groups = [];
            foreach ($categories as $keyName => $category) {
                $places = collect();
                foreach ($category['types'] as $type) {
                    $response = $this->guardedHttp()->timeout(5)
                        ->acceptJson()
                        ->get($this->assertPublicHttpsEndpoint('https://maps.googleapis.com/maps/api/place/nearbysearch/json'), [
                            'location' => $lat.','.$lng,
                            'radius' => self::RADIUS_METERS,
                            'type' => $type,
                            'key' => $key,
                            'language' => 'pt-BR',
                        ]);
                    $this->assertResponseSize($response);

                    if ($response->successful()) {
                        $places = $places->merge($response->json('results', []));
                    }
                }

                $groups[] = [
                    'key' => $keyName,
                    'label' => $category['label'],
                    'count' => $places->unique('place_id')->count(),
                    'places' => $places
                        ->unique('place_id')
                        ->map(fn (array $place) => [
                            'name' => data_get($place, 'name'),
                            'type' => $this->humanType((array) data_get($place, 'types', [])),
                            'distance_m' => $this->distanceMeters($lat, $lng, (float) data_get($place, 'geometry.location.lat'), (float) data_get($place, 'geometry.location.lng')),
                            'rating' => data_get($place, 'rating'),
                        ])
                        ->filter(fn (array $place) => $place['name'])
                        ->sortBy('distance_m')
                        ->take(5)
                        ->values()
                        ->all(),
                ];
            }

            return $this->connected('google_places', 'Google Maps/Places', $query, $lat, $lng, $groups);
        } catch (\Throwable $exception) {
            return $this->unavailable('google_places', 'Serviço externo indisponível.');
        }
    }

    private function fromOpenStreetMap(array $queries): array
    {
        try {
            [$query, $first] = $this->geocodeOpenStreetMap($queries);

            if (! $first) {
                $known = $this->knownCoordinateFallback($queries);
                if (! $known) {
                    return $this->unavailable('openstreetmap', 'Não foi possível localizar coordenadas para o bairro/endereço pesquisado nesta tentativa.');
                }

                [$query, $first] = $known;
            }

            $lat = (float) data_get($first, 'lat');
            $lng = (float) data_get($first, 'lon');
            $cacheKey = $this->cacheKey($query, $lat, $lng);
            $elements = $this->fetchOverpassElements($lat, $lng);

            $groups = $this->emptyLocationGroups()->map(function (array $group) use ($elements, $lat, $lng) {
                $places = $elements
                    ->map(fn (array $element) => $this->normalizeOsmPlace($element, $lat, $lng))
                    ->filter(fn (?array $place) => $place && $place['category'] === $group['key'])
                    ->unique(fn (array $place) => $place['name'].'|'.$place['distance_m'])
                    ->sortBy('distance_m')
                    ->values();

                return [
                    'key' => $group['key'],
                    'label' => $group['label'],
                    'count' => $places->count(),
                    'places' => $places->take(5)->values()->all(),
                ];
            })->values()->all();

            $total = collect($groups)->sum(fn (array $group) => (int) ($group['count'] ?? 0));
            if ($total === 0) {
                $groups = $this->fetchNominatimNearbyGroups($query, $lat, $lng);
                $total = collect($groups)->sum(fn (array $group) => (int) ($group['count'] ?? 0));

                if ($total > 0) {
                    $connected = $this->connected('openstreetmap', 'OpenStreetMap/Nominatim', $query, $lat, $lng, $groups);
                    $connected['message'] = 'Contexto do entorno carregado por busca textual no OpenStreetMap. O Overpass não retornou pontos nesta tentativa.';
                    $connected['fallback_source'] = 'nominatim_text_search';
                    $this->putCachedContext($cacheKey, $connected);

                    return $connected;
                }

                $cached = $this->getCachedContext($cacheKey);
                if (is_array($cached) && collect($cached['groups'] ?? [])->sum(fn (array $group) => (int) ($group['count'] ?? 0)) > 0) {
                    $cached['message'] = 'Contexto do entorno recuperado da última coleta válida. O servidor Overpass oscilou nesta tentativa.';
                    $cached['from_cache'] = true;

                    return $cached;
                }

                return array_merge($this->unavailable(
                    'openstreetmap',
                    'A localização foi identificada, mas nenhuma fonte respondeu com pontos próximos verificáveis nesta tentativa.'
                ), [
                    'query' => $query,
                    'lat' => $lat,
                    'lng' => $lng,
                ]);
            }

            $connected = $this->connected('openstreetmap', 'OpenStreetMap', $query, $lat, $lng, $groups);
            $this->putCachedContext($cacheKey, $connected);

            return $connected;
        } catch (\Throwable $exception) {
            return $this->unavailable('openstreetmap', 'Serviço externo indisponível.');
        }
    }

    private function geocodeOpenStreetMap(array $queries): array
    {
        foreach ($queries as $query) {
            $query = trim((string) $query);
            if ($query === '') {
                continue;
            }

            try {
                $geocode = $this->guardedHttp()->timeout(5)
                    ->acceptJson()
                    ->withUserAgent('InvestFlip/1.0 contato@investflip.local')
                    ->get($this->assertPublicHttpsEndpoint(config('services.openstreetmap.nominatim_endpoint')), [
                        'q' => $query,
                        'format' => 'json',
                        'limit' => 1,
                        'countrycodes' => 'br',
                        'addressdetails' => 1,
                    ]);
                $this->assertResponseSize($geocode);

                if ($geocode->successful() && ! empty($geocode->json())) {
                    return [$query, $geocode->json('0')];
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return [$queries[0] ?? null, null];
    }

    private function knownCoordinateFallback(array $queries): ?array
    {
        $text = Str::of(implode(' ', $queries))->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();
        $known = [
            'moema sao paulo' => [-23.6038, -46.6622, 'Moema, São Paulo, SP, Brasil'],
            'perdizes sao paulo' => [-23.5374, -46.6804, 'Perdizes, São Paulo, SP, Brasil'],
            'pinheiros sao paulo' => [-23.5663, -46.7019, 'Pinheiros, São Paulo, SP, Brasil'],
            'vila mariana sao paulo' => [-23.5896, -46.6349, 'Vila Mariana, São Paulo, SP, Brasil'],
            'itaim bibi sao paulo' => [-23.5848, -46.6789, 'Itaim Bibi, São Paulo, SP, Brasil'],
            'brooklin sao paulo' => [-23.6267, -46.6873, 'Brooklin, São Paulo, SP, Brasil'],
            'campo belo sao paulo' => [-23.6261, -46.6677, 'Campo Belo, São Paulo, SP, Brasil'],
            'santana sao paulo' => [-23.5020, -46.6255, 'Santana, São Paulo, SP, Brasil'],
            'tatuape sao paulo' => [-23.5403, -46.5764, 'Tatuapé, São Paulo, SP, Brasil'],
            'lapa sao paulo' => [-23.5274, -46.7030, 'Lapa, São Paulo, SP, Brasil'],
            'sao paulo sp' => [-23.5505, -46.6333, 'São Paulo, SP, Brasil'],
            'osasco sp' => [-23.5329, -46.7918, 'Osasco, SP, Brasil'],
            'barueri sp' => [-23.5112, -46.8764, 'Barueri, SP, Brasil'],
            'itapevi sp' => [-23.5488, -46.9336, 'Itapevi, SP, Brasil'],
            'cotia sp' => [-23.6037, -46.9192, 'Cotia, SP, Brasil'],
        ];

        foreach ($known as $needle => [$lat, $lng, $label]) {
            if (Str::contains($text, $needle)) {
                return [$label, ['lat' => $lat, 'lon' => $lng]];
            }
        }

        return null;
    }

    private function getCachedContext(string $cacheKey): ?array
    {
        try {
            $cached = Cache::get($cacheKey);

            return is_array($cached) ? $cached : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function putCachedContext(string $cacheKey, array $context): void
    {
        try {
            Cache::put($cacheKey, $context, now()->addDay());
        } catch (\Throwable) {
            // Cache indisponível não pode bloquear a análise do entorno.
        }
    }

    private function normalizeOsmPlace(array $element, float $originLat, float $originLng): ?array
    {
        $tags = (array) data_get($element, 'tags', []);
        $name = $tags['name'] ?? null;
        if (! $name) {
            return null;
        }

        $lat = (float) (data_get($element, 'lat') ?: data_get($element, 'center.lat'));
        $lng = (float) (data_get($element, 'lon') ?: data_get($element, 'center.lon'));
        if (! $lat || ! $lng) {
            return null;
        }

        $category = $this->osmCategory($tags);
        if (! $category) {
            return null;
        }

        return [
            'name' => $name,
            'type' => $this->osmTypeLabel($tags),
            'category' => $category,
            'distance_m' => $this->distanceMeters($originLat, $originLng, $lat, $lng),
            'rating' => null,
        ];
    }

    private function osmCategory(array $tags): ?string
    {
        $amenity = $tags['amenity'] ?? '';
        $shop = $tags['shop'] ?? '';
        $leisure = $tags['leisure'] ?? '';
        $railway = $tags['railway'] ?? '';
        $publicTransport = $tags['public_transport'] ?? '';

        if (in_array($railway, ['station', 'subway_entrance', 'halt'], true) || $publicTransport) {
            return 'transport';
        }
        if (in_array($amenity, ['school', 'university', 'college', 'kindergarten'], true)) {
            return 'education';
        }
        if (in_array($amenity, ['hospital', 'clinic', 'doctors', 'pharmacy'], true)) {
            return 'health';
        }
        if (in_array($shop, ['supermarket', 'mall', 'convenience'], true) || in_array($amenity, ['marketplace'], true)) {
            return 'shopping';
        }
        if (in_array($leisure, ['park', 'fitness_centre', 'sports_centre'], true)) {
            return 'leisure';
        }

        return null;
    }

    private function emptyLocationGroups(): Collection
    {
        return collect([
            ['key' => 'transport', 'label' => 'Transporte'],
            ['key' => 'education', 'label' => 'Educação'],
            ['key' => 'health', 'label' => 'Saúde'],
            ['key' => 'shopping', 'label' => 'Mercado e comércio'],
            ['key' => 'leisure', 'label' => 'Lazer'],
        ]);
    }

    private function fetchNominatimNearbyGroups(string $query, float $lat, float $lng): array
    {
        $searches = [
            'transport' => ['metrô', 'estação', 'terminal ônibus'],
            'education' => ['escola', 'colégio', 'universidade'],
            'health' => ['hospital', 'clínica', 'farmácia'],
            'shopping' => ['supermercado', 'shopping', 'mercado'],
            'leisure' => ['parque', 'academia', 'clube'],
        ];

        return $this->emptyLocationGroups()
            ->map(function (array $group) use ($searches, $query, $lat, $lng) {
                $places = collect();

                foreach ($searches[$group['key']] ?? [] as $term) {
                    if ($places->count() >= 5) {
                        break;
                    }

                    try {
                        $response = $this->guardedHttp()->timeout(4)
                            ->acceptJson()
                            ->withUserAgent('InvestFlip/1.0 contato@investflip.local')
                            ->get($this->assertPublicHttpsEndpoint(config('services.openstreetmap.nominatim_endpoint')), [
                                'q' => $term.' perto de '.$query,
                                'format' => 'json',
                                'limit' => 6,
                                'countrycodes' => 'br',
                                'addressdetails' => 1,
                            ]);
                        $this->assertResponseSize($response);

                        if (! $response->successful()) {
                            continue;
                        }

                        $places = $places->merge(collect($response->json() ?: [])
                            ->map(fn (array $place) => $this->normalizeNominatimPlace($place, $group['key'], $lat, $lng))
                            ->filter()
                            ->filter(fn (array $place) => (int) $place['distance_m'] <= self::RADIUS_METERS));
                    } catch (\Throwable) {
                        continue;
                    }
                }

                $places = $places
                    ->unique(fn (array $place) => Str::of($place['name'])->lower()->ascii()->toString())
                    ->sortBy('distance_m')
                    ->values();

                return [
                    'key' => $group['key'],
                    'label' => $group['label'],
                    'count' => $places->count(),
                    'places' => $places->take(5)->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function normalizeNominatimPlace(array $place, string $category, float $originLat, float $originLng): ?array
    {
        $lat = (float) data_get($place, 'lat');
        $lng = (float) data_get($place, 'lon');
        $name = data_get($place, 'namedetails.name')
            ?: data_get($place, 'address.amenity')
            ?: data_get($place, 'address.shop')
            ?: Str::before((string) data_get($place, 'display_name'), ',');

        if (! $lat || ! $lng || ! $name) {
            return null;
        }

        return [
            'name' => trim((string) $name),
            'type' => $this->nominatimTypeLabel($place, $category),
            'category' => $category,
            'distance_m' => $this->distanceMeters($originLat, $originLng, $lat, $lng),
            'rating' => null,
        ];
    }

    private function nominatimTypeLabel(array $place, string $category): string
    {
        $type = (string) (data_get($place, 'type') ?: data_get($place, 'class') ?: '');
        if ($type !== '') {
            return Str::of($type)->replace('_', ' ')->toString();
        }

        return [
            'transport' => 'transporte',
            'education' => 'educação',
            'health' => 'saúde',
            'shopping' => 'comércio',
            'leisure' => 'lazer',
        ][$category] ?? 'ponto';
    }

    private function osmTypeLabel(array $tags): string
    {
        return $tags['amenity'] ?? $tags['shop'] ?? $tags['leisure'] ?? $tags['railway'] ?? 'ponto';
    }

    private function overpassQuery(float $lat, float $lng): string
    {
        $radius = self::RADIUS_METERS;

        return <<<QUERY
[out:json][timeout:7];
(
  node(around:$radius,$lat,$lng)["amenity"~"school|university|college|kindergarten|hospital|clinic|doctors|pharmacy|marketplace"];
  way(around:$radius,$lat,$lng)["amenity"~"school|university|college|kindergarten|hospital|clinic|doctors|pharmacy|marketplace"];
  relation(around:$radius,$lat,$lng)["amenity"~"school|university|college|kindergarten|hospital|clinic|doctors|pharmacy|marketplace"];
  node(around:$radius,$lat,$lng)["shop"~"supermarket|mall|convenience"];
  way(around:$radius,$lat,$lng)["shop"~"supermarket|mall|convenience"];
  relation(around:$radius,$lat,$lng)["shop"~"supermarket|mall|convenience"];
  node(around:$radius,$lat,$lng)["leisure"~"park|fitness_centre|sports_centre"];
  way(around:$radius,$lat,$lng)["leisure"~"park|fitness_centre|sports_centre"];
  relation(around:$radius,$lat,$lng)["leisure"~"park|fitness_centre|sports_centre"];
  node(around:$radius,$lat,$lng)["railway"~"station|subway_entrance|halt"];
  way(around:$radius,$lat,$lng)["railway"~"station|subway_entrance|halt"];
  relation(around:$radius,$lat,$lng)["railway"~"station|subway_entrance|halt"];
  node(around:$radius,$lat,$lng)["public_transport"];
  way(around:$radius,$lat,$lng)["public_transport"];
  relation(around:$radius,$lat,$lng)["public_transport"];
);
out center tags 80;
QUERY;
    }

    private function fetchOverpassElements(float $lat, float $lng): Collection
    {
        $query = $this->overpassQuery($lat, $lng);
        $endpoints = collect([
            config('services.openstreetmap.overpass_endpoint'),
            config('services.openstreetmap.overpass_fallback_endpoint'),
            'https://overpass-api.de/api/interpreter',
            'https://overpass.kumi.systems/api/interpreter',
            'https://overpass.osm.ch/api/interpreter',
        ])->filter()->unique()->values();

        foreach ($endpoints as $endpoint) {
            try {
                $response = $this->guardedHttp()->timeout(6)
                    ->acceptJson()
                    ->withUserAgent('InvestFlip/1.0 contato@investflip.local')
                    ->asForm()
                    ->post($this->assertPublicHttpsEndpoint($endpoint), ['data' => $query]);
                $this->assertResponseSize($response);

                if ($response->successful() && is_array($response->json('elements'))) {
                    $elements = collect($response->json('elements', []));
                    if ($elements->isNotEmpty()) {
                        return $elements;
                    }
                }
            } catch (\Throwable) {
                // tenta o próximo endpoint
            }
        }

        return collect();
    }

    private function cacheKey(string $query, float $lat, float $lng): string
    {
        $normalizedQuery = Str::of($query)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '-')->trim('-')->limit(80, '')->toString();

        return 'neighborhood-context:'.round($lat, 3).':'.round($lng, 3).':'.$normalizedQuery;
    }

    private function guardedHttp(): PendingRequest
    {
        return Http::withHeaders(['Accept-Encoding' => 'identity'])->withOptions([
            'allow_redirects' => false,
            'decode_content' => false,
            'on_headers' => function (ResponseInterface $response): void {
                $length = trim($response->getHeaderLine('Content-Length'));
                if ($length !== '' && ctype_digit($length) && (int) $length > self::MAX_EXTERNAL_RESPONSE_BYTES) {
                    throw new RuntimeException('Resposta externa excede o limite permitido.');
                }
            },
            'progress' => function (int $downloadTotal, int $downloadedBytes): void {
                if ($downloadTotal > self::MAX_EXTERNAL_RESPONSE_BYTES || $downloadedBytes > self::MAX_EXTERNAL_RESPONSE_BYTES) {
                    throw new RuntimeException('Resposta externa excede o limite permitido.');
                }
            },
        ]);
    }

    private function assertPublicHttpsEndpoint(mixed $endpoint): string
    {
        $endpoint = trim((string) $endpoint);
        if (! ExternalUrlPolicy::allows($endpoint)) {
            throw new RuntimeException('Endpoint externo não permitido.');
        }

        return $endpoint;
    }

    private function assertResponseSize(Response $response): void
    {
        if (strlen($response->body()) > self::MAX_EXTERNAL_RESPONSE_BYTES) {
            throw new RuntimeException('Resposta externa excede o limite permitido.');
        }
    }

    private function queryCandidatesFrom(array $subject): array
    {
        $neighborhood = trim((string) ($subject['neighborhood'] ?? ''));
        $city = trim((string) ($subject['city'] ?? ''));
        $state = trim((string) ($subject['state'] ?? ''));
        $address = trim((string) ($subject['address'] ?? ''));

        return collect([
            $neighborhood && $city ? implode(', ', array_filter([$neighborhood, $city, $state, 'Brasil'])) : null,
            $neighborhood ? implode(', ', array_filter([$neighborhood, $state, 'Brasil'])) : null,
            $city ? implode(', ', array_filter([$city, $state, 'Brasil'])) : null,
            $address ? implode(', ', array_filter([$address, $city, $state, 'Brasil'])) : null,
            'Brasil',
        ])->filter()->unique()->values()->all();
    }

    private function humanType(array $types): string
    {
        $labels = [
            'subway_station' => 'metrô',
            'train_station' => 'trem',
            'transit_station' => 'transporte',
            'school' => 'escola',
            'university' => 'universidade',
            'hospital' => 'hospital',
            'doctor' => 'saúde',
            'pharmacy' => 'farmácia',
            'supermarket' => 'mercado',
            'shopping_mall' => 'shopping',
            'park' => 'parque',
            'gym' => 'academia',
        ];

        foreach ($types as $type) {
            if (isset($labels[$type])) {
                return $labels[$type];
            }
        }

        return Str::of($types[0] ?? 'ponto')->replace('_', ' ')->toString();
    }

    private function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $earth = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return (int) round($earth * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    private function connected(string $key, string $source, string $query, float $lat, float $lng, array $groups): array
    {
        return [
            'key' => $key,
            'source' => $source,
            'status' => 'connected',
            'message' => 'Contexto do entorno carregado em um raio de '.number_format(self::RADIUS_METERS / 1000, 1, ',', '.').' km.',
            'query' => $query,
            'radius_m' => self::RADIUS_METERS,
            'lat' => $lat,
            'lng' => $lng,
            'groups' => $groups,
        ];
    }

    private function unavailable(string $key, string $message): array
    {
        return [
            'key' => $key,
            'source' => $key === 'google_places' ? 'Google Maps/Places' : 'OpenStreetMap',
            'status' => 'unavailable',
            'message' => $message,
            'query' => null,
            'radius_m' => self::RADIUS_METERS,
            'lat' => null,
            'lng' => null,
            'groups' => [],
        ];
    }
}
