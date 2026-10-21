<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSearchMessage extends Model
{
    protected $fillable = ['ai_search_session_id', 'role', 'content', 'criteria_snapshot', 'listing_ids', 'citations', 'ui_blocks', 'tool_events'];

    protected function casts(): array
    {
        return [
            'criteria_snapshot' => 'array',
            'listing_ids' => 'array',
            'citations' => 'array',
            'ui_blocks' => 'array',
            'tool_events' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AiSearchSession::class, 'ai_search_session_id');
    }
}
