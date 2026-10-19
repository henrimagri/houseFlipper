<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ItbiSyncCommand extends Command
{
    protected $signature = 'itbi:sync {--limit=}';

    protected $description = 'Orquestra descoberta e importação de arquivos públicos de ITBI.';

    public function handle(): int
    {
        if ($this->call('itbi:discover') !== self::SUCCESS) {
            return self::FAILURE;
        }

        $options = [];
        if ($this->option('limit') !== null) {
            $options['--limit'] = $this->option('limit');
        }

        if ($this->call('itbi:import', $options) !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->warn('Fases seguintes ainda serão implementadas: itbi:geocode, itbi:rebuild-statistics e avm:recalculate-opportunities.');

        return self::SUCCESS;
    }
}
