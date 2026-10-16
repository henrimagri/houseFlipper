<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancingDetail extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_selected' => 'boolean'];
    }

    public function investmentAnalysis()
    {
        return $this->belongsTo(InvestmentAnalysis::class);
    }
}
