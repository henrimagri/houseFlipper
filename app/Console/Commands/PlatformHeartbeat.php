<?php

namespace App\Console\Commands;

use App\Services\Platform\OperationalHeartbeats;
use Illuminate\Console\Command;
use InvalidArgumentException;

class PlatformHeartbeat extends Command
{
    protected $signature = 'platform:heartbeat {type : scheduler, queue, ai or 3d} {worker_id : Stable configured UUID} {--release=unknown : Application version or release ID}';

    protected $description = 'Record a real operational heartbeat from a trusted local process';

    public function handle(OperationalHeartbeats $heartbeats): int
    {
        try {
            $heartbeats->beat($this->argument('type'), $this->argument('worker_id'), $this->option('release'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::INVALID;
        }
        $this->info('Heartbeat recorded. This proves invocation only, not continuous daemon availability.');

        return self::SUCCESS;
    }
}
