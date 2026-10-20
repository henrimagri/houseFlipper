<?php

namespace App\Models;

use App\Enums\DealStage;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmDeal extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'stage' => DealStage::class,
            'estimated_value_cents' => 'integer',
            'lock_version' => 'integer',
            'next_activity_at' => 'datetime',
            'last_interaction_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'crm_contact_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(CommercialListing::class, 'commercial_listing_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmDealActivity::class)->latest();
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(CrmProposal::class);
    }
}
