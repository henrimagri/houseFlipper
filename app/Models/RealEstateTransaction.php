<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RealEstateTransaction extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'declared_transaction_value' => 'decimal:2',
            'tax_base_value' => 'decimal:2',
            'land_area_m2' => 'decimal:2',
            'built_area_m2' => 'decimal:2',
            'private_area_m2' => 'decimal:2',
            'calculation_area_m2' => 'decimal:2',
            'price_m2' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'geocoded_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ItbiImportBatch::class, 'import_batch_id');
    }
}
