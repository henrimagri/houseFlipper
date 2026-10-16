<?php

namespace App\Services\RealEstateData;

use Illuminate\Support\Str;

class AddressNormalizationService
{
    public function normalize(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $value = Str::of($value)
            ->squish()
            ->replaceMatches('/\bAV\b\.?/i', 'Avenida')
            ->replaceMatches('/\bR\b\.?/i', 'Rua')
            ->replaceMatches('/\bAL\b\.?/i', 'Alameda')
            ->title()
            ->toString();

        return $value ?: null;
    }

    public function normalizedAddress(array $data): ?string
    {
        $parts = collect([
            $data['street_type'] ?? null,
            $data['street_name'] ?? null,
            $data['street_number'] ?? null,
            $data['neighborhood'] ?? null,
            $data['city'] ?? 'São Paulo',
            $data['state'] ?? 'SP',
            'Brasil',
        ])->filter(fn ($part) => filled($part))->implode(', ');

        return $this->normalize($parts);
    }

    public function hash(?string $normalizedAddress): ?string
    {
        return $normalizedAddress ? hash('sha256', Str::of($normalizedAddress)->lower()->ascii()->squish()->toString()) : null;
    }

    public function buildingKey(array $data): ?string
    {
        $parts = collect([
            $data['street_name'] ?? null,
            $data['street_number'] ?? null,
            $data['zip_code'] ?? null,
        ])->filter(fn ($part) => filled($part))->implode('|');

        return $parts ? hash('sha256', Str::of($parts)->lower()->ascii()->squish()->toString()) : null;
    }
}
