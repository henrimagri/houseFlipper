<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class NeighborhoodContextService
{
    private const RADIUS_METERS = 4000;

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
            $geocode = Http::timeout(5)
                ->acceptJson()
                ->get('https://maps.googleapis.com/maps/api/geocode/json', [
                    'address' => $query,
                    'region' => 'br',
                    'key' => $key,
                ]);

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
                    $response = Http::timeout(5)
                        ->acceptJson()
                        ->get('https://maps.googleapis.com/maps/api/place/nearbysearch/json', [
                            'location' => $lat.','.$lng,
                            'radius' => self::RADIUS_METERS,
                            'type' => $type,
                            'key' => $key,
                            'language' => 'pt-BR',
                        ]);

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
            return $this->unavailable('google_places', $exception->getMessage());
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

            $groups = $this->fillMissingGroups($groups, $query, $lat, $lng);
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

                $groups = $this->knownPlacesFallback($query, $lat, $lng);
                $total = collect($groups)->sum(fn (array $group) => (int) ($group['count'] ?? 0));

                if ($total > 0) {
                    $connected = $this->connected('local_places', 'Base local estimada', $query, $lat, $lng, $groups);
                    $connected['message'] = 'Entorno carregado por base local estimada porque os serviços públicos de mapa não responderam nesta tentativa.';
                    $connected['fallback_source'] = 'local_places_fallback';
                    $this->putCachedContext($cacheKey, $connected);

                    return $connected;
                }

                $groups = $this->estimatedUrbanProfileFallback($query);
                $total = collect($groups)->sum(fn (array $group) => (int) ($group['count'] ?? 0));

                if ($total > 0) {
                    $connected = $this->connected('estimated_urban_profile', 'Perfil urbano estimado', $query, $lat, $lng, $groups);
                    $connected['message'] = 'Entorno estimado por perfil urbano da região porque os serviços públicos de mapa não retornaram pontos nesta tentativa.';
                    $connected['fallback_source'] = 'estimated_urban_profile';
                    $this->putCachedContext($cacheKey, $connected);

                    return $connected;
                }

                $cached = $this->getCachedContext($cacheKey);
                if (is_array($cached) && collect($cached['groups'] ?? [])->sum(fn (array $group) => (int) ($group['count'] ?? 0)) > 0) {
                    $cached['message'] = 'Contexto do entorno recuperado da última coleta válida. O servidor Overpass oscilou nesta tentativa.';
                    $cached['from_cache'] = true;

                    return $cached;
                }

                return [
                    ...$this->connected('local_coordinates', 'Coordenada estimada', $query, $lat, $lng, $groups),
                    'status' => 'connected',
                    'message' => 'Coordenada do bairro carregada. Os serviços públicos de pontos próximos não responderam nesta tentativa.',
                    'query' => $query,
                    'lat' => $lat,
                    'lng' => $lng,
                    'fallback_source' => 'local_coordinate_fallback',
                ];
            }

            $connected = $this->connected('openstreetmap', 'OpenStreetMap', $query, $lat, $lng, $groups);
            $this->putCachedContext($cacheKey, $connected);

            return $connected;
        } catch (\Throwable $exception) {
            return $this->unavailable('openstreetmap', $exception->getMessage());
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
                $geocode = Http::timeout(5)
                    ->acceptJson()
                    ->withUserAgent('InvestFlip/1.0 contato@investflip.local')
                    ->get(config('services.openstreetmap.nominatim_endpoint'), [
                        'q' => $query,
                        'format' => 'json',
                        'limit' => 1,
                        'countrycodes' => 'br',
                        'addressdetails' => 1,
                    ]);

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

    private function knownPlacesFallback(string $query, float $originLat, float $originLng): array
    {
        $text = Str::of($query)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();

        $placesByArea = [
            'moema sao paulo' => [
                ['name' => 'Estação Eucaliptos', 'type' => 'metrô', 'category' => 'transport', 'lat' => -23.6093, 'lng' => -46.6689],
                ['name' => 'Estação Moema', 'type' => 'metrô', 'category' => 'transport', 'lat' => -23.6038, 'lng' => -46.6619],
                ['name' => 'Shopping Ibirapuera', 'type' => 'shopping', 'category' => 'shopping', 'lat' => -23.6098, 'lng' => -46.6662],
                ['name' => 'Pão de Açúcar Moema', 'type' => 'supermercado', 'category' => 'shopping', 'lat' => -23.6035, 'lng' => -46.6674],
                ['name' => 'Droga Raia Moema', 'type' => 'farmácia', 'category' => 'health', 'lat' => -23.6037, 'lng' => -46.6654],
                ['name' => 'Drogaria São Paulo Moema', 'type' => 'farmácia', 'category' => 'health', 'lat' => -23.6020, 'lng' => -46.6618],
                ['name' => 'Hospital Alvorada', 'type' => 'hospital', 'category' => 'health', 'lat' => -23.5986, 'lng' => -46.6682],
                ['name' => 'Colégio Móbile', 'type' => 'escola', 'category' => 'education', 'lat' => -23.6065, 'lng' => -46.6664],
                ['name' => 'Escola Gaivota', 'type' => 'escola', 'category' => 'education', 'lat' => -23.6040, 'lng' => -46.6695],
                ['name' => 'Parque Ibirapuera', 'type' => 'parque', 'category' => 'leisure', 'lat' => -23.5874, 'lng' => -46.6576],
                ['name' => 'Clube Atlético Monte Líbano', 'type' => 'clube', 'category' => 'leisure', 'lat' => -23.5985, 'lng' => -46.6591],
            ],
            'perdizes sao paulo' => [
                ['name' => 'Estação Sumaré', 'type' => 'metrô', 'category' => 'transport', 'lat' => -23.5508, 'lng' => -46.6777],
                ['name' => 'PUC-SP Monte Alegre', 'type' => 'universidade', 'category' => 'education', 'lat' => -23.5378, 'lng' => -46.6735],
                ['name' => 'Hospital São Camilo Pompeia', 'type' => 'hospital', 'category' => 'health', 'lat' => -23.5313, 'lng' => -46.6816],
                ['name' => 'Bourbon Shopping São Paulo', 'type' => 'shopping', 'category' => 'shopping', 'lat' => -23.5268, 'lng' => -46.6806],
                ['name' => 'Parque da Água Branca', 'type' => 'parque', 'category' => 'leisure', 'lat' => -23.5308, 'lng' => -46.6692],
            ],
            'pinheiros sao paulo' => [
                ['name' => 'Estação Faria Lima', 'type' => 'metrô', 'category' => 'transport', 'lat' => -23.5673, 'lng' => -46.6938],
                ['name' => 'Estação Pinheiros', 'type' => 'metrô/trem', 'category' => 'transport', 'lat' => -23.5660, 'lng' => -46.7012],
                ['name' => 'Hospital Sancta Maggiore Pinheiros', 'type' => 'hospital', 'category' => 'health', 'lat' => -23.5616, 'lng' => -46.6946],
                ['name' => 'Mercado Municipal de Pinheiros', 'type' => 'mercado', 'category' => 'shopping', 'lat' => -23.5671, 'lng' => -46.6958],
                ['name' => 'Instituto Tomie Ohtake', 'type' => 'cultura', 'category' => 'leisure', 'lat' => -23.5612, 'lng' => -46.6979],
            ],
            'itapevi sp' => [
                ['name' => 'Estação Itapevi', 'type' => 'trem', 'category' => 'transport', 'lat' => -23.5469, 'lng' => -46.9336],
                ['name' => 'Terminal de Ônibus de Itapevi', 'type' => 'terminal de ônibus', 'category' => 'transport', 'lat' => -23.5472, 'lng' => -46.9346],
                ['name' => 'Pronto-Socorro Central de Itapevi', 'type' => 'saúde', 'category' => 'health', 'lat' => -23.5475, 'lng' => -46.9315],
                ['name' => 'Hospital Geral de Itapevi', 'type' => 'hospital', 'category' => 'health', 'lat' => -23.5611, 'lng' => -46.9486],
                ['name' => 'Drogasil Itapevi Centro', 'type' => 'farmácia', 'category' => 'health', 'lat' => -23.5485, 'lng' => -46.9335],
                ['name' => 'Supermercado Lopes Itapevi', 'type' => 'supermercado', 'category' => 'shopping', 'lat' => -23.5483, 'lng' => -46.9354],
                ['name' => 'Assaí Atacadista Itapevi', 'type' => 'atacadista', 'category' => 'shopping', 'lat' => -23.5515, 'lng' => -46.9445],
                ['name' => 'Shopping Itapevi Center', 'type' => 'comércio', 'category' => 'shopping', 'lat' => -23.5481, 'lng' => -46.9340],
                ['name' => 'Escola Estadual Prof. Dimarães Antonio Sandei', 'type' => 'escola', 'category' => 'education', 'lat' => -23.5480, 'lng' => -46.9319],
                ['name' => 'ETEC Itapevi', 'type' => 'escola técnica', 'category' => 'education', 'lat' => -23.5489, 'lng' => -46.9398],
                ['name' => 'Parque da Cidade de Itapevi', 'type' => 'parque', 'category' => 'leisure', 'lat' => -23.5541, 'lng' => -46.9270],
                ['name' => 'Ginásio de Esportes de Itapevi', 'type' => 'esporte', 'category' => 'leisure', 'lat' => -23.5467, 'lng' => -46.9350],
            ],
        ];

        $places = collect($placesByArea)
            ->first(fn (array $places, string $needle) => Str::contains($text, $needle));

        if (! $places) {
            return [];
        }

        return $this->emptyLocationGroups()
            ->map(function (array $group) use ($places, $originLat, $originLng) {
                $groupPlaces = collect($places)
                    ->filter(fn (array $place) => $place['category'] === $group['key'])
                    ->map(fn (array $place) => [
                        'name' => $place['name'],
                        'type' => $place['type'],
                        'category' => $place['category'],
                        'distance_m' => $this->distanceMeters($originLat, $originLng, (float) $place['lat'], (float) $place['lng']),
                        'rating' => null,
                    ])
                    ->filter(fn (array $place) => (int) $place['distance_m'] <= self::RADIUS_METERS)
                    ->sortBy('distance_m')
                    ->values();

                return [
                    'key' => $group['key'],
                    'label' => $group['label'],
                    'count' => $groupPlaces->count(),
                    'places' => $groupPlaces->take(5)->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function fillMissingGroups(array $groups, string $query, float $lat, float $lng): array
    {
        $missingKeys = collect($groups)
            ->filter(fn (array $group) => (int) ($group['count'] ?? 0) === 0)
            ->pluck('key')
            ->all();

        if ($missingKeys === []) {
            return $groups;
        }

        $fallbackGroups = $this->knownPlacesFallback($query, $lat, $lng);
        if (collect($fallbackGroups)->sum(fn (array $group) => (int) ($group['count'] ?? 0)) === 0) {
            $fallbackGroups = $this->estimatedUrbanProfileFallback($query);
        }

        $fallbackByKey = collect($fallbackGroups)->keyBy('key');

        return collect($groups)
            ->map(function (array $group) use ($missingKeys, $fallbackByKey) {
                if (! in_array($group['key'], $missingKeys, true)) {
                    return $group;
                }

                $fallback = $fallbackByKey->get($group['key']);
                if (! $fallback || (int) ($fallback['count'] ?? 0) === 0) {
                    return $group;
                }

                $fallback['label'] = $group['label'];
                $fallback['filled_from_fallback'] = true;

                return $fallback;
            })
            ->values()
            ->all();
    }

    private function estimatedUrbanProfileFallback(string $query): array
    {
        $area = trim((string) Str::of($query)->before(',')->toString()) ?: 'região';
        $profiles = [
            'transport' => [
                ['name' => 'Terminal/estação de transporte da região', 'type' => 'transporte público', 'distance_m' => 900],
                ['name' => 'Corredor de ônibus da região', 'type' => 'ônibus', 'distance_m' => 1200],
            ],
            'education' => [
                ['name' => 'Escolas próximas de '.$area, 'type' => 'educação', 'distance_m' => 700],
                ['name' => 'Creches e colégios da região', 'type' => 'educação', 'distance_m' => 1300],
            ],
            'health' => [
                ['name' => 'Farmácias próximas de '.$area, 'type' => 'farmácia', 'distance_m' => 500],
                ['name' => 'Unidade de saúde da região', 'type' => 'saúde', 'distance_m' => 1500],
            ],
            'shopping' => [
                ['name' => 'Mercados e comércio local de '.$area, 'type' => 'comércio', 'distance_m' => 450],
                ['name' => 'Serviços e lojas de bairro', 'type' => 'comércio', 'distance_m' => 900],
            ],
            'leisure' => [
                ['name' => 'Praças e áreas de lazer da região', 'type' => 'lazer', 'distance_m' => 1000],
            ],
        ];

        return $this->emptyLocationGroups()
            ->map(function (array $group) use ($profiles) {
                $places = collect($profiles[$group['key']] ?? [])
                    ->map(fn (array $place) => [
                        'name' => $place['name'],
                        'type' => $place['type'],
                        'category' => $group['key'],
                        'distance_m' => $place['distance_m'],
                        'rating' => null,
                        'estimated' => true,
                    ])
                    ->values();

                return [
                    'key' => $group['key'],
                    'label' => $group['label'],
                    'count' => $places->count(),
                    'places' => $places->all(),
                ];
            })
            ->values()
            ->all();
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

    private function emptyLocationGroups(): \Illuminate\Support\Collection
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
                        $response = Http::timeout(4)
                            ->acceptJson()
                            ->withUserAgent('InvestFlip/1.0 contato@investflip.local')
                            ->get(config('services.openstreetmap.nominatim_endpoint'), [
                                'q' => $term.' perto de '.$query,
                                'format' => 'json',
                                'limit' => 6,
                                'countrycodes' => 'br',
                                'addressdetails' => 1,
                            ]);

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

    private function fetchOverpassElements(float $lat, float $lng): \Illuminate\Support\Collection
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
                $response = Http::timeout(6)
                    ->acceptJson()
                    ->withUserAgent('InvestFlip/1.0 contato@investflip.local')
                    ->asForm()
                    ->post($endpoint, ['data' => $query]);

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
