<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpportunityPhoto extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_cover' => 'boolean'];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }
}
