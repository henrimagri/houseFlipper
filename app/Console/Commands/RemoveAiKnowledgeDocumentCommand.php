<?php

namespace App\Console\Commands;

use App\Models\AiKnowledgeDocument;
use App\Services\Ai\QdrantVectorStore;
use Illuminate\Console\Command;

class RemoveAiKnowledgeDocumentCommand extends Command
{
    protected $signature = 'ai:knowledge:remove {document : ID do documento}';

    protected $description = 'Invalida um documento e remove seus pontos do índice vetorial';

    public function handle(QdrantVectorStore $vectors): int
    {
        $document = AiKnowledgeDocument::query()->findOrFail((int) $this->argument('document'));
        $document->update(['status' => 'deleted']);
        $points = $document->chunks()->whereNotNull('vector_point_id')->pluck('vector_point_id')->all();
        if ($vectors->available()) {
            $vectors->delete($points);
        }
        $document->chunks()->update(['vector_status' => 'deleted', 'vector_indexed_at' => null]);
        $this->info('Documento invalidado e pontos removidos.');

        return self::SUCCESS;
    }
}
