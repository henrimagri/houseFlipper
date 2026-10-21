<?php

namespace App\Console\Commands;

use App\Models\AiKnowledgeDocument;
use App\Services\Ai\QdrantVectorStore;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SyncAiKnowledgeCommand extends Command
{
    protected $signature = 'ai:knowledge:sync {--file=resources/ai/knowledge.json}';

    protected $description = 'Sincroniza a base de conhecimento autorizada da IA';

    public function handle(QdrantVectorStore $vectors): int
    {
        $path = base_path($this->option('file'));
        abort_unless(is_file($path), 422, 'Arquivo de conhecimento não encontrado.');
        $documents = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        foreach ($documents as $item) {
            $hash = hash('sha256', json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $sourceKey = hash('sha256', implode('|', [
                $item['tenant_id'] ?? 'public',
                $item['product_slug'] ?? '*',
                $item['source_url'] ?? $item['source_name'],
                $item['title'],
            ]));
            $document = AiKnowledgeDocument::query()->where('source_key', $sourceKey)->first()
                ?? AiKnowledgeDocument::query()->where('content_hash', $hash)->first()
                ?? new AiKnowledgeDocument;
            $oldPoints = $document->exists ? $document->chunks()->whereNotNull('vector_point_id')->pluck('vector_point_id')->all() : [];
            if ($vectors->available() && $oldPoints !== []) {
                try {
                    $vectors->delete($oldPoints);
                } catch (\Throwable $exception) {
                    $this->warn('Não foi possível remover vetores antigos: '.$exception->getMessage());
                }
            }
            $document->fill([
                'tenant_id' => $item['tenant_id'] ?? null,
                'product_slug' => $item['product_slug'] ?? null,
                'source_key' => $sourceKey,
                'title' => $item['title'],
                'category' => $item['category'] ?? 'platform',
                'source_url' => $item['source_url'] ?? null,
                'source_name' => $item['source_name'],
                'source_version' => $item['source_version'] ?? null,
                'license' => $item['license'] ?? null,
                'geography' => $item['geography'] ?? 'BR',
                'status' => 'active',
                'verified_at' => now(),
                'metadata' => $item['metadata'] ?? null,
            ])->save();
            $document->chunks()->delete();
            foreach ($item['chunks'] as $index => $content) {
                $document->chunks()->create([
                    'ordinal' => $index + 1,
                    'content' => $content,
                    'search_text' => Str::lower(Str::ascii($item['title'].' '.$content)),
                    'keywords' => $item['keywords'] ?? [],
                    'vector_status' => 'pending',
                ]);
            }
        }
        $this->info(count($documents).' documentos sincronizados.');

        return self::SUCCESS;
    }
}
