<?php

namespace App\Services\MarketData;

use App\Contracts\PropertyTransactionProviderInterface;
use App\Services\MarketData\Concerns\NormalizesTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OpenGovernmentTransactionProvider implements PropertyTransactionProviderInterface
{
    use NormalizesTransactions;

    public function key(): string
    {
        return 'opendata_itbi';
    }

    public function label(): string
    {
        return 'ITBI dados abertos';
    }

    public function search(array $subject): array
    {
        $city = trim((string) ($subject['city'] ?? ''));
        $neighborhood = trim((string) ($subject['neighborhood'] ?? ''));
        $cacheKey = 'opendata-itbi:'.md5(strtolower($city.'|'.$neighborhood.'|'.($subject['state'] ?? '')));

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($subject, $city, $neighborhood) {
            $catalogs = $this->catalogsFor($subject);
            if ($catalogs === []) {
                return $this->result('no_dataset', 'Nenhum catálogo CKAN público mapeado para esta cidade.', []);
            }

            $transactions = [];
            $consulted = 0;
            foreach ($catalogs as $catalog) {
                $consulted++;
                $found = $this->searchCatalog($catalog, $subject);
                foreach ($found as $transaction) {
                    $transactions[] = $transaction;
                }
                if (count($transactions) >= 20) {
                    break;
                }
            }

            $transactions = collect($transactions)
                ->unique(fn (array $row) => implode('|', [$row['source_reference'] ?? '', $row['address'] ?? '', $row['price'] ?? '', $row['transaction_date'] ?? '']))
                ->take(20)
                ->values()
                ->all();

            if ($transactions !== []) {
                $where = implode(' / ', array_filter([$neighborhood, $city]));

                return $this->result('connected', count($transactions).' venda(s) públicas encontradas em dados abertos'.($where !== '' ? ' para '.$where : '').'.', $transactions);
            }

            return $this->result(
                'no_dataset',
                'Catálogos CKAN públicos consultados ('.$consulted.'), mas o recurso não devolveu transações filtráveis para este bairro/cidade nesta coleta.',
                []
            );
        });
    }

    private function catalogsFor(array $subject): array
    {
        $city = Str::of((string) ($subject['city'] ?? ''))->lower()->ascii()->toString();
        $state = strtoupper((string) ($subject['state'] ?? ''));
        $matched = [];

        foreach (config('services.open_data.ckan_catalogs', []) as $catalog) {
            if (! is_array($catalog)) {
                continue;
            }
            $states = array_map('strtoupper', (array) ($catalog['states'] ?? []));
            $cities = array_map(fn ($item) => Str::of((string) $item)->lower()->ascii()->toString(), (array) ($catalog['cities'] ?? []));
            $isNational = $states === [] && $cities === [];
            $matchesState = $states === [] || in_array($state, $states, true);
            $matchesCity = $cities === [] || collect($cities)->contains(fn (string $candidate) => $candidate !== '' && str_contains($city, $candidate));
            if ($isNational || ($matchesState && $matchesCity)) {
                $matched[] = $catalog;
            }
        }

        return $matched;
    }

    private function searchCatalog(array $catalog, array $subject): array
    {
        $packageSearch = $catalog['package_search'] ?? null;
        $datastoreSearch = $catalog['datastore_search'] ?? null;
        if (! is_string($packageSearch) || ! is_string($datastoreSearch)) {
            return [];
        }

        $packages = $this->packages($packageSearch);
        foreach ($packages as $package) {
            foreach ((array) data_get($package, 'resources', []) as $resource) {
                if (! is_array($resource) || empty($resource['datastore_active']) || empty($resource['id'])) {
                    continue;
                }
                $rows = $this->datastoreRows($datastoreSearch, (string) $resource['id'], $subject);
                $transactions = collect($rows)
                    ->map(fn (array $row) => $this->normalizeTransaction($this->flatten($row), $this->label()))
                    ->filter()
                    ->values()
                    ->all();
                if ($transactions !== []) {
                    return $transactions;
                }
            }
        }

        return [];
    }

    private function packages(string $endpoint): array
    {
        try {
            $endpoint = $this->assertPublicHttpsEndpoint($endpoint);
            $response = Http::timeout(12)
                ->acceptJson()
                ->withHeaders(['Accept-Encoding' => 'identity'])
                ->withOptions($this->guardedHttpOptions())
                ->withUserAgent('InvestFlip/1.0 (+open-data)')
                ->get($endpoint, ['q' => 'itbi transacao imobiliaria negocio imoveis', 'rows' => 8]);
            $this->assertGuardedResponseSize($response);
        } catch (\Throwable) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $packages = $response->json('result.results', []);

        return is_array($packages) ? $packages : [];
    }

    private function datastoreRows(string $endpoint, string $resourceId, array $subject): array
    {
        $query = trim(implode(' ', array_filter([
            $subject['neighborhood'] ?? null,
            $subject['city'] ?? null,
        ])));

        try {
            $endpoint = $this->assertPublicHttpsEndpoint($endpoint);
            $response = Http::timeout(12)
                ->acceptJson()
                ->withHeaders(['Accept-Encoding' => 'identity'])
                ->withOptions($this->guardedHttpOptions())
                ->withUserAgent('InvestFlip/1.0 (+open-data)')
                ->get($endpoint, array_filter([
                    'resource_id' => $resourceId,
                    'q' => $query !== '' ? $query : null,
                    'limit' => 50,
                ]));
            $this->assertGuardedResponseSize($response);
        } catch (\Throwable) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $records = $response->json('result.records', []);
        if (! is_array($records)) {
            return [];
        }

        return collect($records)
            ->filter(fn ($row) => is_array($row))
            ->map(fn (array $row) => $this->flatten($row))
            ->filter(fn (array $row) => $this->matchesLocation($row, $subject))
            ->values()
            ->all();
    }

    private function flatten(array $row): array
    {
        $flat = [];
        foreach ($row as $key => $value) {
            $normalized = Str::of((string) $key)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
            $flat[$normalized] = is_scalar($value) || $value === null ? $value : null;
        }

        return $flat;
    }

    private function matchesLocation(array $row, array $subject): bool
    {
        $haystack = Str::of(implode(' ', array_map('strval', $row)))->lower()->ascii()->toString();
        $neighborhood = Str::of((string) ($subject['neighborhood'] ?? ''))->lower()->ascii()->toString();
        $city = Str::of((string) ($subject['city'] ?? ''))->lower()->ascii()->toString();

        $matchesNeighborhood = $neighborhood === '' || str_contains($haystack, $neighborhood);
        $matchesCity = $city === '' || str_contains($haystack, $city);

        return $matchesNeighborhood && $matchesCity;
    }

    private function result(string $status, string $message, array $transactions): array
    {
        return ['key' => $this->key(), 'label' => $this->label(), 'status' => $status, 'message' => $message, 'transactions' => $transactions];
    }
}
