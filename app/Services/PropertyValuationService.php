<?php

namespace App\Services;

use App\Models\RealEstateTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PropertyValuationService
{
    public const VERSION = 'comparables-v3';

    public function applyComparableMarketEstimates(Collection $items): Collection
    {
        $items = collect($items)->values()->map(function (array $item, int $index) {
            $item['_valuation_index'] = $index;

            return $item;
        });
        $comparables = $items
            ->filter(fn (array $item) => is_finite((float) ($item['asking_price'] ?? 0)) && is_finite((float) ($item['area_m2'] ?? 0)) && (float) ($item['asking_price'] ?? 0) > 0 && (float) ($item['area_m2'] ?? 0) >= 20)
            ->map(function (array $item) {
                $item['comparable_price_per_m2'] = (float) $item['asking_price'] / (float) $item['area_m2'];

                return $item;
            })
            ->values();

        return $items->map(function (array $item) use ($comparables) {
            $area = (float) ($item['area_m2'] ?? 0);
            $askingPrice = (float) ($item['asking_price'] ?? 0);
            $bedrooms = (int) ($item['bedrooms'] ?? 0);
            $city = $this->clean((string) ($item['city'] ?? ''));
            $neighborhood = $this->clean((string) ($item['neighborhood'] ?? ''));

            $localComparables = $comparables
                ->filter(function (array $comparable) use ($item, $city, $bedrooms, $area) {
                    if ($comparable['_valuation_index'] === $item['_valuation_index']
                        || (! empty($item['id']) && ($comparable['id'] ?? null) === $item['id'])
                        || (! empty($item['source_url']) && ($comparable['source_url'] ?? null) === $item['source_url'])) {
                        return false;
                    }

                    $sameCity = $city !== '' && $this->clean((string) ($comparable['city'] ?? '')) === $city;
                    $sameState = ! empty($item['state']) && strtoupper(trim((string) ($comparable['state'] ?? ''))) === strtoupper(trim($item['state']));
                    $sameType = $this->propertyTypeFromItem($item) !== '' && $this->propertyTypeFromItem($comparable) === $this->propertyTypeFromItem($item);
                    $similarArea = $area >= 20 && $comparable['area_m2'] >= $area * 0.65 && $comparable['area_m2'] <= $area * 1.45;
                    $sameBedrooms = $bedrooms === 0 || (int) ($comparable['bedrooms'] ?? 0) === 0 || abs((int) ($comparable['bedrooms'] ?? 0) - $bedrooms) <= 1;

                    return $sameCity && $sameState && $sameType && $similarArea && $sameBedrooms && $comparable['comparable_price_per_m2'] > 1000 && $comparable['comparable_price_per_m2'] < 100000;
                })
                ->unique(fn (array $reference) => $reference['source_url'] ?? ($reference['id'] ?? $reference['_valuation_index']))
                ->values();

            $neighborhoodComparables = $this->filteredReferences($localComparables->filter(fn (array $reference) => $neighborhood !== '' && $this->clean((string) ($reference['neighborhood'] ?? '')) === $neighborhood), fn (array $reference) => $reference['comparable_price_per_m2']);
            $neighborhoodTier = $neighborhoodComparables->count() >= 3;
            $localComparables = $neighborhoodTier ? $neighborhoodComparables : $this->filteredReferences($localComparables, fn (array $reference) => $reference['comparable_price_per_m2']);

            $localComparablePrices = $localComparables->pluck('comparable_price_per_m2')->all();
            $medianPricePerM2 = $this->median($localComparablePrices);
            $referenceComparables = $localComparables;
            $referenceSources = $referenceComparables
                ->pluck('source')
                ->filter()
                ->unique()
                ->values()
                ->all();

            $transactionPack = $this->realTransactionReferences($item);
            $transactionPrices = $transactionPack['transactions']
                ->map(fn (RealEstateTransaction $transaction) => $this->transactionPricePerM2($transaction, $item))
                ->filter(fn (float $value) => $value >= 1000 && $value <= 100000)
                ->all();
            $confirmedMedianPricePerM2 = $this->median($transactionPrices);
            $transactionCount = $transactionPack['transactions']->count();
            $activeListingsWeight = $transactionCount >= 3 ? 0.15 : ($transactionCount > 0 ? 0.35 : 1.0);
            $realSalesWeight = 1 - $activeListingsWeight;

            if ($area > 0 && $confirmedMedianPricePerM2 > 0) {
                $basePricePerM2 = $medianPricePerM2 > 0
                    ? ($confirmedMedianPricePerM2 * $realSalesWeight) + ($medianPricePerM2 * $activeListingsWeight)
                    : $confirmedMedianPricePerM2;
                $adjustedPricePerM2 = $this->adjustedPricePerM2($basePricePerM2, $item, $referenceComparables);
                $estimatedMarketPrice = $adjustedPricePerM2 * $area;
                $medianPricePerM2 = $adjustedPricePerM2;
                $method = $transactionPack['method'];
            } elseif ($area > 0 && $medianPricePerM2 > 0) {
                $adjustedPricePerM2 = $this->adjustedPricePerM2($medianPricePerM2, $item, $referenceComparables);
                $estimatedMarketPrice = $adjustedPricePerM2 * $area;
                $medianPricePerM2 = $adjustedPricePerM2;
                $method = $neighborhoodTier ? 'comparables_neighborhood_median_m2' : 'comparables_region_median_m2';
            } else {
                $estimatedMarketPrice = $this->estimateMarketPrice($askingPrice, $area, $item['title'] ?? '');
                $method = 'fallback_heuristic';
            }

            $discount = $estimatedMarketPrice > 0 ? max(0, ($estimatedMarketPrice - $askingPrice) / $estimatedMarketPrice * 100) : 0;
            $confidence = $this->confidence($referenceComparables->count(), $transactionCount, $item);
            if ($method === 'fallback_heuristic') {
                $confidence = min(40, $confidence);
            }
            if (in_array($method, ['itbi_city_median_m2', 'comparables_region_median_m2'], true)) {
                $confidence = min(65, $confidence);
            }
            $rangeFactor = $confidence >= 85 ? 0.06 : ($confidence >= 70 ? 0.09 : 0.13);

            $item['estimated_market_price'] = round($estimatedMarketPrice, 2);
            $item['estimated_min_value'] = round($estimatedMarketPrice * (1 - $rangeFactor), 2);
            $item['estimated_max_value'] = round($estimatedMarketPrice * (1 + $rangeFactor), 2);
            $item['discount_percentage'] = round($discount, 2);
            $item['market_estimate_method'] = $method;
            $item['market_reference_price_per_m2'] = $medianPricePerM2 ? round($medianPricePerM2, 2) : null;
            $item['market_reference_count'] = $referenceComparables->count() + $transactionCount;
            $item['market_reference_sources'] = $transactionCount > 0 ? array_values(array_unique(['ITBI importado', ...$referenceSources])) : $referenceSources;
            $item['confirmed_transactions_count'] = $transactionCount;
            $item['confidence_percentage'] = $confidence;
            $item['confidence_basis'] = 'heuristic_evidence_quality';
            $item['valuation_version'] = self::VERSION;
            $item['transaction_reference_window_months'] = $this->transactionWindowMonths();
            $item['valuation_explanation'] = $this->valuationExplanation($item, $transactionPack, $referenceComparables->count(), $confirmedMedianPricePerM2, $medianPricePerM2, $rangeFactor);
            $item['score'] = min($this->score($discount, $area, $bedrooms, $askingPrice), $confidence < 50 ? 74 : 100);
            if ($method === 'fallback_heuristic') {
                $item['score'] = min(49, $item['score']);
            }
            $item['signal'] = $item['score'] >= 80 ? 'Boa oportunidade' : ($item['score'] >= 65 ? 'Negociar' : 'Atenção');

            unset($item['_valuation_index']);

            return $item;
        });
    }

    private function filteredReferences(Collection $references, callable $price): Collection
    {
        $references = $references->filter(function ($reference) use ($price) {
            $value = (float) $price($reference);

            return is_finite($value) && $value > 1000 && $value < 100000;
        })->values();
        $values = $references->map(fn ($reference) => (float) $price($reference))->sort()->values();
        if ($values->count() >= 5) {
            $q1 = $this->percentile($values->all(), 25);
            $q3 = $this->percentile($values->all(), 75);
            $iqr = $q3 - $q1;
            $min = $q1 - 1.5 * $iqr;
            $max = $q3 + 1.5 * $iqr;
            $references = $references->filter(fn ($reference) => $price($reference) >= $min && $price($reference) <= $max)->values();
        }

        return $references;
    }

    private function median(array $values): float
    {
        sort($values);
        $count = count($values);
        if ($count === 0) {
            return 0;
        }

        $middle = intdiv($count, 2);

        return $count % 2 ? (float) $values[$middle] : ((float) $values[$middle - 1] + (float) $values[$middle]) / 2;
    }

    private function percentile(array $values, int $percentile): float
    {
        sort($values);
        $index = ($percentile / 100) * (count($values) - 1);
        $lower = (int) floor($index);
        $upper = (int) ceil($index);

        if ($lower === $upper) {
            return (float) $values[$lower];
        }

        return (float) $values[$lower] + ($index - $lower) * ((float) $values[$upper] - (float) $values[$lower]);
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

    private function confidence(int $listedComparables, int $confirmedTransactions, array $item): int
    {
        $confidence = 30;
        $confidence += min(30, $listedComparables * 3);
        $confidence += min(30, $confirmedTransactions * 10);
        $confidence += ! empty($item['neighborhood']) ? 5 : 0;
        $confidence += ! empty($item['area_m2']) ? 5 : 0;

        return (int) max(20, min(95, $confidence));
    }

    private function score(float $discount, float $area, int $bedrooms, float $askingPrice): int
    {
        $score = 45 + min(35, $discount * 1.4);
        $score += $area >= 50 && $area <= 110 ? 8 : 0;
        $score += $bedrooms >= 2 ? 6 : 0;
        $score += $askingPrice > 0 && $askingPrice <= 700000 ? 6 : 0;

        return (int) max(0, min(100, round($score)));
    }

    private function realTransactionReferences(array $item): array
    {
        $building = $this->transactionsBaseQuery($item);
        $this->applyBuildingFilter($building, $item);
        $identity = $this->buildingIdentity($item);
        $buildingTransactions = $building->limit(200)->get()->filter(function (RealEstateTransaction $transaction) use ($identity) {
            $reference = $this->buildingIdentity(['street_name' => $transaction->street_name, 'street_number' => $transaction->street_number, 'street_type' => $transaction->street_type]);

            return $identity && $reference && $identity['street'] === $reference['street'] && $identity['number'] === $reference['number']
                && (! $identity['type'] || ! $reference['type'] || $identity['type'] === $reference['type']);
        })->take(30)->values();
        $buildingTransactions = $this->filteredReferences($buildingTransactions, fn ($transaction) => $this->transactionPricePerM2($transaction, $item));

        if ($buildingTransactions->count() >= 3) {
            return [
                'method' => 'itbi_same_building_median_m2',
                'tier' => 'Mesmo prédio/condomínio',
                'transactions' => $buildingTransactions,
            ];
        }

        $radius500 = $this->filteredReferences($this->transactionsByRadius($item, 500), fn ($transaction) => $this->transactionPricePerM2($transaction, $item));
        if ($radius500->count() >= 3) {
            return [
                'method' => 'itbi_radius_500m_median_m2',
                'tier' => 'Raio de 500m',
                'transactions' => $radius500,
            ];
        }

        $radius900 = $this->filteredReferences($this->transactionsByRadius($item, 900), fn ($transaction) => $this->transactionPricePerM2($transaction, $item));
        if ($radius900->count() >= 3) {
            return [
                'method' => 'itbi_radius_900m_median_m2',
                'tier' => 'Raio de 900m',
                'transactions' => $radius900,
            ];
        }

        $neighborhood = $this->transactionsBaseQuery($item);
        $this->applyNeighborhoodFilter($neighborhood, $item);
        $neighborhoodTransactions = $this->filteredReferences($neighborhood->limit(80)->get(), fn ($transaction) => $this->transactionPricePerM2($transaction, $item));

        if ($neighborhoodTransactions->isNotEmpty()) {
            return [
                'method' => 'itbi_neighborhood_median_m2',
                'tier' => 'Mesmo bairro',
                'transactions' => $neighborhoodTransactions,
            ];
        }

        $city = $this->transactionsBaseQuery($item);
        $this->applyCityFilter($city, $item);

        return [
            'method' => 'itbi_city_median_m2',
            'tier' => 'Mesma cidade',
            'transactions' => $this->filteredReferences($city->limit(80)->get(), fn ($transaction) => $this->transactionPricePerM2($transaction, $item)),
        ];
    }

    private function transactionsBaseQuery(array $item): Builder
    {
        $area = (float) ($item['area_m2'] ?? 0);

        $query = RealEstateTransaction::query()
            ->whereDate('transaction_date', '>=', now()->subMonthsNoOverflow($this->transactionWindowMonths())->toDateString())
            ->whereDate('transaction_date', '<=', now()->toDateString())
            ->where(function (Builder $query) {
                $query->whereNotNull('declared_transaction_value')
                    ->orWhereNotNull('tax_base_value');
            })
            ->when($area > 0, function (Builder $query) use ($area) {
                $minArea = max(20, $area * 0.65);
                $maxArea = $area * 1.45;

                $query->where(function (Builder $query) use ($minArea, $maxArea) {
                    $query->whereBetween('calculation_area_m2', [$minArea, $maxArea])
                        ->orWhereBetween('private_area_m2', [$minArea, $maxArea])
                        ->orWhereBetween('built_area_m2', [$minArea, $maxArea]);
                });
            })
            ->latest('transaction_date');

        $this->applyTransactionPropertyTypeFilter($query, $item);
        $this->applyCityFilter($query, $item);
        $state = strtoupper(trim((string) ($item['state'] ?? '')));
        $state !== '' ? $query->whereRaw('UPPER(state) = ?', [$state]) : $query->whereRaw('1 = 0');

        return $query;
    }

    private function transactionWindowMonths(): int
    {
        return max(1, min(120, (int) config('real_estate_data.avm.max_transaction_age_months', 24)));
    }

    private function applyTransactionPropertyTypeFilter(Builder $query, array $item): void
    {
        $type = $this->propertyTypeFromItem($item);

        if ($type === 'apartment') {
            $query->where(function (Builder $query) {
                $query->whereRaw('LOWER(COALESCE(property_type, "")) LIKE ?', ['%apart%'])
                    ->orWhereRaw('LOWER(COALESCE(iptu_use_description, "")) LIKE ?', ['%apart%'])
                    ->orWhereRaw('LOWER(COALESCE(iptu_use_description, "")) LIKE ?', ['%resid%']);
            });

            $this->excludeNonResidentialTransactionUses($query);

            return;
        }

        if ($type === 'house') {
            $query->where(function (Builder $query) {
                $query->whereRaw('LOWER(COALESCE(property_type, "")) LIKE ?', ['%casa%'])
                    ->orWhereRaw('LOWER(COALESCE(property_type, "")) LIKE ?', ['%sobrado%'])
                    ->orWhereRaw('LOWER(COALESCE(iptu_use_description, "")) LIKE ?', ['%resid%']);
            });

            $query->whereRaw('LOWER(COALESCE(iptu_use_description, "")) NOT LIKE ?', ['%apart%']);
            $this->excludeNonResidentialTransactionUses($query);

            return;
        }

        if ($type === 'commercial') {
            $query->where(function (Builder $query) {
                $query->whereRaw('LOWER(COALESCE(property_type, "")) LIKE ?', ['%comerc%'])
                    ->orWhereRaw('LOWER(COALESCE(iptu_use_description, "")) LIKE ?', ['%comerc%'])
                    ->orWhereRaw('LOWER(COALESCE(iptu_use_description, "")) LIKE ?', ['%loja%'])
                    ->orWhereRaw('LOWER(COALESCE(iptu_use_description, "")) LIKE ?', ['%sala%'])
                    ->orWhereRaw('LOWER(COALESCE(iptu_use_description, "")) LIKE ?', ['%galp%']);
            });
        }
    }

    private function excludeNonResidentialTransactionUses(Builder $query): void
    {
        foreach (['%terreno%', '%comerc%', '%loja%', '%sala%', '%garagem%', '%posto%', '%hotel%', '%industrial%', '%galp%'] as $blockedUse) {
            $query->whereRaw('LOWER(COALESCE(iptu_use_description, "")) NOT LIKE ?', [$blockedUse]);
        }
    }

    private function propertyTypeFromItem(array $item): string
    {
        $explicit = Str::of((string) ($item['property_type'] ?? $item['requested_property_type'] ?? ''))->lower()->ascii()->trim()->toString();
        if ($explicit !== '') {
            return $explicit;
        }

        $text = Str::of(implode(' ', array_filter([
            $item['title'] ?? null,
            $item['description'] ?? null,
        ])))->lower()->ascii()->toString();

        if (Str::contains($text, ['apartamento', 'apto', 'studio', 'kitnet'])) {
            return 'apartment';
        }

        if (Str::contains($text, ['casa', 'sobrado'])) {
            return 'house';
        }

        if (Str::contains($text, ['comercial', 'loja', 'sala', 'galpao'])) {
            return 'commercial';
        }

        return '';
    }

    private function transactionPricePerM2(RealEstateTransaction $transaction, array $item = []): float
    {
        $area = $this->comparableTransactionArea($transaction, $item);
        $price = (float) ($transaction->declared_transaction_value ?: $transaction->tax_base_value ?: 0);
        if ($price > 0 && $area > 0) {
            return $price / $area;
        }

        $stored = (float) ($transaction->price_m2 ?? 0);
        if ($stored > 0 && $stored >= 1000) {
            return $stored;
        }

        return 0;
    }

    private function comparableTransactionArea(RealEstateTransaction $transaction, array $item): float
    {
        $subjectArea = (float) ($item['area_m2'] ?? 0);
        $areas = collect([
            (float) ($transaction->private_area_m2 ?: 0),
            (float) ($transaction->built_area_m2 ?: 0),
            (float) ($transaction->calculation_area_m2 ?: 0),
        ])->filter(fn (float $area) => $area > 0)->unique()->values();

        if ($areas->isEmpty()) {
            return 0;
        }

        if ($subjectArea <= 0) {
            return (float) $areas->first();
        }

        $minArea = max(20, $subjectArea * 0.65);
        $maxArea = $subjectArea * 1.45;
        $insideRange = $areas
            ->filter(fn (float $area) => $area >= $minArea && $area <= $maxArea)
            ->sortBy(fn (float $area) => abs($area - $subjectArea))
            ->values();

        return (float) ($insideRange->first() ?: $areas->sortBy(fn (float $area) => abs($area - $subjectArea))->first());
    }

    private function applyBuildingFilter(Builder $query, array $item): void
    {
        $identity = $this->buildingIdentity($item);
        if (! $identity) {
            $query->whereRaw('1 = 0');

            return;
        }
        $aliases = array_map(fn ($prefix) => trim($prefix.' '.$identity['street']), ['', 'rua', 'r', 'r.', 'avenida', 'av', 'av.', 'alameda', 'travessa', 'estrada', 'praca']);
        $query->whereRaw('LOWER(TRIM(street_number)) = ?', [$identity['number']])
            ->whereRaw($this->locationExpression('street_name').' IN ('.implode(',', array_fill(0, count($aliases), '?')).')', $aliases);
    }

    private function buildingIdentity(array $item): ?array
    {
        $street = trim((string) ($item['street_name'] ?? ''));
        $number = trim((string) ($item['street_number'] ?? ''));
        if ($street === '' && preg_match('/^(.+?),\s*(\d+[a-z]?)\b/iu', (string) ($item['address'] ?? ''), $parts)) {
            $street = $parts[1];
            $number = $parts[2];
        }
        if ($street === '' || ! preg_match('/^\d+[a-z]?$/i', $number)) {
            return null;
        }
        $street = $this->clean($street);
        $type = $this->clean((string) ($item['street_type'] ?? ''));
        if (preg_match('/^(rua|r\.?|avenida|av\.?|alameda|travessa|estrada|praca)\s+/u', $street, $parts)) {
            $type = $parts[1];
            $street = trim(substr($street, strlen($parts[0])));
        }
        $type = match (rtrim($type, '.')) {
            'r', 'rua' => 'rua', 'av', 'avenida' => 'avenida', default => rtrim($type, '.')
        };

        return $street !== '' ? ['street' => $street, 'number' => strtolower($number), 'type' => $type] : null;
    }

    private function applyNeighborhoodFilter(Builder $query, array $item): void
    {
        $neighborhood = $this->clean((string) ($item['neighborhood'] ?? ''));
        if ($neighborhood === '') {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereRaw($this->locationExpression('neighborhood').' = ?', [$neighborhood]);
    }

    private function applyCityFilter(Builder $query, array $item): void
    {
        $city = $this->clean((string) ($item['city'] ?? ''));
        if ($city === '') {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereRaw($this->locationExpression('city').' = ?', [$city]);
    }

    private function locationExpression(string $column): string
    {
        $expression = $column;
        foreach (['a' => 'áàâãä', 'e' => 'éèêë', 'i' => 'íìîï', 'o' => 'óòôõö', 'u' => 'úùûü', 'c' => 'ç'] as $plain => $accented) {
            foreach (mb_str_split($accented.mb_strtoupper($accented)) as $character) {
                $expression = "REPLACE({$expression}, '{$character}', '{$plain}')";
            }
        }

        return 'LOWER(TRIM('.$expression.'))';
    }

    private function transactionsByRadius(array $item, int $radiusMeters): Collection
    {
        $rawLat = $item['latitude'] ?? $item['lat'] ?? null;
        $rawLng = $item['longitude'] ?? $item['lng'] ?? null;
        if (! is_numeric($rawLat) || ! is_numeric($rawLng)) {
            return collect();
        }
        $lat = (float) $rawLat;
        $lng = (float) $rawLng;
        if (! is_finite($lat) || ! is_finite($lng) || abs($lat) > 90 || abs($lng) > 180) {
            return collect();
        }
        $radiusKm = $radiusMeters / 1000;
        $latDelta = rad2deg($radiusKm / 6371);
        $lngDelta = abs($lat) + $latDelta >= 90 ? 180 : rad2deg(asin(min(1, sin($radiusKm / 6371) / max(0.00000001, abs(cos(deg2rad($lat)))))));
        $query = $this->transactionsBaseQuery($item)->reorder()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [max(-90, $lat - $latDelta), min(90, $lat + $latDelta)]);
        if ($lng - $lngDelta < -180) {
            $query->where(fn (Builder $q) => $q->where('longitude', '>=', $lng - $lngDelta + 360)->orWhere('longitude', '<=', $lng + $lngDelta));
        } elseif ($lng + $lngDelta > 180) {
            $query->where(fn (Builder $q) => $q->where('longitude', '>=', $lng - $lngDelta)->orWhere('longitude', '<=', $lng + $lngDelta - 360));
        } else {
            $query->whereBetween('longitude', [$lng - $lngDelta, $lng + $lngDelta]);
        }

        $nearest = new \SplPriorityQueue;
        $nearest->setExtractFlags(\SplPriorityQueue::EXTR_DATA);
        foreach ($query->lazyById(250) as $transaction) {
            $referencePrice = $this->transactionPricePerM2($transaction, $item);
            if (! is_finite($referencePrice) || $referencePrice <= 1000 || $referencePrice >= 100000) {
                continue;
            }
            $referenceLat = (float) $transaction->latitude;
            $referenceLng = (float) $transaction->longitude;
            $a = sin(deg2rad($referenceLat - $lat) / 2) ** 2 + cos(deg2rad($lat)) * cos(deg2rad($referenceLat)) * sin(deg2rad($referenceLng - $lng) / 2) ** 2;
            $distance = 2 * 6371 * asin(sqrt(max(0, min(1, $a))));
            if ($distance > $radiusKm) {
                continue;
            }
            $transaction->setAttribute('distance_km', $distance);
            $nearest->insert($transaction, [$distance, -$transaction->transaction_date->getTimestamp(), -$transaction->id]);
            if ($nearest->count() > 80) {
                $nearest->extract();
            }
        }

        return collect(iterator_to_array($nearest, false))->sortBy('distance_km')->values();
    }

    private function adjustedPricePerM2(float $basePricePerM2, array $item, Collection $referenceComparables): float
    {
        $adjustment = 1.0;
        $parking = (int) ($item['parking_spaces'] ?? 0);
        $bedrooms = (int) ($item['bedrooms'] ?? 0);

        $medianParking = (int) round($this->median($referenceComparables->pluck('parking_spaces')->filter(fn ($value) => $value !== null)->map(fn ($value) => (float) $value)->all()));
        $medianBedrooms = (int) round($this->median($referenceComparables->pluck('bedrooms')->filter(fn ($value) => $value !== null)->map(fn ($value) => (float) $value)->all()));

        if ($parking > 0 && $medianParking > 0) {
            $adjustment += max(-0.08, min(0.08, ($parking - $medianParking) * 0.04));
        }

        if ($bedrooms > 0 && $medianBedrooms > 0) {
            $adjustment += max(-0.05, min(0.05, ($bedrooms - $medianBedrooms) * 0.025));
        }

        return $basePricePerM2 * max(0.85, min(1.15, $adjustment));
    }

    private function valuationExplanation(array $item, array $transactionPack, int $listedComparableCount, float $confirmedMedianPricePerM2, float $finalPricePerM2, float $rangeFactor): array
    {
        return [
            'steps' => [
                ['label' => 'Vendas reais', 'value' => $transactionPack['transactions']->count().' transações — '.$transactionPack['tier']],
                ['label' => 'Período das transações', 'value' => 'Últimos '.$this->transactionWindowMonths().' meses; datas futuras e ausentes excluídas'],
                ['label' => 'Anúncios ativos semelhantes', 'value' => $listedComparableCount.' anúncios usados como apoio'],
                ['label' => 'Ajuste por m²', 'value' => $finalPricePerM2 > 0 ? 'Preço final: R$ '.number_format($finalPricePerM2, 2, ',', '.').'/m²' : 'Sem área suficiente'],
                ['label' => 'Ajuste por vagas', 'value' => isset($item['parking_spaces']) ? ((int) $item['parking_spaces']).' vaga(s) considerada(s)' : 'Sem dado de vaga'],
                ['label' => 'Ajuste por quartos', 'value' => isset($item['bedrooms']) ? ((int) $item['bedrooms']).' quarto(s) considerado(s)' : 'Sem dado de quartos'],
                ['label' => 'Idade do prédio', 'value' => 'Não informada; sem ajuste aplicado'],
                ['label' => 'Andar', 'value' => 'Não informado; sem ajuste aplicado'],
                ['label' => 'Cobertura da base', 'value' => 'Quantidade de referências informa cobertura; não mede tempo de venda nem aumenta o preço'],
                ['label' => 'Tendência FIPEZAP', 'value' => 'Pendente de integração; sem ajuste aplicado'],
                ['label' => 'Faixa de sensibilidade', 'value' => '±'.number_format($rangeFactor * 100, 1, ',', '.').'% — regra de cenário, sem calibração estatística'],
            ],
            'confirmed_median_price_m2' => $confirmedMedianPricePerM2 ? round($confirmedMedianPricePerM2, 2) : null,
        ];
    }

    private function clean(string $value): string
    {
        return Str::of($value)->lower()->ascii()->trim()->toString();
    }

    private function cleanAddress(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->ascii()
            ->replaceMatches('/\b(apartamento|apto|casa|sobrado|studio|imovel|imoveis|a venda|venda|com|em|quartos|quarto|dormitorios|suite)\b/', ' ')
            ->replaceMatches('/[^\w\s]/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }
}
