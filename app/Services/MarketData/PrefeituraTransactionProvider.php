<?php

namespace App\Services\MarketData;

use App\Contracts\PropertyTransactionProviderInterface;
use App\Services\MarketData\Concerns\NormalizesTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PrefeituraTransactionProvider implements PropertyTransactionProviderInterface
{
    use NormalizesTransactions;

    public function key(): string
    {
        return 'prefeitura';
    }

    public function label(): string
    {
        return 'Prefeitura';
    }

    public function search(array $subject): array
    {
        $endpoint = config('services.prefeituras.endpoint');

        if (! $endpoint) {
            if ($this->isSaoPaulo($subject)) {
                return $this->result(
                    'no_dataset',
                    'Sem endpoint municipal configurado. Use PREFEITURA_TRANSACOES_API_URL para complementar a planilha ITBI importada.',
                    []
                );
            }

            return $this->result('unconfigured', 'Defina PREFEITURA_TRANSACOES_API_URL para consultar a base municipal.', []);
        }

        try {
            $request = Http::acceptJson()
                ->timeout(12)
                ->withUserAgent('InvestFlip/1.0 (+market-data)');
            if ($token = config('services.prefeituras.token')) {
                $request = $request->withToken($token);
            }

            $response = $request->get($endpoint, [
                'city' => $subject['city'] ?? null,
                'state' => $subject['state'] ?? null,
                'neighborhood' => $subject['neighborhood'] ?? null,
                'address' => $subject['address'] ?? null,
                'limit' => 50,
            ]);
        } catch (\Throwable $exception) {
            return $this->result('error', $exception->getMessage(), []);
        }

        if (! $response->successful()) {
            return $this->result('error', 'HTTP '.$response->status(), []);
        }

        $rows = $response->json('data', $response->json('records', $response->json('transactions', [])));
        $transactions = collect(is_array($rows) ? $rows : [])
            ->map(fn (array $row) => $this->normalizeTransaction($row, $this->label()))
            ->filter()
            ->values()
            ->all();

        return $this->result('connected', count($transactions).' transações municipais normalizadas.', $transactions);
    }

    private function result(string $status, string $message, array $transactions): array
    {
        return ['key' => $this->key(), 'label' => $this->label(), 'status' => $status, 'message' => $message, 'transactions' => $transactions];
    }

    private function probeSaoPauloCatalog(array $subject): array
    {
        $catalog = config('services.prefeituras.sp_catalog');
        if (! $catalog) {
            return $this->result('unconfigured', 'Defina PREFEITURA_TRANSACOES_API_URL para consultar a base municipal.', []);
        }

        $terms = ['transações imobiliárias', 'itbi', 'valor venal imóveis', 'imóveis'];
        $matches = [];

        foreach ($terms as $term) {
            try {
                $response = Http::timeout(12)
                    ->acceptJson()
                    ->withUserAgent('Mozilla/5.0 InvestFlip/1.0')
                    ->get($catalog, ['q' => $term, 'rows' => 10]);
            } catch (\Throwable $exception) {
                return $this->result('error', $exception->getMessage(), []);
            }

            if (! $response->successful()) {
                continue;
            }

            $packages = $response->json('result.results', []);
            if (! is_array($packages)) {
                continue;
            }

            foreach ($packages as $package) {
                $text = Str::of(implode(' ', [
                    data_get($package, 'name'),
                    data_get($package, 'title'),
                    data_get($package, 'notes'),
                ]))->lower()->ascii()->toString();

                if (Str::contains($text, ['itbi', 'transa', 'valor venal', 'imobili'])) {
                    $matches[] = data_get($package, 'title') ?: data_get($package, 'name');
                }
            }
        }

        if ($matches) {
            return $this->result(
                'no_dataset',
                'Catálogo público de São Paulo consultado. Foram encontrados datasets relacionados, mas sem endpoint transacional normalizado configurado: '.implode(', ', array_slice(array_unique($matches), 0, 3)).'.',
                []
            );
        }

        return $this->result(
            'no_dataset',
            'Catálogo público de São Paulo consultado; nenhuma base municipal pública de vendas/ITBI foi localizada para integração automática.',
            []
        );
    }

    private function isSaoPaulo(array $subject): bool
    {
        $city = Str::of((string) ($subject['city'] ?? ''))->lower()->ascii()->toString();
        $state = strtoupper((string) ($subject['state'] ?? ''));

        return $state === 'SP' && Str::contains($city, 'sao paulo');
    }
}
