<?php

namespace App\Services;

use App\Models\RealEstateTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PropertyValuationService
{
    public function applyComparableMarketEstimates(Collection $items): Collection
    {
        $items = collect($items)->values();
        $comparables = $items
            ->filter(fn (array $item) => (float) ($item['asking_price'] ?? 0) > 0 && (float) ($item['area_m2'] ?? 0) >= 20)
            ->map(function (array $item) {
                $item['comparable_price_per_m2'] = (float) $item['asking_price'] / (float) $item['area_m2'];

                return $item;
            })
            ->values();

        $globalMedian = $this->medianWithoutOutliers($comparables->pluck('comparable_price_per_m2')->all());

        return $items->map(function (array $item) use ($comparables, $globalMedian) {
            $area = (float) ($item['area_m2'] ?? 0);
            $askingPrice = (float) ($item['asking_price'] ?? 0);
            $bedrooms = (int) ($item['bedrooms'] ?? 0);
            $city = Str::lower((string) ($item['city'] ?? ''));
            $neighborhood = Str::lower((string) ($item['neighborhood'] ?? ''));

            $localComparables = $comparables
                ->filter(function (array $comparable) use ($item, $city, $neighborhood, $bedrooms) {
                    if (($comparable['id'] ?? null) === ($item['id'] ?? null)) {
                        return false;
                    }

                    $sameNeighborhood = $neighborhood !== '' && Str::lower((string) ($comparable['neighborhood'] ?? '')) === $neighborhood;
                    $sameCity = $city !== '' && Str::lower((string) ($comparable['city'] ?? '')) === $city;
                    $sameBedrooms = $bedrooms === 0 || (int) ($comparable['bedrooms'] ?? 0) === 0 || abs((int) ($comparable['bedrooms'] ?? 0) - $bedrooms) <= 1;

                    return ($sameNeighborhood || $sameCity) && $sameBedrooms;
                })
                ->values();

            $localComparablePrices = $localComparables->pluck('comparable_price_per_m2')->all();
            $medianPricePerM2 = $this->medianWithoutOutliers($localComparablePrices) ?: $globalMedian;
            $referenceComparables = count($localComparablePrices) > 0 ? $localComparables : $comparables;
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
            $confirmedMedianPricePerM2 = $this->medianWithoutOutliers($transactionPrices);
            $transactionCount = $transactionPack['transactions']->count();
            $activeListingsWeight = $transactionCount >= 3 ? 0.15 : ($transactionCount > 0 ? 0.35 : 1.0);
            $realSalesWeight = 1 - $activeListingsWeight;

            if ($area > 0 && $confirmedMedianPricePerM2 > 0) {
                $basePricePerM2 = $medianPricePerM2 > 0
                    ? ($confirmedMedianPricePerM2 * $realSalesWeight) + ($medianPricePerM2 * $activeListingsWeight)
                    : $confirmedMedianPricePerM2;
                $adjustedPricePerM2 = $this->adjustedPricePerM2($basePricePerM2, $item, $referenceComparables, $transactionCount);
                $estimatedMarketPrice = $adjustedPricePerM2 * $area;
                $medianPricePerM2 = $adjustedPricePerM2;
                $method = $transactionPack['method'];
            } elseif ($area > 0 && $medianPricePerM2 > 0) {
                $adjustedPricePerM2 = $this->adjustedPricePerM2($medianPricePerM2, $item, $referenceComparables, 0);
                $estimatedMarketPrice = $adjustedPricePerM2 * $area;
                $medianPricePerM2 = $adjustedPricePerM2;
                $method = $neighborhood !== '' && count($localComparablePrices) >= 3 ? 'comparables_neighborhood_median_m2' : 'comparables_region_median_m2';
            } else {
                $estimatedMarketPrice = $this->estimateMarketPrice($askingPrice, $area, $item['title'] ?? '');
                $method = 'fallback_heuristic';
            }

            $discount = $estimatedMarketPrice > 0 ? max(0, ($estimatedMarketPrice - $askingPrice) / $estimatedMarketPrice * 100) : 0;
            $confidence = $this->confidence($referenceComparables->count(), $transactionCount, $item);
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
            $item['valuation_explanation'] = $this->valuationExplanation($item, $transactionPack, $referenceComparables->count(), $confirmedMedianPricePerM2, $medianPricePerM2, $rangeFactor);
            $item['score'] = min($this->score($discount, $area, $bedrooms, $askingPrice), $confidence < 50 ? 74 : 100);
            $item['signal'] = $item['score'] >= 80 ? 'Boa oportunidade' : ($item['score'] >= 65 ? 'Negociar' : 'Atenção');

            return $item;
        });
    }

    private function medianWithoutOutliers(array $values): float
    {
        $values = collect($values)
            ->map(fn ($value) => (float) $value)
            ->filter(fn (float $value) => $value > 1000 && $value < 100000)
            ->sort()
            ->values();

        if ($values->isEmpty()) {
            return 0;
        }

        if ($values->count() >= 5) {
            $q1 = $this->percentile($values->all(), 25);
            $q3 = $this->percentile($values->all(), 75);
            $iqr = $q3 - $q1;
            $min = $q1 - 1.5 * $iqr;
            $max = $q3 + 1.5 * $iqr;
            $values = $values->filter(fn (float $value) => $value >= $min && $value <= $max)->values();
        }

        return $this->median($values->all());
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
        $buildingTransactions = $building->limit(30)->get();

        if ($buildingTransactions->count() >= 3) {
            return [
                'method' => 'itbi_same_building_median_m2',
                'tier' => 'Mesmo prédio/condomínio',
                'transactions' => $buildingTransactions,
            ];
        }

        $radius500 = $this->transactionsByRadius($item, 500);
        if ($radius500->count() >= 3) {
            return [
                'method' => 'itbi_radius_500m_median_m2',
                'tier' => 'Raio de 500m',
                'transactions' => $radius500,
            ];
        }

        $radius900 = $this->transactionsByRadius($item, 900);
        if ($radius900->count() >= 3) {
            return [
                'method' => 'itbi_radius_900m_median_m2',
                'tier' => 'Raio de 900m',
                'transactions' => $radius900,
            ];
        }

        $neighborhood = $this->transactionsBaseQuery($item);
        $this->applyNeighborhoodFilter($neighborhood, $item);
        $neighborhoodTransactions = $neighborhood->limit(80)->get();

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
            'transactions' => $city->limit(80)->get(),
        ];
    }

    private function transactionsBaseQuery(array $item): Builder
    {
        $area = (float) ($item['area_m2'] ?? 0);

        $query = RealEstateTransaction::query()
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

        return $query;
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
        $rawAddress = (string) ($item['address'] ?? '');
        $address = $this->cleanAddress($rawAddress);
        $hasStreetSignal = preg_match('/\d/', $rawAddress) || Str::contains(Str::lower($rawAddress), ['rua', 'avenida', 'av.', 'alameda', 'travessa', 'r. ']);

        if ($address === '' || ! $hasStreetSignal) {
            $query->whereRaw('1 = 0');

            return;
        }

        $words = collect(explode(' ', $address))
            ->filter(fn (string $word) => strlen($word) >= 4 && ! is_numeric($word))
            ->take(4)
            ->values();

        if ($words->isEmpty()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $query) use ($words) {
            foreach ($words as $word) {
                $query->whereRaw('LOWER(normalized_address) LIKE ?', ['%'.$word.'%']);
            }
        });
    }

    private function applyNeighborhoodFilter(Builder $query, array $item): void
    {
        $neighborhood = $this->clean((string) ($item['neighborhood'] ?? ''));
        if ($neighborhood === '') {
            $this->applyCityFilter($query, $item);

            return;
        }

        $query->where(function (Builder $query) use ($neighborhood) {
            $query->whereRaw('LOWER(neighborhood) = ?', [$neighborhood])
                ->orWhereRaw('LOWER(neighborhood) LIKE ?', ['%'.$neighborhood.'%']);
        });
    }

    private function applyCityFilter(Builder $query, array $item): void
    {
        $city = $this->clean((string) ($item['city'] ?? ''));
        if ($city === '') {
            return;
        }

        $query->whereRaw('LOWER(city) LIKE ?', ['%'.$city.'%']);
    }

    private function transactionsByRadius(array $item, int $radiusMeters): Collection
    {
        $lat = (float) ($item['latitude'] ?? $item['lat'] ?? 0);
        $lng = (float) ($item['longitude'] ?? $item['lng'] ?? 0);

        if ($lat === 0.0 || $lng === 0.0) {
            return collect();
        }

        $radiusKm = $radiusMeters / 1000;

        return $this->transactionsBaseQuery($item)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->selectRaw('real_estate_transactions.*, (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) as distance_km', [$lat, $lng, $lat])
            ->having('distance_km', '<=', $radiusKm)
            ->orderBy('distance_km')
            ->limit(80)
            ->get();
    }

    private function adjustedPricePerM2(float $basePricePerM2, array $item, Collection $referenceComparables, int $transactionCount): float
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

        $liquiditySignal = min(0.03, (($transactionCount + $referenceComparables->count()) / 80) * 0.03);
        $adjustment += $liquiditySignal;

        return $basePricePerM2 * max(0.85, min(1.15, $adjustment));
    }

    private function valuationExplanation(array $item, array $transactionPack, int $listedComparableCount, float $confirmedMedianPricePerM2, float $finalPricePerM2, float $rangeFactor): array
    {
        return [
            'steps' => [
                ['label' => 'Vendas reais', 'value' => $transactionPack['transactions']->count().' transações — '.$transactionPack['tier']],
                ['label' => 'Anúncios ativos semelhantes', 'value' => $listedComparableCount.' anúncios usados como apoio'],
                ['label' => 'Ajuste por m²', 'value' => $finalPricePerM2 > 0 ? 'Preço final: R$ '.number_format($finalPricePerM2, 2, ',', '.').'/m²' : 'Sem área suficiente'],
                ['label' => 'Ajuste por vagas', 'value' => isset($item['parking_spaces']) ? ((int) $item['parking_spaces']).' vaga(s) considerada(s)' : 'Sem dado de vaga'],
                ['label' => 'Ajuste por quartos', 'value' => isset($item['bedrooms']) ? ((int) $item['bedrooms']).' quarto(s) considerado(s)' : 'Sem dado de quartos'],
                ['label' => 'Idade do prédio', 'value' => 'Não informada; sem ajuste aplicado'],
                ['label' => 'Andar', 'value' => 'Não informado; sem ajuste aplicado'],
                ['label' => 'Liquidez do bairro', 'value' => 'Ajustada pela quantidade de vendas/anúncios encontrados'],
                ['label' => 'Tendência FIPEZAP', 'value' => 'Pendente de integração; sem ajuste aplicado'],
                ['label' => 'Intervalo de confiança', 'value' => '±'.number_format($rangeFactor * 100, 1, ',', '.').'%'],
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
