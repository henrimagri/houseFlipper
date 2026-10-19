<?php

namespace App\Services\Ai;

use Illuminate\Support\Str;

class PropertyMatchRanker
{
    public function rank(array $listings, array $criteria): array
    {
        $required = $criteria['required'] ?? [];
        $preferred = $criteria['preferred'] ?? [];

        return collect($listings)
            ->map(function (array $item) use ($required, $preferred): array {
                $hard = $this->hardFailures($item, $required);
                $reasons = [];
                $score = (int) ($item['score'] ?? 0);
                if ($hard === []) {
                    $score += 8;
                    $reasons[] = 'Atende os critérios obrigatórios da conversa.';
                }
                $neighborhoods = $preferred['neighborhoods'] ?? [];
                if ($neighborhoods !== [] && $this->textHasAny($item, $neighborhoods)) {
                    $score += 12;
                    $reasons[] = 'Está em um dos bairros preferidos ('.implode(', ', $neighborhoods).').';
                }
                foreach ($preferred['features'] ?? [] as $feature) {
                    if ($this->hasFeature($item, $feature)) {
                        $score += 4;
                        $reasons[] = 'Há indício textual da característica “'.$feature.'”.';
                    }
                }
                if (($required['max_price'] ?? null) && (float) ($item['asking_price'] ?? 0) > 0) {
                    $reasons[] = 'O preço pedido permanece dentro do teto informado.';
                }
                $item['match_score'] = max(0, min(100, $score));
                $item['match_reasons'] = $reasons;
                $item['hard_failures'] = $hard;
                $item['eligible'] = $hard === [];

                return $item;
            })
            ->sortBy([
                ['eligible', 'desc'],
                ['match_score', 'desc'],
                ['score', 'desc'],
            ])
            ->values()
            ->all();
    }

    public function recommend(array $ranked): ?array
    {
        $eligible = collect($ranked)->firstWhere('eligible', true);
        if (! is_array($eligible)) {
            return null;
        }

        $reasons = $eligible['match_reasons'] ?? [];
        $text = $reasons !== []
            ? implode(' ', $reasons)
            : 'A recomendação usa o ranking atual de compatibilidade e o Opportunity Score já existente.';

        return [
            'listing_id' => $eligible['id'] ?? null,
            'origin' => $eligible['origin'] ?? 'market',
            'title' => $eligible['title'] ?? 'Imóvel',
            'explanation' => 'Não é “o melhor imóvel absoluto”. '.$text,
            'caveats' => array_values(array_filter([
                ($eligible['origin'] ?? '') === 'market' ? 'Anúncio de portal: disponibilidade e fotos dependem da fonte original.' : null,
                isset($eligible['market_estimate_method']) && $eligible['market_estimate_method'] === 'fallback_heuristic'
                    ? 'A estimativa de mercado deste item ainda é heurística, não uma transação confirmada.'
                    : null,
            ])),
        ];
    }

    public function compare(array $ranked, int $limit = 3): array
    {
        return collect($ranked)->where('eligible', true)->take($limit)->map(function (array $item): array {
            return [
                'id' => $item['id'] ?? null,
                'title' => $item['title'] ?? '',
                'origin' => $item['origin'] ?? 'market',
                'asking_price' => $item['asking_price'] ?? null,
                'neighborhood' => $item['neighborhood'] ?? null,
                'bedrooms' => $item['bedrooms'] ?? null,
                'parking_spaces' => $item['parking_spaces'] ?? null,
                'advantages' => $item['match_reasons'] ?? [],
                'disadvantages' => $item['hard_failures'] ?? [],
            ];
        })->values()->all();
    }

    public function missingExplanation(array $criteria, array $ranked): array
    {
        $required = $criteria['required'] ?? [];
        $blockers = [];
        foreach (collect($ranked)->pluck('hard_failures')->flatten(1)->countBy() as $reason => $count) {
            $blockers[] = ['constraint' => $reason, 'listings_rejected' => $count];
        }
        $suggestions = [];
        if (isset($required['max_price'])) {
            $suggestions[] = 'Liberar o teto de preço ou tratá-lo como preferência.';
        }
        if (isset($required['min_parking'])) {
            $suggestions[] = 'Aceitar uma vaga a menos, se a localização for inegociável.';
        }
        if (($criteria['preferred']['neighborhoods'] ?? []) !== []) {
            $suggestions[] = 'Manter os bairros como preferência e ampliar a busca para a cidade.';
        }

        return [
            'message' => 'Nenhum imóvel das fontes consultadas atendeu todos os critérios obrigatórios.',
            'blockers' => $blockers,
            'suggestions' => $suggestions,
        ];
    }

    private function hardFailures(array $item, array $required): array
    {
        $failures = [];
        $price = (float) ($item['asking_price'] ?? 0);
        if (isset($required['max_price']) && ($price <= 0 || $price > (float) $required['max_price'])) {
            $failures[] = 'Preço acima do teto obrigatório';
        }
        if (isset($required['min_bedrooms']) && (int) ($item['bedrooms'] ?? 0) < (int) $required['min_bedrooms']) {
            $failures[] = 'Quartos abaixo do mínimo obrigatório';
        }
        if (isset($required['min_bathrooms']) && (int) ($item['bathrooms'] ?? 0) < (int) $required['min_bathrooms']) {
            $failures[] = 'Banheiros abaixo do mínimo obrigatório';
        }
        if (isset($required['min_parking']) && (int) ($item['parking_spaces'] ?? 0) < (int) $required['min_parking']) {
            $failures[] = 'Vagas abaixo do mínimo obrigatório';
        }
        if (isset($required['min_area_m2']) && (float) ($item['area_m2'] ?? 0) < (float) $required['min_area_m2']) {
            $failures[] = 'Área abaixo do mínimo obrigatório';
        }
        if (isset($required['neighborhood']) && ! $this->textHasAny($item, [$required['neighborhood']])) {
            $failures[] = 'Fora do bairro obrigatório';
        }
        if (isset($required['city']) && ! $this->textHasAny($item, [$required['city']])) {
            $failures[] = 'Fora da cidade obrigatória';
        }

        return $failures;
    }

    private function hasFeature(array $item, string $feature): bool
    {
        $needles = match ($feature) {
            'balcony' => ['varanda', 'sacada'],
            'pool' => ['piscina'],
            'elevator' => ['elevador'],
            'renovated' => ['reformado', 'reforma pronta'],
            'furnished' => ['mobiliado'],
            'sea_view' => ['vista mar', 'vista para o mar'],
            'gourmet' => ['gourmet'],
            default => [$feature],
        };

        return $this->textHasAny($item, $needles);
    }

    private function textHasAny(array $item, array $needles): bool
    {
        $text = $this->normalize(implode(' ', [
            $item['title'] ?? '',
            $item['address'] ?? '',
            $item['neighborhood'] ?? '',
            $item['city'] ?? '',
            $item['notes'] ?? '',
            $item['description'] ?? '',
        ]));

        foreach ($needles as $needle) {
            if ($needle !== '' && Str::contains($text, $this->normalize((string) $needle))) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();
    }
}
