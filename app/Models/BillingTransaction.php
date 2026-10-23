<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Immutable normalized evidence, written exclusively through BillingLedger. */
class BillingTransaction extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'recurring_months' => 'integer',
            'effective_at' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
            'service_period_start' => 'immutable_datetime',
            'service_period_end' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Ledger records are append-only.'));
        static::deleting(fn () => throw new LogicException('Ledger records are append-only.'));
    }
}
