<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvestmentAnalysis extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['projeto_id' => 'integer', 'is_primary' => 'boolean', 'viability_completed_at' => 'datetime', 'financing_completed_at' => 'datetime', 'costs_completed_at' => 'datetime', 'result_completed_at' => 'datetime'];
    }

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function financingDetail()
    {
        return $this->hasOne(FinancingDetail::class)->where('is_selected', true);
    }

    public function financingSimulations()
    {
        return $this->hasMany(FinancingDetail::class);
    }

    public function costs()
    {
        return $this->hasMany(InvestmentCost::class)->latest();
    }

    public function result()
    {
        return $this->hasOne(InvestmentResult::class);
    }
}
