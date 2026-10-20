<?php

namespace App\Models;

use App\Enums\AuthorizationStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommercializationAuthorization extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => AuthorizationStatus::class,
            'granted_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function listingSource(): BelongsTo
    {
        return $this->belongsTo(ListingSource::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function listings(): HasMany
    {
        return $this->hasMany(CommercialListing::class, 'authorization_id');
    }

    public function isUsable(): bool
    {
        if ($this->status !== AuthorizationStatus::Granted) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
