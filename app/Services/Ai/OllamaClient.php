<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OllamaClient
{
    public function chat(array $messages): array
    {
        $response = $this->http()->timeout((int) config('ai.local.timeout', 120))
            ->post($this->endpoint('/api/chat'), [
                'model' => config('ai.local.model'),
                'messages' => $messages,
                'stream' => false,
                'think' => false,
                'options' => ['temperature' => 0.1, 'num_predict' => 280],
            ])->throw()->json();
        $text = trim((string) data_get($response, 'message.content'));
        if ($text === '') {
            throw new RuntimeException('O modelo local retornou uma resposta vazia.');
        }

        return [
            'text' => $text,
            'model' => (string) ($response['model'] ?? config('ai.local.model')),
            'usage' => [
                'input_tokens' => $response['prompt_eval_count'] ?? null,
                'output_tokens' => $response['eval_count'] ?? null,
            ],
            'metrics' => [
                'total_duration_ns' => $response['total_duration'] ?? null,
                'load_duration_ns' => $response['load_duration'] ?? null,
                'prompt_eval_duration_ns' => $response['prompt_eval_duration'] ?? null,
                'eval_duration_ns' => $response['eval_duration'] ?? null,
            ],
        ];
    }

    public function embed(string $text): array
    {
        $response = $this->http()->timeout((int) config('ai.local.timeout', 120))
            ->post($this->endpoint('/api/embed'), [
                'model' => config('ai.embeddings.model'),
                'input' => $text,
                'truncate' => true,
            ])->throw()->json();
        $vector = data_get($response, 'embeddings.0');
        if (! is_array($vector) || $vector === [] || collect($vector)->contains(fn ($value) => ! is_numeric($value) || ! is_finite((float) $value))) {
            throw new RuntimeException('O modelo de embeddings retornou um vetor inválido.');
        }
        $expected = (int) config('ai.embeddings.dimensions');
        if (count($vector) !== $expected) {
            throw new RuntimeException("Dimensão do embedding divergente: esperado {$expected}, recebido ".count($vector).'.');
        }

        return array_map('floatval', $vector);
    }

    public function models(): array
    {
        return $this->http()->get($this->endpoint('/api/tags'))->throw()->json('models', []);
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()->asJson()->withOptions(['allow_redirects' => false]);
    }

    private function endpoint(string $path): string
    {
        $endpoint = rtrim((string) config('ai.local.endpoint'), '/');
        $host = parse_url($endpoint, PHP_URL_HOST);
        if (! in_array($host, config('ai.local.allowed_hosts', []), true)) {
            throw new RuntimeException('Host do modelo local não autorizado.');
        }

        return $endpoint.$path;
    }
}
