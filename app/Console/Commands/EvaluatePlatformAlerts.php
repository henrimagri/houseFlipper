<?php

namespace App\Console\Commands;

use App\Services\Platform\AlertEvaluator;
use Illuminate\Console\Command;

class EvaluatePlatformAlerts extends Command
{
    protected $signature = 'platform:evaluate-alerts';

    protected $description = 'Evaluate enabled count rules using collected metadata; never blocks automatically';

    public function handle(): int
    {
        $this->line('New alerts: '.app(AlertEvaluator::class)->run());

        return self::SUCCESS;
    }
}
