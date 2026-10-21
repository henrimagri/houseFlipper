<?php

namespace App\Services\Ai;

use App\Models\AiKnowledgeChunk;
use App\Models\User;
use Illuminate\Support\Str;

class AiKnowledgeService
{
    public function __construct(
        private readonly OllamaClient $ollama,
        private readonly QdrantVectorStore $vectors,
    ) {}

    public function retrieve(User $user, string $product, string $query, int $limit = 4): array
    {
        $lexical = $this->lexical($user, $product, $query, max($limit * 2, 8));
        $vector = [];
        if ($this->vectors->available()) {
            try {
                $vector = $this->vectors->search($this->ollama->embed($query), (int) $user->tenant_id, $product, max($limit * 2, 8));
            } catch (\Throwable) {
                $vector = [];
            }
        }

        $scores = [];
        foreach ($lexical as $rank => $row) {
            $scores[$row['chunk_id']] = ($scores[$row['chunk_id']] ?? 0) + 1 / (60 + $rank + 1);
        }
        foreach ($vector as $rank => $row) {
            $scores[$row['chunk_id']] = ($scores[$row['chunk_id']] ?? 0) + 1 / (60 + $rank + 1);
        }
        arsort($scores);
        $ids = array_slice(array_keys($scores), 0, max(1, min($limit, 8)));
        if ($ids === []) {
            return [];
        }

        $authorized = $this->authorized($user, $product)->whereKey($ids)->get()->keyBy('id');

        return collect($ids)->map(function (int $id) use ($authorized, $scores, $vector): ?array {
            $chunk = $authorized->get($id);
            if (! $chunk) {
                return null;
            }

            return $this->format($chunk, $scores[$id], collect($vector)->contains('chunk_id', $id) ? 'hybrid' : 'lexical');
        })->filter()->values()->all();
    }

    private function lexical(User $user, string $product, string $query, int $limit): array
    {
        $terms = $this->terms($query);
        if ($terms === []) {
            return [];
        }

        return $this->authorized($user, $product)
            ->get()
            ->map(function (AiKnowledgeChunk $chunk) use ($terms): array {
                $haystack = Str::lower(Str::ascii($chunk->search_text.' '.$chunk->content));
                $matches = collect($terms)->filter(fn (string $term) => Str::contains($haystack, $term))->count();
                $phrase = Str::contains($haystack, implode(' ', $terms)) ? 3 : 0;

                return ['chunk' => $chunk, 'score' => $matches + $phrase];
            })
            ->filter(fn (array $row) => $row['score'] >= 2)
            ->sortByDesc('score')
            ->take(max(1, min($limit, 16)))
            ->map(fn (array $row): array => ['chunk_id' => $row['chunk']->id, 'score' => $row['score']])
            ->values()->all();
    }

    public function shouldRetrieve(string $message): bool
    {
        return Str::contains(Str::lower(Str::ascii($message)), [
            'itbi', 'cartorio', 'registro', 'matricula', 'financiamento', 'documento',
            'lei', 'juridic', 'contrato', 'como funciona', 'produto', 'plano', 'reforma',
        ]);
    }

    private function terms(string $query): array
    {
        $stop = ['para', 'como', 'com', 'uma', 'que', 'por', 'dos', 'das', 'qual', 'quais', 'sobre', 'isso', 'este', 'esta', 'funciona', 'funcionar', 'imovel', 'imoveis', 'brasil'];

        return collect(preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii($query))) ?: [])
            ->filter(fn (string $term) => mb_strlen($term) >= 3 && ! in_array($term, $stop, true))
            ->unique()->values()->all();
    }

    private function authorized(User $user, string $product)
    {
        return AiKnowledgeChunk::query()->with('document')->whereHas('document', function ($builder) use ($user, $product) {
            $builder->where('status', 'active')
                ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $user->tenant_id))
                ->where(fn ($q) => $q->whereNull('product_slug')->orWhere('product_slug', $product));
        });
    }

    private function format(AiKnowledgeChunk $chunk, float $score, string $retrievalMode): array
    {
        $document = $chunk->document;

        return [
            'id' => 'knowledge-'.$chunk->id,
            'chunk_id' => $chunk->id,
            'title' => $document->title,
            'excerpt' => Str::limit($chunk->content, 500),
            'source_name' => $document->source_name,
            'source_url' => $document->source_url,
            'source_version' => $document->source_version,
            'verified_at' => $document->verified_at?->toDateString(),
            'category' => $document->category,
            'score' => round($score, 8),
            'retrieval_mode' => $retrievalMode,
        ];
    }
}
