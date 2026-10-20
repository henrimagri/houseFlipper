<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantProductSubscription extends Model
{
    protected $fillable = [
        'tenant_id',
        'product_slug',
        'plan_slug',
        'active',
        'status',
        'trial_starts_at',
        'trial_ends_at',
        'grace_until',
        'current_period_end',
        'payment_provider',
        'external_subscription_id',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'status' => \App\Enums\SubscriptionStatus::class,
            'trial_starts_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'grace_until' => 'datetime',
            'current_period_end' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
