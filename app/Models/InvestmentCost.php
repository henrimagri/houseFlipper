<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvestmentCost extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'paid_at' => 'datetime'];
    }

    public function investmentAnalysis()
    {
        return $this->belongsTo(InvestmentAnalysis::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
