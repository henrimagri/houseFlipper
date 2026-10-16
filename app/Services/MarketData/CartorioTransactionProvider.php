<?php

namespace App\Services\MarketData;

use App\Contracts\PropertyTransactionProviderInterface;
use App\Services\MarketData\Concerns\NormalizesTransactions;
use Illuminate\Support\Facades\Http;

class CartorioTransactionProvider implements PropertyTransactionProviderInterface
{
    use NormalizesTransactions;

    public function key(): string
    {
        return 'cartorios';
    }

    public function label(): string
    {
        return 'Cartórios';
    }

    public function search(array $subject): array
    {
        $endpoint = config('services.cartorios.endpoint');

        if (! $endpoint) {
            return $this->unconfigured('Defina CARTORIOS_API_URL e, se necessário, CARTORIOS_API_TOKEN.');
        }

        try {
            $request = Http::acceptJson()->timeout(12);
            if ($token = config('services.cartorios.token')) {
                $request = $request->withToken($token);
            }

            $response = $request->get($endpoint, [
                'city' => $subject['city'] ?? null,
                'state' => $subject['state'] ?? null,
                'neighborhood' => $subject['neighborhood'] ?? null,
                'address' => $subject['address'] ?? null,
                'area_m2' => $subject['area_m2'] ?? null,
                'bedrooms' => $subject['bedrooms'] ?? null,
            ]);
        } catch (\Throwable $exception) {
            return $this->error($exception->getMessage());
        }

        if (! $response->successful()) {
            return $this->error('HTTP '.$response->status());
        }

        $rows = $response->json('data', $response->json('transactions', $response->json()));
        $transactions = collect(is_array($rows) ? $rows : [])
            ->map(fn (array $row) => $this->normalizeTransaction($row, $this->label()))
            ->filter()
            ->values()
            ->all();

        return $this->result('connected', 'Provider de cartório consultado.', $transactions);
    }

    private function unconfigured(string $message): array
    {
        return $this->result('unconfigured', $message, []);
    }

    private function error(string $message): array
    {
        return $this->result('error', $message, []);
    }

    private function result(string $status, string $message, array $transactions): array
    {
        return ['key' => $this->key(), 'label' => $this->label(), 'status' => $status, 'message' => $message, 'transactions' => $transactions];
    }
}
