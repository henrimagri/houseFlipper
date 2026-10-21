<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiUserPreference extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['preferences' => 'array', 'consented_at' => 'datetime'];
    }
}
