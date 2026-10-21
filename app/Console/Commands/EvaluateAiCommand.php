<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Ai\AiAgentCatalog;
use App\Services\Ai\PropertyQueryInterpreter;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use RuntimeException;

class EvaluateAiCommand extends Command
{
    protected $signature = 'ai:evaluate {--dataset=resources/ai/evaluations/core.json} {--output=docs/ai/evaluation-results.json}';

    protected $description = 'Executa avaliações determinísticas de interpretação e roteamento da IA';

    public function handle(PropertyQueryInterpreter $interpreter, AiAgentCatalog $agents): int
    {
        $dataset = json_decode(file_get_contents(base_path($this->option('dataset'))), true, flags: JSON_THROW_ON_ERROR);
        $cases = Arr::isList($dataset) ? $dataset : ($dataset['cases'] ?? []);
        $suite = Arr::isList($dataset) ? pathinfo($this->option('dataset'), PATHINFO_FILENAME) : ($dataset['suite'] ?? 'core');
        $minimumReviewed = Arr::isList($dataset) ? 0 : (int) ($dataset['minimum_reviewed'] ?? 0);
        $seenIds = [];
        $results = [];
        foreach ($cases as $case) {
            $id = (string) ($case['id'] ?? '');
            if ($id === '' || isset($seenIds[$id])) {
                throw new RuntimeException($id === '' ? 'Caso de avaliação sem ID.' : "ID de avaliação duplicado: {$id}");
            }
            $seenIds[$id] = true;

            $evaluator = $case['evaluator'] ?? 'query_interpreter';
            $actual = match ($evaluator) {
                'query_interpreter' => $interpreter->interpret($case['input'], $case['previous'] ?? []),
                'agent_route' => $this->agentRoute($case, $interpreter, $agents),
                default => throw new RuntimeException("Avaliador desconhecido em {$id}: {$evaluator}"),
            };
            $errors = [];
            foreach ($case['expect'] as $path => $expected) {
                $value = data_get($actual, $path);
                $matches = is_numeric($value) && is_numeric($expected)
                    ? (float) $value === (float) $expected
                    : $value === $expected;
                if (! $matches) {
                    $errors[] = ['path' => $path, 'expected' => $expected, 'actual' => $value];
                }
            }
            $results[] = [
                'id' => $id,
                'category' => $case['category'] ?? 'uncategorized',
                'evaluator' => $evaluator,
                'reviewed' => ($case['reviewed'] ?? false) === true,
                'passed' => $errors === [],
                'errors' => $errors,
            ];
        }
        $passed = collect($results)->where('passed', true)->count();
        $reviewed = collect($results)->where('reviewed', true)->count();
        $categoryMetrics = collect($results)->groupBy('category')->map(fn ($items, $category) => [
            'category' => $category,
            'total' => $items->count(),
            'passed' => $items->where('passed', true)->count(),
            'failed' => $items->where('passed', false)->count(),
            'reviewed' => $items->where('reviewed', true)->count(),
        ])->values()->all();
        $evaluatorMetrics = collect($results)->groupBy('evaluator')->map(fn ($items, $evaluator) => [
            'evaluator' => $evaluator,
            'total' => $items->count(),
            'passed' => $items->where('passed', true)->count(),
            'failed' => $items->where('passed', false)->count(),
        ])->values()->all();
        $reviewThresholdPassed = $reviewed >= $minimumReviewed;
        $report = [
            'suite' => $suite,
            'prompt_version' => config('ai.prompt_version'),
            'generated_at' => now()->toIso8601String(),
            'deterministic_scope' => 'Somente extração estruturada e seleção de agente/workflow; qualidade subjetiva de texto exige revisão humana separada.',
            'minimum_reviewed' => $minimumReviewed,
            'reviewed' => $reviewed,
            'review_threshold_passed' => $reviewThresholdPassed,
            'total' => count($results),
            'passed' => $passed,
            'failed' => count($results) - $passed,
            'categories' => $categoryMetrics,
            'evaluators' => $evaluatorMetrics,
            'results' => $results,
        ];
        $output = base_path($this->option('output'));
        if (! is_dir(dirname($output))) {
            mkdir(dirname($output), 0775, true);
        }
        file_put_contents($output, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
        $this->info("{$passed}/".count($results)." avaliações aprovadas; {$reviewed} casos revisados.");
        if (! $reviewThresholdPassed) {
            $this->error("A suíte exige ao menos {$minimumReviewed} casos revisados.");
        }

        return $passed === count($results) && $reviewThresholdPassed ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $case
     * @return array<string, mixed>
     */
    private function agentRoute(array $case, PropertyQueryInterpreter $interpreter, AiAgentCatalog $agents): array
    {
        $criteria = $interpreter->interpret($case['input'], $case['previous'] ?? []);
        $route = $agents->select(new User, (string) $case['product'], $case['input'], $criteria['intent']);

        return ['criteria' => $criteria, 'route' => $route];
    }
}
