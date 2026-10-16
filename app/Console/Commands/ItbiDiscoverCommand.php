<?php

namespace App\Console\Commands;

use App\Services\RealEstateData\ItbiDatasetDiscoveryService;
use Illuminate\Console\Command;

class ItbiDiscoverCommand extends Command
{
    protected $signature = 'itbi:discover';

    protected $description = 'Descobre arquivos públicos de ITBI da Prefeitura de São Paulo.';

    public function handle(ItbiDatasetDiscoveryService $discovery): int
    {
        $summary = $discovery->discover();

        $this->info("Arquivos encontrados: {$summary['found']}");
        $this->info("Novos lotes: {$summary['created']}");
        $this->info("Já conhecidos: {$summary['existing']}");

        return self::SUCCESS;
    }
}
