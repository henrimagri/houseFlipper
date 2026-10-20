<?php

namespace App\Services\Maps;

use App\Support\ExternalUrlPolicy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class GeocodeService
{
    public function search(string $query): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 3) {
            return [];
        }
        $endpoint = (string) config('maps.nominatim_endpoint');
        if (! ExternalUrlPolicy::allows($endpoint)) {
            return [];
        }
        $rows = Cache::remember('maps:geocode:'.hash('sha256', mb_strtolower($query)), now()->addMinutes(30), function () use ($endpoint, $query) {
            $response = Http::timeout(8)
                ->retry(1, 400)
                ->withHeaders(['User-Agent' => config('maps.user_agent'), 'Accept-Language' => 'pt-BR'])
                ->get($endpoint, [
                    'q' => $query,
                    'format' => 'jsonv2',
                    'addressdetails' => 1,
                    'limit' => 6,
                    'countrycodes' => 'br',
                ]);

            return $response->ok() ? $response->json() : [];
        });
        if (! is_array($rows)) {
            return [];
        }

        return collect($rows)->map(fn ($row) => [
            'label' => $row['display_name'] ?? '',
            'lat' => isset($row['lat']) ? (float) $row['lat'] : null,
            'lng' => isset($row['lon']) ? (float) $row['lon'] : null,
            'type' => $row['type'] ?? $row['class'] ?? null,
        ])->filter(fn ($row) => $row['lat'] && $row['lng'])->values()->all();
    }
}
