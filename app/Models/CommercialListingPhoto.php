<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialListingPhoto extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'licensed_for_publication' => 'boolean',
            'is_cover' => 'boolean',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(CommercialListing::class, 'commercial_listing_id');
    }
}
