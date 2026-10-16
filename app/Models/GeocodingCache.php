<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeocodingCache extends Model
{
    protected $table = 'geocoding_cache';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'partial_match' => 'boolean',
            'response_payload' => 'array',
            'queried_at' => 'datetime',
        ];
    }
}
