<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class MarketplaceAudit
{
    public function record(User $user, string $action, Model $subject, array $meta = []): void
    {
        if (! $user->tenant_id) {
            return;
        }

        MarketplaceAuditLog::query()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'action' => $action,
            'subject_type' => $subject::class,
            'subject_id' => (int) $subject->getKey(),
            'meta' => $meta === [] ? null : $meta,
        ]);
    }
}
