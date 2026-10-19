<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertySceneVersion extends Model
{
    protected $fillable = ['property_visualization_id', 'user_id', 'label', 'graph'];

    protected function casts(): array
    {
        return ['graph' => 'array'];
    }

    public function visualization(): BelongsTo
    {
        return $this->belongsTo(PropertyVisualization::class, 'property_visualization_id');
    }
}
