<?php

namespace App\Services\Ai;

use Illuminate\Support\Str;

class PropertyQueryInterpreter
{
    private const CITIES = [
        'fortaleza' => ['city' => 'Fortaleza', 'state' => 'CE'],
        'sao paulo' => ['city' => 'São Paulo', 'state' => 'SP'],
        'rio de janeiro' => ['city' => 'Rio de Janeiro', 'state' => 'RJ'],
        'belo horizonte' => ['city' => 'Belo Horizonte', 'state' => 'MG'],
        'curitiba' => ['city' => 'Curitiba', 'state' => 'PR'],
        'porto alegre' => ['city' => 'Porto Alegre', 'state' => 'RS'],
        'recife' => ['city' => 'Recife', 'state' => 'PE'],
        'salvador' => ['city' => 'Salvador', 'state' => 'BA'],
        'brasilia' => ['city' => 'Brasília', 'state' => 'DF'],
        'campinas' => ['city' => 'Campinas', 'state' => 'SP'],
        'florianopolis' => ['city' => 'Florianópolis', 'state' => 'SC'],
        'osasco' => ['city' => 'Osasco', 'state' => 'SP'],
    ];

    private const NEIGHBORHOODS = [
        'aldeota', 'meireles', 'coco', 'papicu', 'perdizes', 'moema', 'pinheiros',
        'vila mariana', 'itaim bibi', 'copacabana', 'ipanema', 'leblon', 'barra da tijuca',
        'savassi', 'batel', 'centro',
    ];

    private const FEATURES = [
        'varanda' => 'balcony',
        'sacada' => 'balcony',
        'piscina' => 'pool',
        'elevador' => 'elevator',
        'reformado' => 'renovated',
        'mobiliado' => 'furnished',
        'vista mar' => 'sea_view',
        'area gourmet' => 'gourmet',
    ];

    public function interpret(string $message, array $previous = []): array
    {
        $raw = Str::of($message)->replaceMatches('/[\x00-\x1F\x7F]+/u', ' ')->squish()->limit(2000, '')->toString();
        $normalized = $this->normalize($raw);
        $intent = $this->intent($normalized, $previous);
        $extracted = [
            'intent' => $intent,
            'required' => [],
            'preferred' => [],
            'unknowns' => [],
            'warnings' => [],
        ];

        $this->extractType($normalized, $extracted);
        $this->extractLocation($normalized, $extracted);
        $this->extractPrice($normalized, $extracted);
        $this->extractCounts($normalized, $extracted);
        $this->extractArea($normalized, $extracted);
        $this->extractFeatures($normalized, $extracted);
        $this->extractLimitations($normalized, $extracted);

        if ($intent === 'refine' || $intent === 'compare' || $intent === 'recommend') {
            $extracted = $this->merge($previous, $extracted, $intent);
        }

        if (($extracted['required'] === [] && ($extracted['preferred'] === [])) && in_array($intent, ['search', 'refine'], true)) {
            $extracted['warnings'][] = 'Não identifiquei filtros estruturados suficientes nesta frase.';
        }

        return $extracted;
    }

    public function merge(array $previous, array $incoming, string $intent): array
    {
        $merged = [
            'intent' => $intent,
            'required' => $previous['required'] ?? [],
            'preferred' => $previous['preferred'] ?? [],
            'unknowns' => $incoming['unknowns'] ?? [],
            'warnings' => $incoming['warnings'] ?? [],
        ];

        foreach (['required', 'preferred'] as $bucket) {
            foreach (($incoming[$bucket] ?? []) as $key => $value) {
                if ($value === null || $value === [] || $value === '') {
                    continue;
                }
                $merged[$bucket][$key] = $value;
            }
        }

        if (isset($incoming['required']['max_price']) && isset($merged['preferred']['max_price'])) {
            unset($merged['preferred']['max_price']);
        }

        return $merged;
    }

    public function toSearchFilters(array $criteria): array
    {
        $required = $criteria['required'] ?? [];
        $preferred = $criteria['preferred'] ?? [];
        $neighborhoods = $preferred['neighborhoods'] ?? [];
        $hardNeighborhood = $required['neighborhood'] ?? ($required['neighborhoods'][0] ?? null);

        return array_filter([
            'city' => $required['city'] ?? $preferred['city'] ?? null,
            'state' => $required['state'] ?? $preferred['state'] ?? null,
            'neighborhood' => $hardNeighborhood,
            'property_type' => $required['property_type'] ?? $preferred['property_type'] ?? 'apartment',
            'max_price' => $required['max_price'] ?? null,
            'min_bedrooms' => $required['min_bedrooms'] ?? null,
            'min_bathrooms' => $required['min_bathrooms'] ?? null,
            'min_parking' => $required['min_parking'] ?? null,
            'min_area_m2' => $required['min_area_m2'] ?? null,
            'preferred_neighborhoods' => $neighborhoods,
            'preferred_features' => $preferred['features'] ?? [],
        ], fn ($value) => $value !== null && $value !== []);
    }

    private function intent(string $normalized, array $previous): string
    {
        if (Str::contains($normalized, ['3d', 'visita virtual', 'mostre a cozinha', 'planta baixa', 'decorar'])) {
            return 'visualize';
        }
        if (Str::contains($normalized, ['qual desses', 'mais interessante', 'recomenda', 'melhor opcao', 'melhor opção'])) {
            return 'recommend';
        }
        if (Str::contains($normalized, ['compare', 'diferenca', 'diferença', 'vantagens'])) {
            return 'compare';
        }
        if ($previous !== [] && Str::contains($normalized, ['agora', 'parecid', 'mas com', 'mas sem', 'tambem', 'também', 'em vez', 'mais barato', 'mais caro'])) {
            return 'refine';
        }

        return 'search';
    }

