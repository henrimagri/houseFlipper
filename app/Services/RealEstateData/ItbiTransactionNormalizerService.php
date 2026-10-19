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

        $sourceName = $this->boundedText($sourceName, 160, 'source_name');
        if ($sourceName === null) {
            throw new \UnexpectedValueException('A fonte do registro ITBI não pode ser vazia.');
        }

        $data = [
            'source_type' => 'itbi',
            'source_name' => $sourceName,
            'property_sql' => $this->boundedText($mapped['property_sql'] ?? null, 80, 'property_sql'),
            'transaction_date' => $this->date($mapped['transaction_date'] ?? null),
            'declared_transaction_value' => $this->boundedNumber($mapped['declared_transaction_value'] ?? null, 9999999999999.99, 'declared_transaction_value'),
            'tax_base_value' => $this->boundedNumber($mapped['tax_base_value'] ?? null, 9999999999999.99, 'tax_base_value'),
            'property_type' => $this->boundedText($this->propertyType($mapped['property_type'] ?? null), 80, 'property_type'),
            'transaction_nature' => $this->boundedText($mapped['transaction_nature'] ?? null, 120, 'transaction_nature'),
            'street_type' => $this->boundedText($mapped['street_type'] ?? null, 40, 'street_type'),
            'street_name' => $this->boundedText($mapped['street_name'] ?? null, 180, 'street_name'),
            'street_number' => $this->streetNumber($mapped['street_number'] ?? null),
            'address_complement' => $this->boundedText($mapped['address_complement'] ?? null, 120, 'address_complement'),
            'address_reference' => $this->boundedText($mapped['address_reference'] ?? null, 160, 'address_reference'),
            'neighborhood' => $this->boundedText($mapped['neighborhood'] ?? null, 120, 'neighborhood'),
            'district' => $this->boundedText($mapped['district'] ?? null, 120, 'district'),
            'city' => $this->boundedText($mapped['city'] ?? null, 120, 'city') ?: 'São Paulo',
            'state' => $this->state($mapped['state'] ?? null),
            'zip_code' => $this->zipCode($mapped['zip_code'] ?? null),
            'financing_type' => $this->boundedText($mapped['financing_type'] ?? null, 160, 'financing_type'),
            'registry_office' => $this->boundedText($mapped['registry_office'] ?? null, 120, 'registry_office'),
            'registry_registration' => $this->boundedText($mapped['registry_registration'] ?? null, 80, 'registry_registration'),
            'land_area_m2' => $this->boundedNumber($mapped['land_area_m2'] ?? null, 9999999999.99, 'land_area_m2'),
            'built_area_m2' => $this->boundedNumber($mapped['built_area_m2'] ?? null, 9999999999.99, 'built_area_m2'),
            'private_area_m2' => $this->boundedNumber($mapped['private_area_m2'] ?? null, 9999999999.99, 'private_area_m2'),
            'calculation_area_m2' => $this->boundedNumber($mapped['calculation_area_m2'] ?? null, 9999999999.99, 'calculation_area_m2'),
            'iptu_use_description' => $this->boundedBytes($mapped['iptu_use_description'] ?? null, 65535, 'iptu_use_description'),
            'iptu_pattern_description' => $this->boundedBytes($mapped['iptu_pattern_description'] ?? null, 65535, 'iptu_pattern_description'),
            'raw_payload' => [
                'row_number' => $rowNumber,
                'recognized' => array_keys($mapped),
                'raw' => $row,
            ],
        ];

        $area = $this->selectedArea($data);
        $value = $this->transactionValue($data);
        $data['calculation_area_source'] = $area['source'];
        $priceM2 = $value > 0 && $area['value'] > 0 ? round($value / $area['value'], 2) : null;
        if ($priceM2 !== null && (! is_finite($priceM2) || $priceM2 > 9999999999999.99)) {
            throw new \UnexpectedValueException('price_m2 excede o limite do banco.');
        }
        $data['price_m2'] = $priceM2;
        $data['normalized_address'] = $this->boundedText($this->addresses->normalizedAddress($data), 255, 'normalized_address');
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

    public function sourceReference(array $data, int $rowNumber = 0): string
    {
        $parts = [
            $data['property_sql'] ?: null,
            $data['transaction_date'] ?: null,
            $data['declared_transaction_value'] ?: $data['tax_base_value'] ?: null,
            $data['registry_office'] ?: null,
        ];

        $fingerprint = collect($parts)->filter()->implode('|');
        if (empty($data['property_sql'])) {
            $identity = [
                $data['normalized_address'] ?? null,
                $data['address_complement'] ?? null,
                $data['registry_registration'] ?? null,
            ];
            if (filled($data['street_name'] ?? null) || filled($data['registry_registration'] ?? null)) {
                $fingerprint .= '|property|'.json_encode($identity, JSON_UNESCAPED_UNICODE);
            } else {
                $fingerprint .= '|raw|'.json_encode($data['raw_payload']['raw'] ?? $data);
            }
        }
        $fingerprint = $fingerprint ?: 'row|'.$rowNumber.'|'.json_encode($data['raw_payload']['raw']);

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

    private function boundedNumber(mixed $value, float $max, string $field): ?float
    {
        $number = $this->number($value);
        if ($number !== null && (! is_finite($number) || $number < 0 || $number > $max)) {
            throw new \UnexpectedValueException("{$field} excede o limite do banco.");
        }

        return $number;
    }

    private function date(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (is_numeric($value) && (float) $value > 25000 && (float) $value <= 2958465) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
        }

        $formats = [
            'd/m/Y' => '/^\d{2}\/\d{2}\/\d{4}$/',
            'd-m-Y' => '/^\d{2}-\d{2}-\d{4}$/',
            'Y-m-d' => '/^\d{4}-\d{2}-\d{2}$/',
            'm/Y' => '/^\d{2}\/\d{4}$/',
            'Y/m' => '/^\d{4}\/\d{2}$/',
        ];
        foreach ($formats as $format => $pattern) {
            if (! preg_match($pattern, $value)) {
                continue;
            }
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);

                return $date && $date->format($format) === $value ? $date->toDateString() : null;
            } catch (\Throwable) {
                return null;
            }
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})[T ]/', $value, $parts)
            && ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            return null;
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

    private function boundedText(mixed $value, int $max, string $field): ?string
    {
        $text = $this->cleanText($value);
        if ($text !== null && mb_strlen($text) > $max) {
            throw new \UnexpectedValueException("{$field} excede o limite do banco.");
        }

        return $text;
    }

    private function boundedBytes(mixed $value, int $max, string $field): ?string
    {
        $text = $this->cleanText($value);
        if ($text !== null && strlen($text) > $max) {
            throw new \UnexpectedValueException("{$field} excede o limite do banco.");
        }

        return $text;
    }

    private function streetNumber(mixed $value): ?string
    {
        $value = $this->cleanText($value);
        $number = $value ? preg_replace('/[^\dA-Za-z\-\/]/', '', $value) : null;

        return $this->boundedText($number, 40, 'street_number');
    }

    private function zipCode(mixed $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        return $this->boundedText($digits ?: null, 12, 'zip_code');
    }

    private function state(mixed $value): string
    {
        $state = strtoupper($this->cleanText($value) ?: 'SP');
        if (preg_match('/^[A-Z]{2}$/', $state) !== 1) {
            throw new \UnexpectedValueException('state não possui o formato aceito pelo banco.');
        }

        return $state;
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
