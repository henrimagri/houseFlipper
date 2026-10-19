<?php

namespace App\Models;

use App\Enums\OpportunitySource;
use App\Enums\OpportunityStatus;
use App\Enums\PropertyType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Opportunity extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected $attributes = [
        'status' => 'new',
        'source' => 'manual',
        'is_favorite' => false,
        'is_archived' => false,
    ];

    protected static function booted(): void
    {
        static::saving(function (self $opportunity): void {
            $opportunity->is_archived = $opportunity->status === OpportunityStatus::Archived;
        });
    }

    protected function casts(): array
    {
        return ['status' => OpportunityStatus::class, 'property_type' => PropertyType::class, 'source' => OpportunitySource::class, 'is_favorite' => 'boolean', 'is_archived' => 'boolean', 'asking_price' => 'decimal:2', 'estimated_market_price' => 'decimal:2'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(OpportunityPhoto::class)->orderBy('order');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OpportunityStatusHistory::class)->latest();
    }

    public function coverPhoto()
    {
        return $this->hasOne(OpportunityPhoto::class)->where('is_cover', true)->withDefault();
    }

    public function investmentAnalyses(): HasMany
    {
        return $this->hasMany(InvestmentAnalysis::class);
    }
}
