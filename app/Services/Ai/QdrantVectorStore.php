<?php

namespace App\Services\Ai;

use App\Models\AiKnowledgeChunk;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class QdrantVectorStore
{
    public function available(): bool
    {
        return (bool) config('ai.vector.enabled');
    }

    public function ensureCollection(): void
    {
        $response = $this->http()->get($this->endpoint('/collections/'.$this->collection()));
        if ($response->successful()) {
            $size = (int) data_get($response->json(), 'result.config.params.vectors.size');
            if ($size !== (int) config('ai.embeddings.dimensions')) {
                throw new RuntimeException('A coleção vetorial existe com dimensão incompatível.');
            }

            return;
        }
        if ($response->status() !== 404) {
            $response->throw();
        }
        $this->http()->put($this->endpoint('/collections/'.$this->collection()), [
            'vectors' => ['size' => (int) config('ai.embeddings.dimensions'), 'distance' => 'Cosine'],
        ])->throw();
    }

    public function upsert(AiKnowledgeChunk $chunk, array $vector): void
    {
        $document = $chunk->document;
        $this->http()->put($this->endpoint('/collections/'.$this->collection().'/points?wait=true'), [
            'points' => [[
                'id' => $chunk->id,
                'vector' => $vector,
                'payload' => [
                    'chunk_id' => $chunk->id,
                    'document_id' => $document->id,
                    'tenant_id' => $document->tenant_id ?? 0,
                    'scope' => $document->tenant_id === null ? 'public' : 'tenant',
                    'product_slug' => $document->product_slug ?? '*',
                    'content_hash' => $document->content_hash,
                    'embedding_model' => config('ai.embeddings.model'),
                ],
            ]],
        ])->throw();
    }

    public function search(array $vector, int $tenantId, string $product, int $limit = 8): array
    {
        $response = $this->http()->post($this->endpoint('/collections/'.$this->collection().'/points/query'), [
            'query' => $vector,
            'filter' => ['must' => [
                ['should' => [
                    ['key' => 'tenant_id', 'match' => ['value' => $tenantId]],
                    ['key' => 'scope', 'match' => ['value' => 'public']],
                ]],
                ['should' => [
                    ['key' => 'product_slug', 'match' => ['value' => $product]],
                    ['key' => 'product_slug', 'match' => ['value' => '*']],
                ]],
            ]],
            'limit' => max(1, min($limit, 20)),
            'with_payload' => true,
        ])->throw()->json();

        return collect(data_get($response, 'result.points', []))->map(fn (array $point) => [
            'chunk_id' => (int) data_get($point, 'payload.chunk_id'),
            'score' => (float) ($point['score'] ?? 0),
        ])->filter(fn (array $point) => $point['chunk_id'] > 0)->values()->all();
    }

    public function delete(array $pointIds): void
    {
        if ($pointIds === []) {
            return;
        }
        $this->http()->post($this->endpoint('/collections/'.$this->collection().'/points/delete?wait=true'), [
            'points' => collect($pointIds)->map(fn ($id) => ctype_digit((string) $id) ? (int) $id : $id)->values()->all(),
        ])->throw();
    }

    private function http(): PendingRequest
    {
        $request = Http::acceptJson()->asJson()
            ->timeout((int) config('ai.vector.timeout', 15))
            ->withOptions(['allow_redirects' => false]);
        if (filled(config('ai.vector.api_key'))) {
            $request = $request->withHeaders(['api-key' => config('ai.vector.api_key')]);
        }

        return $request;
    }

    private function endpoint(string $path): string
    {
        $endpoint = rtrim((string) config('ai.vector.endpoint'), '/');
        $host = parse_url($endpoint, PHP_URL_HOST);
        if (! in_array($host, config('ai.vector.allowed_hosts', []), true)) {
            throw new RuntimeException('Host do banco vetorial não autorizado.');
        }

        return $endpoint.$path;
    }

    private function collection(): string
    {
        $name = (string) config('ai.vector.collection');
        if (! preg_match('/^[a-zA-Z0-9_-]{1,120}$/', $name)) {
            throw new RuntimeException('Nome da coleção vetorial inválido.');
        }

        return $name;
    }
}
