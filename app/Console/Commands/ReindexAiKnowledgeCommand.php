<?php

namespace App\Console\Commands;

use App\Models\AiKnowledgeChunk;
use App\Services\Ai\OllamaClient;
use App\Services\Ai\QdrantVectorStore;
use Illuminate\Console\Command;

class ReindexAiKnowledgeCommand extends Command
{
    protected $signature = 'ai:knowledge:reindex {--force : Recria também embeddings atuais}';

    protected $description = 'Gera embeddings reais e indexa os chunks autorizados no Qdrant';

    public function handle(OllamaClient $ollama, QdrantVectorStore $vectors): int
    {
        if (! $vectors->available()) {
            $this->error('AI_VECTOR_ENABLED precisa estar ativo para indexar.');

            return self::FAILURE;
        }
        $vectors->ensureCollection();
        $query = AiKnowledgeChunk::query()->with('document')->whereHas('document', fn ($q) => $q->where('status', 'active'));
        if (! $this->option('force')) {
            $query->where(fn ($q) => $q->where('vector_status', '!=', 'indexed')->orWhereNull('vector_status'));
        }
        $indexed = 0;
        $failed = 0;
        $query->orderBy('id')->each(function (AiKnowledgeChunk $chunk) use ($ollama, $vectors, &$indexed, &$failed): void {
            try {
                $vector = $ollama->embed($chunk->content);
                $vectors->upsert($chunk, $vector);
                $chunk->update([
                    'vector_point_id' => (string) $chunk->id,
                    'embedding_provider' => 'ollama',
                    'embedding_model' => config('ai.embeddings.model'),
                    'embedding_dimensions' => count($vector),
                    'embedding_hash' => hash('sha256', config('ai.embeddings.model').'|'.$chunk->content),
                    'vector_status' => 'indexed',
                    'vector_indexed_at' => now(),
                    'vector_error' => null,
                ]);
                $indexed++;
            } catch (\Throwable $exception) {
                $chunk->update(['vector_status' => 'failed', 'vector_error' => mb_substr($exception->getMessage(), 0, 1000)]);
                $failed++;
            }
        });
        $this->info("{$indexed} chunks indexados; {$failed} falhas.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