    private function extractType(string $normalized, array &$extracted): void
    {
        if (Str::contains($normalized, ['apartamento', 'apto', 'cobertura'])) {
            $extracted['required']['property_type'] = 'apartment';
        } elseif (Str::contains($normalized, ['casa', 'sobrado'])) {
            $extracted['required']['property_type'] = 'house';
        } elseif (Str::contains($normalized, ['sala comercial', 'loja', 'galpao', 'galpão', 'comercial'])) {
            $extracted['required']['property_type'] = 'commercial';
        }
    }

    private function extractLocation(string $normalized, array &$extracted): void
    {
        foreach (self::CITIES as $needle => $place) {
            if (Str::contains($normalized, $needle)) {
                $extracted['required']['city'] = $place['city'];
                $extracted['required']['state'] = $place['state'];
                break;
            }
        }

        if (preg_match('/\b(ac|al|ap|am|ba|ce|df|es|go|ma|mt|ms|mg|pa|pb|pr|pe|pi|rj|rn|rs|ro|rr|sc|sp|se|to)\b/', $normalized, $match)) {
            $extracted['required']['state'] = strtoupper($match[1]);
        }

        $preferred = Str::contains($normalized, ['de preferencia', 'de preferência', 'preferencialmente', 'se possivel', 'se possível']);
        $found = [];
        foreach (self::NEIGHBORHOODS as $neighborhood) {
            if (Str::contains($normalized, $neighborhood)) {
                $found[] = Str::title($neighborhood);
            }
        }
        if ($found === []) {
            return;
        }
        if ($preferred || count($found) > 1) {
            $extracted['preferred']['neighborhoods'] = array_values(array_unique($found));
        } else {
            $extracted['required']['neighborhood'] = $found[0];
        }
    }

    private function extractPrice(string $normalized, array &$extracted): void
    {
        if (preg_match('/(?:ate|até|no maximo|no máximo|maximo|máximo)\s*(?:r\$\s*)?(\d[\d\.,]*)\s*(milhao|milhão|milhoes|milhões|mil)?/u', $normalized, $match)) {
            $extracted['required']['max_price'] = $this->money($match[1], $match[2] ?? null);

            return;
        }
        if (preg_match('/(?:r\$\s*)?(\d[\d\.,]*)\s*(milhao|milhão|milhoes|milhões|mil)/u', $normalized, $match)) {
            $bucket = Str::contains($normalized, ['por volta', 'em torno', 'cerca']) ? 'preferred' : 'required';
            $extracted[$bucket]['max_price'] = $this->money($match[1], $match[2]);
        }
    }

    private function extractCounts(string $normalized, array &$extracted): void
    {
        $map = ['um' => 1, 'uma' => 1, 'dois' => 2, 'duas' => 2, 'tres' => 3, 'três' => 3, 'quatro' => 4, 'cinco' => 5];
        foreach (['quartos?' => 'min_bedrooms', 'dormitorios?' => 'min_bedrooms', 'dormitórios?' => 'min_bedrooms', 'suites?' => 'min_bedrooms', 'suítes?' => 'min_bedrooms', 'banheiros?' => 'min_bathrooms', 'vagas?' => 'min_parking'] as $word => $field) {
            if (preg_match('/(?:pelo menos|no minimo|no mínimo|minimo|mínimo)?\s*(\d+|um|uma|dois|duas|tres|três|quatro|cinco)\s+'.$word.'\b/u', $normalized, $match)) {
                $value = $map[$match[1]] ?? (int) $match[1];
                if ($value > 0) {
                    $extracted['required'][$field] = $value;
                }
            }
        }
    }

    private function extractArea(string $normalized, array &$extracted): void
    {
        if (preg_match('/(?:pelo menos|no minimo|no mínimo|acima de)?\s*(\d[\d\.,]*)\s*(?:m2|m²|metros)/u', $normalized, $match)) {
            $extracted['required']['min_area_m2'] = (float) str_replace(['.', ','], ['', '.'], $match[1]);
        }
    }

    private function extractFeatures(string $normalized, array &$extracted): void
    {
        $features = [];
        foreach (self::FEATURES as $needle => $code) {
            if (Str::contains($normalized, $needle)) {
                $features[] = $code;
            }
        }
        if ($features !== []) {
            $extracted['preferred']['features'] = array_values(array_unique($features));
        }
        if (Str::contains($normalized, ['condominio mais barato', 'condomínio mais barato', 'condominio baixo'])) {
            $extracted['preferred']['cheaper_condo'] = true;
            $extracted['unknowns'][] = 'O valor de condomínio não vem na coleta atual dos portais; o critério foi registrado como preferência e não como filtro obrigatório.';
        }
    }

    private function extractLimitations(string $normalized, array &$extracted): void
    {
        if (Str::contains($normalized, ['invente', 'finja', 'crie imoveis', 'crie imóveis'])) {
            $extracted['warnings'][] = 'Somente imóveis de fontes reais ou da sua conta entram no resultado.';
        }
    }

    private function money(string $amount, ?string $unit): float
    {
        $amount = trim($amount);
        $value = preg_match('/^\d+,\d+$/', $amount)
            ? (float) str_replace(',', '.', $amount)
            : (float) str_replace('.', '', str_replace(',', '.', $amount));
        $unit = $this->normalize((string) $unit);
        if (Str::contains($unit, 'milhao')) {
            return $value * 1_000_000;
        }
        if ($unit === 'mil') {
            return $value * 1000;
        }

        return $value;
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->lower()->ascii()->replaceMatches('/[^a-z0-9\$\.,]+/u', ' ')->squish()->toString();
    }
}
