<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyPhotoAnalysis extends Model
{
    protected $fillable = [
        'tenant_id', 'user_id', 'subject_type', 'subject_id', 'source_photo_id', 'source_name',
        'room_guess', 'elements', 'confidence', 'origin', 'quality', 'limitations', 'palette', 'pixel_stats',
    ];

    protected function casts(): array
    {
        return [
            'elements' => 'array',
            'limitations' => 'array',
            'palette' => 'array',
            'pixel_stats' => 'array',
            'confidence' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
