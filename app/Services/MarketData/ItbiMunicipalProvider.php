<?php

namespace App\Services\MarketData;

use App\Contracts\PropertyTransactionProviderInterface;
use App\Services\MarketData\Concerns\NormalizesTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ItbiMunicipalProvider implements PropertyTransactionProviderInterface
{
    use NormalizesTransactions;

    public function key(): string
    {
        return 'itbi';
    }

    public function label(): string
    {
        return 'ITBI municipal';
    }

    public function search(array $subject): array
    {
        $endpoint = $this->endpointFor($subject);

        if (! $endpoint) {
            if ($this->isSaoPaulo($subject)) {
                return $this->result(
                    'no_dataset',
                    'Endpoint ITBI próprio não configurado. A busca pública usa o catálogo CKAN da Prefeitura e a planilha ITBI importada.',
                    []
                );
            } else {
                return $this->result(
                    'no_dataset',
                    'Sem endpoint ITBI próprio. A análise consulta catálogos CKAN municipais/nacionais de dados abertos quando existirem para esta cidade.',
                    []
                );
            }
        }

        try {
            $endpoint = $this->assertPublicHttpsEndpoint($endpoint);
            $request = Http::timeout(18)
                ->accept('*/*')
                ->withHeaders(['Accept-Encoding' => 'identity'])
                ->withOptions($this->guardedHttpOptions())
                ->withUserAgent('InvestFlip/1.0 (+market-data)');
            if ($token = config('services.itbi.token')) {
                $request = $request->withToken($token);
            }

            $response = $request->get($endpoint, [
                'city' => $subject['city'] ?? null,
                'state' => $subject['state'] ?? null,
                'neighborhood' => $subject['neighborhood'] ?? null,
                'address' => $subject['address'] ?? null,
                'limit' => 50,
            ]);
            $this->assertGuardedResponseSize($response);
        } catch (\Throwable $exception) {
            return $this->result('error', 'Serviço externo indisponível.', []);
        }

        if (! $response->successful()) {
            return $this->result('error', 'HTTP '.$response->status(), []);
        }

        $rows = $this->rowsFromResponse($response->body(), $response->header('content-type', ''));
        $filtered = $this->filterRows($rows, $subject);
        $transactions = collect($filtered)
            ->map(fn (array $row) => $this->normalizeTransaction($row, $this->label()))
            ->filter()
            ->take(20)
            ->values()
            ->all();

        return $this->result('connected', count($transactions).' transações normalizadas via ITBI.', $transactions);
    }

    private function endpointFor(array $subject): ?string
    {
        if ($this->isSaoPaulo($subject)) {
            return config('services.itbi.sp_endpoint') ?: config('services.itbi.endpoint');
        }

        return config('services.itbi.endpoint');
    }

    private function isSaoPaulo(array $subject): bool
    {
        $city = Str::of((string) ($subject['city'] ?? ''))->lower()->ascii()->toString();
        $state = strtoupper((string) ($subject['state'] ?? ''));

        return $state === 'SP' && Str::contains($city, 'sao paulo');
    }

    private function discoverSaoPauloDataset(array $subject): ?string
    {
        $catalog = config('services.itbi.sp_catalog');
        if (! $catalog) {
            return null;
        }

        $terms = [
            'itbi',
            'transações imobiliárias',
            'transacao imobiliaria',
            'valor venal imóveis',
            'imóveis',
        ];

        foreach ($terms as $term) {
            try {
                $catalog = $this->assertPublicHttpsEndpoint($catalog);
                $response = Http::timeout(12)
                    ->acceptJson()
                    ->withHeaders(['Accept-Encoding' => 'identity'])
                    ->withOptions($this->guardedHttpOptions())
                    ->withUserAgent('Mozilla/5.0 InvestFlip/1.0')
                    ->get($catalog, ['q' => $term, 'rows' => 20]);
                $this->assertGuardedResponseSize($response);
            } catch (\Throwable) {
                continue;
            }

            if (! $response->successful()) {
                continue;
            }

            $packages = $response->json('result.results', []);
            if (! is_array($packages)) {
                continue;
            }

            foreach ($packages as $package) {
                $packageText = $this->normalizedText([
                    data_get($package, 'name'),
                    data_get($package, 'title'),
                    data_get($package, 'notes'),
                ]);

                foreach ((array) data_get($package, 'resources', []) as $resource) {
                    $resourceText = $this->normalizedText([
                        $packageText,
                        data_get($resource, 'name'),
                        data_get($resource, 'description'),
                        data_get($resource, 'format'),
                        data_get($resource, 'url'),
                    ]);

                    $format = Str::of((string) data_get($resource, 'format'))->lower()->toString();
                    $url = data_get($resource, 'url');
                    $isReadable = $url && (
                        Str::contains($format, ['csv', 'json'])
                        || Str::of((string) $url)->lower()->endsWith(['.csv', '.json'])
                    );
                    $looksTransactional = Str::contains($resourceText, ['itbi', 'transa', 'venda', 'valor venal', 'imobili']);

                    if ($isReadable && $looksTransactional) {
                        return $url;
                    }
                }
            }
        }

        return null;
    }

    private function normalizedText(array $parts): string
    {
        return Str::of(implode(' ', array_filter(array_map('strval', $parts))))
            ->lower()
            ->ascii()
            ->toString();
    }

    private function rowsFromResponse(string $body, string $contentType): array
    {
        if (str_contains($contentType, 'json') || str_starts_with(trim($body), '{') || str_starts_with(trim($body), '[')) {
            $decoded = json_decode($body, true);
            $rows = data_get($decoded, 'data', data_get($decoded, 'records', data_get($decoded, 'transactions', $decoded)));

            return is_array($rows) ? $rows : [];
        }

        return $this->csvRows($body);
    }

    private function csvRows(string $body): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($body));
        if (! $lines || count($lines) < 2) {
            return [];
        }

        $delimiter = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
        $headers = array_map(fn ($header) => Str::of($header)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString(), str_getcsv(array_shift($lines), $delimiter));

        return collect($lines)
            ->map(function (string $line) use ($headers, $delimiter) {
                $values = str_getcsv($line, $delimiter);

                if (count($headers) !== count($values)) {
                    return [];
                }

                return array_combine($headers, $values) ?: [];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function filterRows(array $rows, array $subject): array
    {
        $neighborhood = Str::lower((string) ($subject['neighborhood'] ?? ''));
        $city = Str::lower((string) ($subject['city'] ?? ''));

        return collect($rows)
            ->filter(fn ($row) => is_array($row) && collect($row)->every(fn ($value) => is_scalar($value) || $value === null))
            ->filter(function (array $row) use ($neighborhood, $city) {
                $haystack = Str::lower(implode(' ', array_map('strval', $row)));
                $matchesNeighborhood = $neighborhood === '' || Str::contains($haystack, $neighborhood);
                $matchesCity = $city === '' || Str::contains($haystack, $city);

                return $matchesNeighborhood && $matchesCity;
            })
            ->values()
            ->all();
    }

    private function result(string $status, string $message, array $transactions): array
    {
        return ['key' => $this->key(), 'label' => $this->label(), 'status' => $status, 'message' => $message, 'transactions' => $transactions];
    }
}
