<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ItbiSyncCommand extends Command
{
    protected $signature = 'itbi:sync {--limit=}';

    protected $description = 'Orquestra descoberta e importação de arquivos públicos de ITBI.';

    public function handle(): int
    {
        $this->call('itbi:discover');

        $options = [];
        if ($this->option('limit')) {
            $options['--limit'] = $this->option('limit');
        }

        $this->call('itbi:import', $options);

        $this->warn('Fases seguintes ainda serão implementadas: itbi:geocode, itbi:rebuild-statistics e avm:recalculate-opportunities.');

        return self::SUCCESS;
    }
}
