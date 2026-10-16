<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvestmentResult extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sale_date' => 'date'];
    }

    public function investmentAnalysis()
    {
        return $this->belongsTo(InvestmentAnalysis::class);
    }
}
