<?php

namespace App\Models;

use App\Enums\AuthorizationStatus;
use App\Enums\BrokerSiteStatus;
use App\Enums\CommercialListingStatus;
use App\Enums\PropertyType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommercialListing extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => CommercialListingStatus::class,
            'property_type' => PropertyType::class,
            'asking_price_cents' => 'integer',
            'area_m2' => 'decimal:2',
            'highlight' => 'boolean',
            'published_at' => 'datetime',
            'unpublished_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function authorization(): BelongsTo
    {
        return $this->belongsTo(CommercializationAuthorization::class, 'authorization_id');
    }

    public function listingSource(): BelongsTo
    {
        return $this->belongsTo(ListingSource::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(CommercialListingPhoto::class)->orderBy('sort_order');
    }

    public function licensedPhotos(): HasMany
    {
        return $this->photos()->where('licensed_for_publication', true);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CrmContact::class);
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->where('commercial_listings.status', CommercialListingStatus::Published->value)
            ->whereHas('authorization', function (Builder $authorization): void {
                $authorization
                    ->where('status', AuthorizationStatus::Granted->value)
                    ->where(function (Builder $inner): void {
                        $inner->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    });
            })
            ->whereExists(function ($site): void {
                $site->selectRaw('1')
                    ->from('broker_sites')
                    ->whereColumn('broker_sites.user_id', 'commercial_listings.user_id')
                    ->whereIn('broker_sites.status', [
                        BrokerSiteStatus::Trial->value,
                        BrokerSiteStatus::Active->value,
                        BrokerSiteStatus::Grace->value,
                    ]);
            });
    }
}
