<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformMfaCredential extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['secret', 'recovery_hashes'];

    protected function casts(): array
    {
        return ['secret' => 'encrypted', 'recovery_hashes' => 'array', 'confirmed_at' => 'datetime', 'pending_expires_at' => 'datetime'];
    }
}
