<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(TenantProductSubscription::class, 'tenant_product_subscription_id');
    }
}
