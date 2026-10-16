<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyMarketStatistic extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'reference_date' => 'date',
            'median_price' => 'decimal:2',
            'average_price' => 'decimal:2',
            'median_price_m2' => 'decimal:2',
            'average_price_m2' => 'decimal:2',
            'min_price_m2' => 'decimal:2',
            'max_price_m2' => 'decimal:2',
            'percentile_25_price_m2' => 'decimal:2',
            'percentile_75_price_m2' => 'decimal:2',
            'standard_deviation_price_m2' => 'decimal:2',
            'calculated_at' => 'datetime',
        ];
    }
}
