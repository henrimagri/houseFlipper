<?php

namespace App\Services\RealEstateData;

use Carbon\Carbon;
use Illuminate\Support\Str;

class ItbiTransactionNormalizerService
{
    public function __construct(private readonly AddressNormalizationService $addresses) {}

    public function normalize(array $row, int $rowNumber, string $sourceName): array
    {
        $mapped = $this->mapColumns($row);

        $data = [
            'source_type' => 'itbi',
            'source_name' => $sourceName,
            'property_sql' => $this->cleanText($mapped['property_sql'] ?? null),
            'transaction_date' => $this->date($mapped['transaction_date'] ?? null),
            'declared_transaction_value' => $this->number($mapped['declared_transaction_value'] ?? null),
            'tax_base_value' => $this->number($mapped['tax_base_value'] ?? null),
            'property_type' => $this->propertyType($mapped['property_type'] ?? null),
            'transaction_nature' => $this->cleanText($mapped['transaction_nature'] ?? null),
            'street_type' => $this->cleanText($mapped['street_type'] ?? null),
            'street_name' => $this->cleanText($mapped['street_name'] ?? null),
            'street_number' => $this->streetNumber($mapped['street_number'] ?? null),
            'address_complement' => $this->cleanText($mapped['address_complement'] ?? null),
            'address_reference' => $this->cleanText($mapped['address_reference'] ?? null),
            'neighborhood' => $this->cleanText($mapped['neighborhood'] ?? null),
            'district' => $this->cleanText($mapped['district'] ?? null),
            'city' => $this->cleanText($mapped['city'] ?? null) ?: 'São Paulo',
            'state' => strtoupper($this->cleanText($mapped['state'] ?? null) ?: 'SP'),
            'zip_code' => $this->zipCode($mapped['zip_code'] ?? null),
            'financing_type' => $this->cleanText($mapped['financing_type'] ?? null),
            'registry_office' => $this->cleanText($mapped['registry_office'] ?? null),
            'registry_registration' => $this->cleanText($mapped['registry_registration'] ?? null),
            'land_area_m2' => $this->number($mapped['land_area_m2'] ?? null),
            'built_area_m2' => $this->number($mapped['built_area_m2'] ?? null),
            'private_area_m2' => $this->number($mapped['private_area_m2'] ?? null),
            'calculation_area_m2' => $this->number($mapped['calculation_area_m2'] ?? null),
            'iptu_use_description' => $this->cleanText($mapped['iptu_use_description'] ?? null),
            'iptu_pattern_description' => $this->cleanText($mapped['iptu_pattern_description'] ?? null),
            'raw_payload' => [
                'row_number' => $rowNumber,
                'recognized' => array_keys($mapped),
                'raw' => $row,
            ],
        ];

        $area = $this->selectedArea($data);
        $value = $this->transactionValue($data);
        $data['calculation_area_source'] = $area['source'];
        $data['price_m2'] = $value > 0 && $area['value'] > 0 ? round($value / $area['value'], 2) : null;
        $data['normalized_address'] = $this->addresses->normalizedAddress($data);
        $data['address_hash'] = $this->addresses->hash($data['normalized_address']);
        $data['building_key'] = $this->addresses->buildingKey($data);
        $data['source_reference'] = $this->sourceReference($data, $rowNumber);

        return $data;
    }

    private function mapColumns(array $row): array
    {
        $aliases = collect(config('real_estate_data.column_aliases'))
            ->mapWithKeys(fn (array $items, string $field) => [($field) => collect($items)->map(fn ($alias) => $this->key($alias))->all()]);

        $mapped = [];
        foreach ($row as $column => $value) {
            $key = $this->key($column);
            foreach ($aliases as $field => $fieldAliases) {
                if ($key === $field || in_array($key, $fieldAliases, true)) {
                    $mapped[$field] = $value;
                    break;
                }
            }
        }

        return $mapped;
    }

    private function sourceReference(array $data, int $rowNumber): string
    {
        $parts = [
            $data['property_sql'] ?: null,
            $data['transaction_date'] ?: null,
            $data['declared_transaction_value'] ?: $data['tax_base_value'] ?: null,
            $data['registry_office'] ?: null,
        ];

        $fingerprint = collect($parts)->filter()->implode('|') ?: 'row|'.$rowNumber.'|'.json_encode($data['raw_payload']['raw']);

        return hash('sha256', $fingerprint);
    }

    private function selectedArea(array $data): array
    {
        foreach (['private_area_m2', 'built_area_m2', 'calculation_area_m2'] as $field) {
            if ((float) ($data[$field] ?? 0) > 0) {
                return ['source' => $field, 'value' => (float) $data[$field]];
            }
        }

        if (($data['property_type'] ?? null) === 'terreno' && (float) ($data['land_area_m2'] ?? 0) > 0) {
            return ['source' => 'land_area_m2', 'value' => (float) $data['land_area_m2']];
        }

        return ['source' => null, 'value' => 0.0];
    }

    private function transactionValue(array $data): float
    {
        $field = config('real_estate_data.avm.transaction_value_field', 'declared_transaction_value');

        return (float) ($data[$field] ?? 0);
    }

    private function number(mixed $value): ?float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $value = preg_replace('/[^\d,\.\-]/', '', $value) ?: '';
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    private function date(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (is_numeric($value) && (float) $value > 25000) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
        }

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'm/Y', 'Y/m'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->startOfDay()->toDateString();
            } catch (\Throwable) {
                //
            }
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function cleanText(mixed $value): ?string
    {
        $value = Str::of((string) $value)->squish()->toString();

        return $value === '' ? null : $value;
    }

    private function streetNumber(mixed $value): ?string
    {
        $value = $this->cleanText($value);

        return $value ? preg_replace('/[^\dA-Za-z\-\/]/', '', $value) : null;
    }

    private function zipCode(mixed $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        return $digits ?: null;
    }

    private function propertyType(mixed $value): ?string
    {
        $normalized = Str::of((string) $value)->lower()->ascii()->squish()->toString();
        if ($normalized === '') {
            return null;
        }

        if (Str::contains($normalized, ['terreno', 'lote'])) {
            return 'terreno';
        }
        if (Str::contains($normalized, ['apartamento', 'apto', 'residencial vertical'])) {
            return 'apartamento';
        }
        if (Str::contains($normalized, ['casa', 'sobrado'])) {
            return 'casa';
        }
        if (Str::contains($normalized, ['comercial', 'loja', 'sala'])) {
            return 'comercial';
        }

        return $normalized;
    }

    private function key(string $value): string
    {
        return Str::of($value)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
    }
}
