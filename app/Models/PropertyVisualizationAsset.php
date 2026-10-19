<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyVisualizationAsset extends Model
{
    protected $fillable = [
        'property_visualization_id', 'kind', 'room_key', 'path', 'original_name',
        'mime_type', 'size_bytes', 'checksum', 'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'size_bytes' => 'integer',
        ];
    }

    public function visualization(): BelongsTo
    {
        return $this->belongsTo(PropertyVisualization::class, 'property_visualization_id');
    }
}
