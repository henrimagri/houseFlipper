<?php

namespace App\Services\Maps;

use App\Enums\AuthorizationStatus;
use App\Models\CommercialListing;
use App\Models\AuctionListing;
use App\Models\CrmContact;
use App\Models\Opportunity;
use App\Models\SavedMapSearch;
use App\Models\User;
use App\Services\Ai\ProductEntitlement;
use App\Support\ProductContext;

class MapQueryService
{
    public function __construct(
        private readonly ProductEntitlement $entitlement,
        private readonly ProductContext $context,
    ) {}

    public function query(User $user, array $input): array
    {
        $this->entitlement->assert($user, 'map_listings');
        $product = $this->context->normalize($input['product'] ?? null) ?? $this->context->current();
        $mode = (string) ($input['mode'] ?? 'properties');
        $modes = config('maps.modes', []);
        if (! isset($modes[$mode])) {
            abort(422, 'Modo de mapa desconhecido.');
        }
        $allowed = $this->entitlement->mapModesFor($user, $product);
        if (! in_array($mode, $allowed, true)) {
            return [
                'mode' => $mode,
                'available' => false,
                'reason' => 'Este modo não está incluído no produto ativo.',
                'points' => [],
                'clusters' => [],
            ];
        }
        if (! ($modes[$mode]['implemented'] ?? false)) {
            return [
                'mode' => $mode,
                'available' => false,
                'reason' => $modes[$mode]['reason'] ?? 'Modo ainda sem fonte de dados.',
                'points' => [],
                'clusters' => [],
            ];
        }

        $points = $this->pointsForMode($user, $mode);
        $filters = is_array($input['filters'] ?? null) ? $input['filters'] : [];
        $points = $this->applyFilters($points, $filters);
        $geometry = $input['geometry'] ?? null;
        if (is_array($geometry)) {
            $points = $this->applyGeometry($points, $geometry);
        }

        $response = [
            'mode' => $mode,
            'available' => true,
            'reason' => null,
            'points' => $points,
            'clusters' => [],
            'meta' => [
                'count' => count($points),
                'provider' => config('maps.provider'),
                'buildings_3d' => config('maps.provider') === 'openfreemap' ? 'extrusion_when_tiles_available' : 'depends_on_provider',
            ],
        ];

        if ($mode === 'lead_heat') {
            $response['points'] = [];
            $response['clusters'] = $this->heatClusters($this->pointsForMode($user, 'lead_heat'));
            $response['meta']['privacy'] = 'aggregated_only';
        }
        if ($mode === 'price_m2') {
            $response['clusters'] = $this->priceClusters($points);
            $response['meta']['sample_warning'] = collect($response['clusters'])->contains(fn ($row) => ($row['samples'] ?? 0) < 3);
        }
        if ($mode === 'liquidity') {
            $response['meta']['note'] = 'Liquidez estimada apenas com tempo de anúncio da carteira da conta. Sem série de demanda externa.';
        }

        return $response;
    }

