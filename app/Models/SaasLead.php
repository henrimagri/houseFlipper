<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaasLead extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['ip_hash'];

    protected function casts(): array
    {
        return [
            'consent' => 'boolean',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(SaasLeadEvent::class);
    }
}
