<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuctionListing extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer', 'appraisal_cents' => 'integer',
            'auction_at' => 'datetime', 'source_updated_at' => 'datetime', 'collected_at' => 'datetime',
            'latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'source_payload' => 'array',
        ];
    }
}
