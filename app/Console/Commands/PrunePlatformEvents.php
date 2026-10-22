<?php

namespace App\Console\Commands;

use App\Models\PlatformEvent;
use Illuminate\Console\Command;

class PrunePlatformEvents extends Command
{
    protected $signature = 'platform:prune {--execute : Apply configured retention; otherwise dry-run}';

    protected $description = 'Prune telemetry in bounded batches; audit retention cannot be shorter than 730 days';

    public function handle(): int
    {
        foreach (config('observability.retention') as $category => $days) {
            $days = max($category === 'audit' ? 730 : 1, (int) $days);
            $query = PlatformEvent::where('category', $category)->where('created_at', '<', now()->subDays($days));
            $total = 0;
            if ($this->option('execute')) {
                do {
                    $ids = (clone $query)->limit(1000)->pluck('id');
                    $n = PlatformEvent::whereIn('id', $ids)->delete();
                    $total += $n;
                } while ($n === 1000);
            } else {
                $total = $query->count();
            }
            $this->line($category.': '.$total.($this->option('execute') ? ' removed' : ' eligible (dry-run)'));
        }

        return self::SUCCESS;
    }
}
