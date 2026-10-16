<?php

namespace App\Services\RealEstateData;

use App\Contracts\ItbiDatasetProviderInterface;
use App\Models\ItbiImportBatch;

class ItbiDatasetDiscoveryService
{
    public function __construct(private readonly ItbiDatasetProviderInterface $provider) {}

    public function discover(): array
    {
        $datasets = $this->provider->discover();
        $created = 0;
        $existing = 0;

        foreach ($datasets as $dataset) {
            $batch = ItbiImportBatch::query()
                ->where('provider', $this->provider->providerKey())
                ->where('source_url', $dataset['url'])
                ->first();

            if ($batch) {
                $existing++;
                $batch->update([
                    'source_filename' => $dataset['filename'],
                    'reference_period' => $dataset['reference_period'],
                    'metadata_json' => array_merge($batch->metadata_json ?? [], $dataset['metadata'] ?? []),
                ]);

                continue;
            }

            ItbiImportBatch::create([
                'provider' => $this->provider->providerKey(),
                'source_url' => $dataset['url'],
                'source_filename' => $dataset['filename'],
                'reference_period' => $dataset['reference_period'],
                'status' => 'discovered',
                'discovered_at' => now(),
                'metadata_json' => $dataset['metadata'] ?? [],
            ]);
            $created++;
        }

        return ['found' => count($datasets), 'created' => $created, 'existing' => $existing];
    }
}
