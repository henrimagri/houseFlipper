<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiKnowledgeChunk extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['keywords' => 'array', 'metadata' => 'array', 'vector_indexed_at' => 'datetime'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(AiKnowledgeDocument::class, 'ai_knowledge_document_id');
    }
}
