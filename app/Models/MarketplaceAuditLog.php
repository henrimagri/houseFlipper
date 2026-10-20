<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceAuditLog extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }
}
