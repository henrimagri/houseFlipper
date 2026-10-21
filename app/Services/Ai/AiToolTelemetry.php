<?php

namespace App\Services\Ai;

use App\Models\AiSearchSession;
use App\Models\AiToolRun;
use App\Models\User;

class AiToolTelemetry
{
    public function run(User $user, AiSearchSession $session, string $agent, string $tool, array $input, callable $callback): array
    {
        $started = hrtime(true);
        $status = 'success';
        $errorCode = null;
        try {
            $result = $callback();

            return $result;
        } catch (\Throwable $exception) {
            $status = 'failed';
            $errorCode = class_basename($exception);
            throw $exception;
        } finally {
            $count = isset($result) && is_array($result) ? count($result['items'] ?? $result) : null;
            AiToolRun::query()->create([
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'organization_id' => $session->organization_id,
                'ai_search_session_id' => $session->id,
                'product_slug' => $session->product_slug,
                'agent' => $agent,
                'tool' => $tool,
                'status' => $status,
                'latency_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
                'provider' => $tool === 'synthesize_response' ? config('ai.provider') : 'laravel',
                'model' => $this->modelFor($tool),
                'input_hash' => hash('sha256', json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                'input_summary' => $this->sanitize($input),
                'result_count' => $count,
                'error_code' => $errorCode,
            ]);
        }
    }

    private function modelFor(string $tool): string
    {
        if ($tool !== 'synthesize_response') {
            return 'deterministic-v1';
        }

        return match (config('ai.provider')) {
            'openai' => (string) config('ai.openai.model'),
            'local' => (string) config('ai.local.model'),
            default => 'deterministic-v1',
        };
    }

    private function sanitize(array $input): array
    {
        return collect($input)->except(['message', 'email', 'phone', 'cpf', 'cnpj'])->map(function ($value) {
            if (is_string($value)) {
                return mb_substr($value, 0, 120);
            }
            if (is_array($value)) {
                return array_slice($value, 0, 10, true);
            }

            return $value;
        })->all();
    }
}
