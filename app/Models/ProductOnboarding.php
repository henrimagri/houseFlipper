<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductOnboarding extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'completed_steps' => 'array',
            'dismissed' => 'boolean',
            'completed_at' => 'datetime',
            'current_step' => 'integer',
            'tour_version' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
