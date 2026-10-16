<?php

namespace App\Models;

use App\Enums\OpportunityStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpportunityStatusHistory extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['old_status' => OpportunityStatus::class, 'new_status' => OpportunityStatus::class];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