    public function saveSearch(User $user, array $data): SavedMapSearch
    {
        $this->entitlement->assert($user, 'map_listings');

        return SavedMapSearch::query()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'product_slug' => $this->context->normalize($data['product'] ?? null) ?? $this->context->current() ?? 'corretor',
            'name' => $data['name'],
            'mode' => $data['mode'] ?? 'properties',
            'geometry' => $data['geometry'] ?? null,
            'filters' => $data['filters'] ?? null,
        ]);
    }

    private function pointsForMode(User $user, string $mode): array
    {
        if ($mode === 'auctions') {
            return AuctionListing::query()->whereNotNull('latitude')->whereNotNull('longitude')->where('status', '!=', 'withdrawn')->limit(5000)->get()->map(fn (AuctionListing $item) => [
                'kind' => 'auction', 'id' => $item->id, 'title' => $item->title, 'status' => $item->status,
                'lat' => (float) $item->latitude, 'lng' => (float) $item->longitude,
                'price' => $item->price_cents !== null ? $item->price_cents / 100 : null,
                'area_m2' => null, 'city' => $item->city, 'neighborhood' => $item->neighborhood,
                'provider' => $item->provider, 'official_url' => $item->official_url,
            ])->all();
        }
        $wallet = CommercialListing::query()
            ->where('user_id', $user->id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();
        $leads = Opportunity::query()
            ->where('user_id', $user->id)
            ->where('is_archived', false)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        $walletPoints = $wallet->map(fn (CommercialListing $item) => [
            'kind' => 'wallet',
            'id' => $item->id,
            'title' => $item->title,
            'status' => $item->status->value,
            'lat' => (float) $item->latitude,
            'lng' => (float) $item->longitude,
            'price' => $item->asking_price_cents !== null ? $item->asking_price_cents / 100 : null,
            'area_m2' => $item->area_m2 !== null ? (float) $item->area_m2 : null,
            'city' => $item->city,
            'neighborhood' => $item->neighborhood,
            'authorized' => $item->authorization?->status === AuthorizationStatus::Granted,
            'published' => $item->status->value === 'published',
            'days_listed' => $item->published_at ? $item->published_at->diffInDays(now()) : null,
        ])->all();

        $leadPoints = $leads->map(fn (Opportunity $item) => [
            'kind' => 'discovered',
            'id' => $item->id,
            'title' => $item->title,
            'status' => $item->status->value,
            'lat' => (float) $item->latitude,
            'lng' => (float) $item->longitude,
            'price' => $item->asking_price !== null ? (float) $item->asking_price : null,
            'area_m2' => isset($item->area_m2) ? (float) $item->area_m2 : null,
            'city' => $item->city,
            'neighborhood' => $item->neighborhood,
            'favorite' => (bool) $item->is_favorite,
        ])->all();

        return match ($mode) {
            'properties' => [...$walletPoints, ...$leadPoints],
            'opportunities', 'flipper' => $leadPoints,
            'capture_potential' => array_values(array_filter($leadPoints, fn ($p) => ($p['kind'] ?? '') === 'discovered')),
            'price_m2', 'liquidity' => $walletPoints,
            'lead_heat' => $this->leadHeatPoints($user),
            default => [...$walletPoints, ...$leadPoints],
        };
    }

    private function leadHeatPoints(User $user): array
    {
        return CrmContact::query()
            ->where('user_id', $user->id)
            ->whereHas('listing', fn ($q) => $q->whereNotNull('latitude')->whereNotNull('longitude'))
            ->with('listing:id,latitude,longitude,city,neighborhood')
            ->get()
            ->map(fn (CrmContact $contact) => [
                'kind' => 'lead_aggregate',
                'id' => $contact->id,
                'lat' => (float) $contact->listing->latitude,
                'lng' => (float) $contact->listing->longitude,
                'city' => $contact->listing->city,
                'neighborhood' => $contact->listing->neighborhood,
            ])
            ->all();
    }

    private function heatClusters(array $points): array
    {
        $buckets = [];
        foreach ($points as $point) {
            $key = round($point['lat'], 3).':'.round($point['lng'], 3);
            $buckets[$key] ??= [
                'lat' => round($point['lat'], 3),
                'lng' => round($point['lng'], 3),
                'count' => 0,
                'neighborhood' => $point['neighborhood'] ?? null,
            ];
            $buckets[$key]['count']++;
        }

        return array_values($buckets);
    }

    private function priceClusters(array $points): array
    {
        $groups = [];
        foreach ($points as $point) {
            if (! $point['price'] || ! $point['area_m2'] || $point['area_m2'] <= 0) {
                continue;
            }
            $key = ($point['city'] ?? '—').'|'.($point['neighborhood'] ?? '—');
            $groups[$key] ??= ['city' => $point['city'] ?? null, 'neighborhood' => $point['neighborhood'] ?? null, 'values' => []];
            $groups[$key]['values'][] = $point['price'] / $point['area_m2'];
        }
        $out = [];
        foreach ($groups as $group) {
            sort($group['values']);
            $n = count($group['values']);
            $median = $n ? $group['values'][(int) floor(($n - 1) / 2)] : null;
            $sample = $group;
            $lat = null;
            $lng = null;
            foreach ($points as $point) {
                if (($point['neighborhood'] ?? null) === $group['neighborhood'] && ($point['city'] ?? null) === $group['city']) {
                    $lat = $point['lat'];
                    $lng = $point['lng'];
                    break;
                }
            }
            $out[] = [
                'city' => $group['city'],
                'neighborhood' => $group['neighborhood'],
                'median_price_m2' => $median,
                'samples' => $n,
                'source' => 'asking_price',
                'lat' => $lat,
                'lng' => $lng,
            ];
        }

        return $out;
    }

    private function applyFilters(array $points, array $filters): array
    {
        $max = isset($filters['max_price']) ? (float) $filters['max_price'] : null;
        $city = isset($filters['city']) ? mb_strtolower(trim((string) $filters['city'])) : '';
        $type = isset($filters['kind']) ? (string) $filters['kind'] : '';

        return array_values(array_filter($points, function (array $point) use ($max, $city, $type) {
            if ($max !== null && $point['price'] !== null && $point['price'] > $max) {
                return false;
            }
            if ($city !== '' && mb_strtolower((string) ($point['city'] ?? '')) !== $city) {
                return false;
            }
            if ($type !== '' && ($point['kind'] ?? '') !== $type) {
                return false;
            }

            return true;
        }));
    }

    private function applyGeometry(array $points, array $geometry): array
    {
        $type = $geometry['type'] ?? null;
        if ($type === 'radius') {
            $lat = (float) ($geometry['lat'] ?? 0);
            $lng = (float) ($geometry['lng'] ?? 0);
            $km = (float) ($geometry['radius_km'] ?? 0);
            if ($km <= 0) {
                return $points;
            }

            return array_values(array_filter($points, fn (array $p) => $this->haversine($lat, $lng, $p['lat'], $p['lng']) <= $km));
        }
        if ($type === 'polygon' && is_array($geometry['rings'] ?? null)) {
            $ring = $geometry['rings'];

            return array_values(array_filter($points, fn (array $p) => $this->pointInPolygon($p['lat'], $p['lng'], $ring)));
        }
        if ($type === 'bbox' && isset($geometry['min_lat'], $geometry['max_lat'], $geometry['min_lng'], $geometry['max_lng'])) {
            return array_values(array_filter($points, fn (array $p) => $p['lat'] >= $geometry['min_lat']
                && $p['lat'] <= $geometry['max_lat']
                && $p['lng'] >= $geometry['min_lng']
                && $p['lng'] <= $geometry['max_lng']));
        }

        return $points;
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $earth * asin(min(1, sqrt($a)));
    }

    private function pointInPolygon(float $lat, float $lng, array $ring): bool
    {
        $inside = false;
        $n = count($ring);
        if ($n < 3) {
            return false;
        }
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $yi = (float) ($ring[$i]['lat'] ?? $ring[$i][0] ?? 0);
            $xi = (float) ($ring[$i]['lng'] ?? $ring[$i][1] ?? 0);
            $yj = (float) ($ring[$j]['lat'] ?? $ring[$j][0] ?? 0);
            $xj = (float) ($ring[$j]['lng'] ?? $ring[$j][1] ?? 0);
            $intersect = (($yi > $lat) !== ($yj > $lat))
                && ($lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi);
            if ($intersect) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }
}
