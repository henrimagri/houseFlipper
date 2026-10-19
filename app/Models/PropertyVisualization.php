<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PropertyVisualization extends Model
{
    protected $fillable = [
        'tenant_id', 'user_id', 'subject_type', 'subject_id', 'status', 'level',
        'quality_score', 'origin_label', 'limitations', 'rooms', 'scene_status', 'scene_brief', 'scene_graph',
    ];

    protected function casts(): array
    {
        return [
            'rooms' => 'array',
            'scene_brief' => 'array',
            'scene_graph' => 'array',
            'quality_score' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(PropertyVisualizationAsset::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PropertySceneVersion::class);
    }

    public function primaryModel(): HasMany
    {
        return $this->hasMany(PropertyVisualizationAsset::class)->whereIn('kind', ['glb', 'gltf'])->where('is_primary', true);
    }
}
