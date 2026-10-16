<?php

namespace App\Services\MarketData;

use App\Contracts\PropertyTransactionProviderInterface;
use App\Models\RealEstateTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ImportedItbiTransactionProvider implements PropertyTransactionProviderInterface
{
    public function key(): string
    {
        return 'imported_itbi';
    }

    public function label(): string
    {
        return 'ITBI importado';
    }

    public function search(array $subject): array
    {
        $query = RealEstateTransaction::query()
            ->where(function (Builder $query) {
                $query->whereNotNull('declared_transaction_value')
                    ->orWhereNotNull('tax_base_value');
            })
            ->whereNotNull('transaction_date')
            ->where(function (Builder $query) {
                $query->whereNotNull('calculation_area_m2')
                    ->orWhereNotNull('private_area_m2')
                    ->orWhereNotNull('built_area_m2');
            });

        $this->applyLocationFilter($query, $subject);
        $this->applyAreaFilter($query, $subject);
        $this->applyPropertyTypeFilter($query, $subject);

        $transactions = $query
            ->latest('transaction_date')
            ->limit(80)
            ->get()
            ->map(fn (RealEstateTransaction $transaction) => $this->payload($transaction, $subject))
            ->filter(fn (array $transaction) => (float) ($transaction['price_m2'] ?? 0) >= 1000 && (float) ($transaction['price_m2'] ?? 0) <= 100000)
            ->values()
            ->all();

        return [
            'key' => $this->key(),
            'label' => $this->label(),
            'status' => 'connected',
            'message' => count($transactions).' vendas reais encontradas na planilha ITBI importada.',
            'transactions' => $transactions,
        ];
    }

    private function applyAreaFilter(Builder $query, array $subject): void
    {
        $area = (float) ($subject['area_m2'] ?? 0);
        if ($area <= 0) {
            return;
        }

        $minArea = max(20, $area * 0.65);
        $maxArea = $area * 1.45;

        $query->where(function (Builder $query) use ($minArea, $maxArea) {
            $query->whereBetween('calculation_area_m2', [$minArea, $maxArea])
                ->orWhereBetween('private_area_m2', [$minArea, $maxArea])
                ->orWhereBetween('built_area_m2', [$minArea, $maxArea]);
        });
    }

    private function applyLocationFilter(Builder $query, array $subject): void
    {
        $neighborhood = $this->clean($subject['neighborhood'] ?? null);
        $city = $this->clean($subject['city'] ?? null);
        $street = $this->streetFromSubject($subject);

        if ($neighborhood !== '') {
            $query->where(function (Builder $query) use ($neighborhood) {
                $query->whereRaw('LOWER(neighborhood) = ?', [$neighborhood])
                    ->orWhereRaw('LOWER(neighborhood) LIKE ?', ['%'.$neighborhood.'%']);
            });

            return;
        }

        if ($street !== '') {
            $query->whereRaw('LOWER(street_name) LIKE ?', ['%'.$street.'%']);

            return;
        }

        if ($city !== '') {
            $query->whereRaw('LOWER(city) LIKE ?', ['%'.$city.'%']);
        }
    }

    private function applyPropertyTypeFilter(Builder $query, array $subject): void
    {
        $type = $this->propertyTypeFromSubject($subject);

        if ($type === 'apartment') {
            $query->where(function (Builder $query) {
                $query->whereRaw('LOWER(COALESCE(property_type, "")) LIKE ?', ['%apart%'])
                    ->orWhereRaw('LOWER(COALESCE(iptu_use_description, "")) LIKE ?', ['%apart%'])
                    ->orWhereRaw('LOWER(COALESCE(iptu_use_description, "")) LIKE ?', ['%resid%']);
            });

            $this->excludeNonResidentialUses($query);

            return;
        }

        if ($type === 'house') {
            $query->where(function (Builder $query) {
                $query->whereRaw('LOWER(COALESCE(property_type, "")) LIKE ?', ['%casa%'])
                    ->orWhereRaw('LOWER(COALESCE(property_type, "")) LIKE ?', ['%sobrado%'])
                    ->orWhereRaw('LOWER(COALESCE(iptu_use_description, "")) LIKE ?', ['%resid%']);
            });

            $query->whereRaw('LOWER(COALESCE(iptu_use_description, "")) NOT LIKE ?', ['%apart%']);
            $this->excludeNonResidentialUses($query);

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

    private function excludeNonResidentialUses(Builder $query): void
    {
        foreach (['%terreno%', '%comerc%', '%loja%', '%sala%', '%garagem%', '%posto%', '%hotel%', '%industrial%', '%galp%'] as $blockedUse) {
            $query->whereRaw('LOWER(COALESCE(iptu_use_description, "")) NOT LIKE ?', [$blockedUse]);
        }
    }

    private function propertyTypeFromSubject(array $subject): string
    {
        $explicit = $this->clean($subject['property_type'] ?? $subject['requested_property_type'] ?? '');
        if ($explicit !== '') {
            return $explicit;
        }

        $text = $this->clean(implode(' ', array_filter([
            $subject['title'] ?? null,
            $subject['description'] ?? null,
        ])));

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

    private function payload(RealEstateTransaction $transaction, array $subject): array
    {
        $price = (float) ($transaction->declared_transaction_value ?: $transaction->tax_base_value ?: 0);
        $area = $this->comparableArea($transaction, $subject);
        $address = $transaction->normalized_address ?: trim(implode(', ', array_filter([
            $transaction->street_name,
            $transaction->street_number,
            $transaction->address_complement,
        ])));

        return [
            'type' => 'confirmed_transaction',
            'source' => $this->label(),
            'source_reference' => $transaction->source_reference,
            'title' => 'Venda real registrada no ITBI',
            'address' => $address ?: 'Endereço não informado',
            'street_name' => $transaction->street_name,
            'street_number' => $transaction->street_number,
            'neighborhood' => $transaction->neighborhood,
            'city' => $transaction->city,
            'state' => $transaction->state,
            'transaction_date' => $transaction->transaction_date?->toDateString(),
            'price' => round($price, 2),
            'area_m2' => $area > 0 ? round($area, 2) : null,
            'price_m2' => (float) ($area > 0 ? $price / $area : ($transaction->price_m2 ?: 0)) ?: null,
            'registry_office' => $transaction->registry_office,
            'property_sql' => $transaction->property_sql,
            'similarity_percentage' => $this->similarity($transaction, $subject),
            'confidence_percentage' => 92,
            'raw_payload' => [
                'transaction_id' => $transaction->id,
                'property_sql' => $transaction->property_sql,
                'transaction_nature' => $transaction->transaction_nature,
                'registry_office' => $transaction->registry_office,
            ],
        ];
    }

    private function similarity(RealEstateTransaction $transaction, array $subject): int
    {
        $score = 65;

        if ($this->clean($transaction->neighborhood) !== '' && $this->clean($transaction->neighborhood) === $this->clean($subject['neighborhood'] ?? null)) {
            $score += 15;
        }

        $subjectArea = (float) ($subject['area_m2'] ?? 0);
        $area = $this->comparableArea($transaction, $subject);
        if ($subjectArea > 0 && $area > 0) {
            $variation = abs($area - $subjectArea) / max($subjectArea, 1);
            $score += $variation <= 0.2 ? 15 : ($variation <= 0.4 ? 8 : 0);
        }

        return min(98, $score);
    }

    private function comparableArea(RealEstateTransaction $transaction, array $subject): float
    {
        $subjectArea = (float) ($subject['area_m2'] ?? 0);
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

    private function streetFromSubject(array $subject): string
    {
        $address = $this->clean($subject['address'] ?? $subject['title'] ?? '');
        $address = preg_replace('/\b(apartamento|casa|sobrado|studio|imovel|imóvel|a venda|à venda|venda)\b/u', ' ', $address);
        $address = preg_replace('/\s+/', ' ', (string) $address);

        return trim((string) $address);
    }

    private function clean(?string $value): string
    {
        return Str::of((string) $value)->lower()->ascii()->trim()->toString();
    }
}
