<?php

namespace App\Services;

use App\Services\MarketData\CartorioTransactionProvider;
use App\Services\MarketData\ImportedItbiTransactionProvider;
use App\Services\MarketData\ItbiMunicipalProvider;
use App\Services\MarketData\OpenGovernmentTransactionProvider;
use App\Services\MarketData\PrefeituraTransactionProvider;

class PropertyMarketDataService
{
    public function __construct(
        private readonly ImportedItbiTransactionProvider $importedItbi,
        private readonly OpenGovernmentTransactionProvider $openGovernment,
        private readonly CartorioTransactionProvider $cartorios,
        private readonly ItbiMunicipalProvider $itbi,
        private readonly PrefeituraTransactionProvider $prefeitura,
        private readonly EconomicIndicatorService $economicIndicators,
        private readonly NeighborhoodContextService $neighborhoodContext,
    ) {}

    public function analyze(array $subject): array
    {
        $providers = [$this->importedItbi, $this->openGovernment, $this->cartorios, $this->itbi, $this->prefeitura];
        $results = collect($providers)->map(fn ($provider) => $provider->search($subject))->values();
        $transactions = $results
            ->flatMap(fn (array $result) => $result['transactions'] ?? [])
            ->unique(fn (array $transaction) => implode('|', [
                $transaction['source'] ?? '',
                $transaction['source_reference'] ?? '',
                $transaction['address'] ?? '',
                $transaction['transaction_date'] ?? '',
                $transaction['price'] ?? '',
            ]))
            ->sortByDesc('transaction_date')
            ->values();
        $indicators = $this->economicIndicators->current();

        return [
            'providers' => $results->map(fn (array $result) => [
                'key' => $result['key'],
                'label' => $result['label'],
                'status' => $result['status'],
                'message' => $result['message'],
                'count' => count($result['transactions'] ?? []),
            ])->all(),
            'transactions' => $transactions->all(),
            'economic_indicators' => $indicators,
            'location_context' => null,
            'summary' => [
                'confirmed_transactions_count' => $transactions->count(),
                'sources_connected' => $results->where('status', 'connected')->pluck('label')->values()->all(),
                'sources_unavailable' => $results->whereIn('status', ['unconfigured', 'no_dataset'])->pluck('label')->values()->all(),
            ],
        ];
    }
}
