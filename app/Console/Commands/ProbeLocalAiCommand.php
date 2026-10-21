<?php

namespace App\Console\Commands;

use App\Services\Ai\AiResponseSynthesizer;
use App\Services\Ai\OllamaClient;
use App\Services\Ai\PropertyQueryInterpreter;
use Illuminate\Console\Command;

class ProbeLocalAiCommand extends Command
{
    protected $signature = 'ai:local:probe {--question=Encontre apartamentos em Fortaleza com três quartos e duas vagas, até R$ 700 mil.}';

    protected $description = 'Executa inferência neural local real pela camada de IA do Laravel';

    public function handle(PropertyQueryInterpreter $interpreter, AiResponseSynthesizer $synthesizer, OllamaClient $ollama): int
    {
        if (config('ai.provider') !== 'local') {
            $this->error('Configure AI_PROVIDER=local antes de executar o probe.');

            return self::FAILURE;
        }
        $question = (string) $this->option('question');
        $criteria = $interpreter->interpret($question);
        $verified = 'Consulta interpretada pelo Laravel: cidade Fortaleza, tipo apartamento, mínimo de 3 quartos, mínimo de 2 vagas e preço máximo de R$ 700.000. A busca real deve usar somente ferramentas autorizadas e não há imóveis fornecidos neste teste.';
        $started = hrtime(true);
        $result = $synthesizer->synthesize($question, $verified, ['criteria' => $criteria, 'results' => []]);
        $latency = (int) round((hrtime(true) - $started) / 1_000_000);
        $models = collect($ollama->models());
        $model = $models->firstWhere('name', config('ai.local.model')) ?? $models->firstWhere('model', config('ai.local.model'));

        $this->line(json_encode([
            'provider' => $result['provider'],
            'model' => $result['model'] ?? config('ai.local.model'),
            'digest' => $model['digest'] ?? null,
            'latency_ms' => $latency,
            'usage' => $result['usage'] ?? null,
            'metrics' => $result['metrics'] ?? null,
            'criteria' => $criteria,
            'response' => $result['text'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $result['provider'] === 'local' ? self::SUCCESS : self::FAILURE;
    }
}
