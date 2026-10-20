<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaasLeadEvent extends Model
{
    protected $guarded = ['id'];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(SaasLead::class, 'saas_lead_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
