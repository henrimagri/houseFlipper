<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyProcessingJob extends Model
{
    protected $fillable = [
        'property_visualization_id', 'tenant_id', 'user_id', 'worker_job_id', 'kind', 'status',
        'input_asset_ids', 'result', 'error', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'input_asset_ids' => 'array',
            'result' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function visualization(): BelongsTo
    {
        return $this->belongsTo(PropertyVisualization::class, 'property_visualization_id');
    }
}
