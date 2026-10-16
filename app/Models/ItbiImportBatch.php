<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItbiImportBatch extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'discovered_at' => 'datetime',
            'downloaded_at' => 'datetime',
            'processing_started_at' => 'datetime',
            'processing_finished_at' => 'datetime',
            'metadata_json' => 'array',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(RealEstateTransaction::class, 'import_batch_id');
    }
}
