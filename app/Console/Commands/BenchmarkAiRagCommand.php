<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Ai\AiKnowledgeService;
use Illuminate\Console\Command;

class BenchmarkAiRagCommand extends Command
{
    protected $signature = 'ai:rag:benchmark {--dataset=resources/ai/evaluations/rag-semantic.json} {--user=}';

    protected $description = 'Compara recuperação lexical e híbrida usando embeddings reais';

    public function handle(AiKnowledgeService $knowledge): int
    {
        $user = User::query()->find($this->option('user')) ?? User::query()->whereNotNull('tenant_id')->firstOrFail();
        $cases = json_decode(file_get_contents(base_path($this->option('dataset'))), true, flags: JSON_THROW_ON_ERROR);
        $result = ['dataset' => $this->option('dataset'), 'generated_at' => now()->toIso8601String(), 'modes' => []];
        foreach (['lexical' => false, 'hybrid' => true] as $mode => $enabled) {
            config(['ai.vector.enabled' => $enabled]);
            $hits = 0;
            $reciprocalRank = 0.0;
            $started = hrtime(true);
            $rows = [];
            foreach ($cases as $case) {
                $items = $knowledge->retrieve($user, $case['product'], $case['query'], 4);
                $rank = collect($items)->search(fn (array $item) => $item['title'] === $case['expected_title']);
                if ($rank !== false) {
                    $hits++;
                    $reciprocalRank += 1 / ($rank + 1);
                }
                $rows[] = ['query' => $case['query'], 'expected' => $case['expected_title'], 'rank' => $rank === false ? null : $rank + 1, 'titles' => collect($items)->pluck('title')->all()];
            }
            $result['modes'][$mode] = [
                'recall_at_4' => round($hits / max(1, count($cases)), 4),
                'mrr' => round($reciprocalRank / max(1, count($cases)), 4),
                'latency_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
                'cases' => $rows,
            ];
        }
        file_put_contents(base_path('docs/ai/rag-benchmark.json'), json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
