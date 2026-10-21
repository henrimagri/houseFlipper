<?php

namespace App\Console\Commands;

use App\Services\Ai\AiWorkerClient;
use Illuminate\Console\Command;

class ProbeAiWorkerCommand extends Command
{
    protected $signature = 'ai:worker:probe';

    protected $description = 'Valida autenticação e capacidades do worker Python interno';

    public function handle(AiWorkerClient $worker): int
    {
        $this->line(json_encode([
            'health' => $worker->health(),
            'models' => $worker->models(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
