<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AiWorkerClient
{
    public function health(): array
    {
        return $this->http()->get($this->endpoint('/health'))->throw()->json();
    }

    public function models(): array
    {
        return $this->http()->get($this->endpoint('/v1/models'))->throw()->json();
    }

    public function analyzeVision(string $contents, string $filename, array $points = []): array
    {
        return $this->http()
            ->attach('file', $contents, $filename)
            ->post($this->endpoint('/v1/vision/analyze'), [
                'points' => $points === [] ? null : json_encode($points, JSON_THROW_ON_ERROR),
            ])->throw()->json();
    }

    public function simulatePhoto(
        string $contents,
        string $filename,
        string $color,
        array $points = [],
        float $opacity = 0.72,
        string $material = 'paint',
        string $maskMode = 'sam',
        array $maskStrokes = [],
    ): array {
        return $this->http()
            ->timeout((int) config('ai.worker.processing_timeout', 1800))
            ->attach('file', $contents, $filename)
            ->post($this->endpoint('/v1/vision/simulate'), [
                'color' => $color,
                'points' => $points === [] ? null : json_encode($points, JSON_THROW_ON_ERROR),
                'opacity' => $opacity,
                'material' => $material,
                'mask_mode' => $maskMode,
                ...($maskMode === 'manual' ? ['mask_strokes' => json_encode($maskStrokes, JSON_THROW_ON_ERROR)] : []),
            ])->throw()->json();
    }

    public function reconstruct(array $files, string $method, string $scope, string $idempotencyKey): array
    {
        $request = $this->http($scope)->timeout((int) config('ai.worker.processing_timeout', 1800));
        foreach ($files as $file) {
            $request = $request->attach('files', $file['contents'], $file['name']);
        }

        return $request->post($this->endpoint('/v1/3d/reconstruct'), [
            'overlap_acknowledged' => 'true',
            'method' => $method,
            'job_scope' => $scope,
            'idempotency_key' => $idempotencyKey,
        ])->throw()->json();
    }

    public function artifact(string $jobId, string $name, ?string $scope = null): string
    {
        if (! preg_match('/^[0-9a-f-]{36}$/i', $jobId) || ! preg_match('/^[a-z0-9.-]{1,80}$/i', $name)) {
            throw new RuntimeException('Identificador de artefato inválido.');
        }

        return $this->http($scope)->get($this->endpoint('/v1/jobs/'.$jobId.'/artifacts/'.$name))->throw()->body();
    }

    public function job(string $jobId, string $scope): array
    {
        if (! preg_match('/^[0-9a-f-]{36}$/i', $jobId)) {
            throw new RuntimeException('Identificador de job inválido.');
        }

        return $this->http($scope)->get($this->endpoint('/v1/jobs/'.$jobId))->throw()->json();
    }

    public function cancelJob(string $jobId, string $scope): array
    {
        if (! preg_match('/^[0-9a-f-]{36}$/i', $jobId)) {
            throw new RuntimeException('Identificador de job inválido.');
        }

        return $this->http($scope)->delete($this->endpoint('/v1/jobs/'.$jobId))->throw()->json();
    }

    private function http(?string $scope = null): PendingRequest
    {
        $token = (string) config('ai.worker.token');
        if ($token === '') {
            throw new RuntimeException('Credencial do worker de IA não configurada.');
        }

        $headers = ['X-Worker-Token' => $token];
        if ($scope !== null) {
            $headers['X-Worker-Scope'] = $scope;
        }

        return Http::acceptJson()->withHeaders($headers)
            ->timeout((int) config('ai.worker.timeout', 30))
            ->withOptions(['allow_redirects' => false]);
    }

    private function endpoint(string $path): string
    {
        $endpoint = rtrim((string) config('ai.worker.endpoint'), '/');
        $host = parse_url($endpoint, PHP_URL_HOST);
        if (! in_array($host, config('ai.worker.allowed_hosts', []), true)) {
            throw new RuntimeException('Host do worker de IA não autorizado.');
        }

        return $endpoint.$path;
    }
}
