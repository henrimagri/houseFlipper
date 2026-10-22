<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformControl extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['value' => 'array', 'expires_at' => 'datetime'];
    }
}
