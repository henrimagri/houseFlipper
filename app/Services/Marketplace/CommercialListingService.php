<?php

namespace App\Services\Marketplace;

use App\Enums\CommercialListingStatus;
use App\Enums\PropertyType;
use App\Models\CommercializationAuthorization;
use App\Models\CommercialListing;
use App\Models\CommercialListingPhoto;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\Ai\ProductEntitlement;
use App\Support\CommercialPhotoPathPolicy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommercialListingService
{
    public function __construct(
        private readonly ProductEntitlement $entitlement,
        private readonly MarketplaceAudit $audit,
        private readonly BrokerSiteService $sites,
    ) {}

    public function createFromAuthorization(User $user, CommercializationAuthorization $authorization, array $overrides = []): CommercialListing
    {
        $this->entitlement->assert($user, 'commercial_wallet');
        $this->assertAuthorizationOwner($user, $authorization);
        if (! $authorization->isUsable()) {
            throw ValidationException::withMessages(['authorization_id' => 'A autorização ainda não permite incluir o imóvel na carteira.']);
        }

        $existing = CommercialListing::query()
            ->where('user_id', $user->id)
            ->where('authorization_id', $authorization->id)
            ->first();
        if ($existing) {
            return $existing->load('photos');
        }

        $opportunity = $authorization->opportunity_id
            ? Opportunity::query()->where('user_id', $user->id)->find($authorization->opportunity_id)
            : null;
        $source = $authorization->listingSource;
        $title = $overrides['title'] ?? $opportunity?->title ?? $source?->title ?? 'Imóvel';
        $price = $overrides['asking_price_cents'] ?? ($opportunity && $opportunity->asking_price !== null
            ? (int) round(((float) $opportunity->asking_price) * 100)
            : $source?->price_cents);

        $membership = app(\App\Services\Workspace\OrganizationService::class)->membership($user);
        $listing = CommercialListing::query()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'organization_id' => $membership?->organization_id,
            'branch_id' => $membership?->branch_id,
            'authorization_id' => $authorization->id,
            'listing_source_id' => $authorization->listing_source_id,
            'opportunity_id' => $authorization->opportunity_id,
            'slug' => $this->uniqueSlug(Str::slug($title) ?: 'imovel'),
            'title' => mb_substr($title, 0, 180),
            'description' => $overrides['description'] ?? null,
            'property_type' => $overrides['property_type'] ?? $opportunity?->property_type?->value ?? PropertyType::Other->value,
            'asking_price_cents' => $price,
            'area_m2' => $overrides['area_m2'] ?? $opportunity?->area_m2 ?? $source?->area_m2,
            'bedrooms' => $overrides['bedrooms'] ?? $opportunity?->bedrooms,
            'bathrooms' => $overrides['bathrooms'] ?? $opportunity?->bathrooms,
            'parking_spaces' => $overrides['parking_spaces'] ?? $opportunity?->parking_spaces,
            'address' => $overrides['address'] ?? $opportunity?->address,
            'neighborhood' => $overrides['neighborhood'] ?? $opportunity?->neighborhood,
            'city' => $overrides['city'] ?? $opportunity?->city,
            'state' => $overrides['state'] ?? $opportunity?->state,
            'latitude' => $opportunity?->latitude,
            'longitude' => $opportunity?->longitude,
            'status' => CommercialListingStatus::Draft,
        ]);
        $this->sites->forUser($user);
        $this->audit->record($user, 'listing.created', $listing);

        return $listing->load('photos');
    }

    public function update(User $user, CommercialListing $listing, array $data): CommercialListing
    {
        $this->entitlement->assert($user, 'commercial_wallet');
        $this->assertListingOwner($user, $listing);
        $listing->fill([
            'title' => $data['title'] ?? $listing->title,
            'description' => array_key_exists('description', $data) ? $data['description'] : $listing->description,
            'asking_price_cents' => array_key_exists('asking_price_cents', $data) ? $data['asking_price_cents'] : $listing->asking_price_cents,
            'area_m2' => array_key_exists('area_m2', $data) ? $data['area_m2'] : $listing->area_m2,
            'bedrooms' => array_key_exists('bedrooms', $data) ? $data['bedrooms'] : $listing->bedrooms,
            'bathrooms' => array_key_exists('bathrooms', $data) ? $data['bathrooms'] : $listing->bathrooms,
            'parking_spaces' => array_key_exists('parking_spaces', $data) ? $data['parking_spaces'] : $listing->parking_spaces,
            'neighborhood' => array_key_exists('neighborhood', $data) ? $data['neighborhood'] : $listing->neighborhood,
            'city' => array_key_exists('city', $data) ? $data['city'] : $listing->city,
            'state' => array_key_exists('state', $data) ? $data['state'] : $listing->state,
            'highlight' => array_key_exists('highlight', $data) ? (bool) $data['highlight'] : $listing->highlight,
        ])->save();
        $this->audit->record($user, 'listing.updated', $listing);

        return $listing->fresh('photos');
    }

    public function publish(User $user, CommercialListing $listing): CommercialListing
    {
        $this->entitlement->assert($user, 'public_listing');
        $this->assertListingOwner($user, $listing);
        $listing->load('authorization');
        if (! $listing->authorization?->isUsable()) {
            abort(403, 'O imóvel não pode ser publicado sem autorização de comercialização vigente.');
        }
        if (! $this->sites->isLive($user)) {
            abort(403, 'A página pública está suspensa ou fora do plano.');
        }
        $hasLicensedPhoto = $listing->photos()->where('licensed_for_publication', true)->exists();
        if (! $hasLicensedPhoto) {
            throw ValidationException::withMessages(['photos' => 'Publique somente com fotos licenciadas para esta finalidade.']);
        }

        $listing->fill([
            'status' => CommercialListingStatus::Published,
            'published_at' => now(),
            'unpublished_at' => null,
            'unpublish_reason' => null,
        ])->save();
        $this->audit->record($user, 'listing.published', $listing);

        return $listing->fresh('photos');
    }

    public function unpublish(User $user, CommercialListing $listing, string $reason = 'manual'): CommercialListing
    {
        $this->entitlement->assert($user, 'commercial_wallet');
        $this->assertListingOwner($user, $listing);
        $listing->fill([
            'status' => CommercialListingStatus::Paused,
            'unpublished_at' => now(),
            'unpublish_reason' => mb_substr($reason, 0, 120),
        ])->save();
        $this->audit->record($user, 'listing.unpublished', $listing, ['reason' => $reason]);

        return $listing->fresh('photos');
    }

    public function unpublishForAuthorization(User $user, CommercializationAuthorization $authorization, string $reason): void
    {
        CommercialListing::query()
            ->where('user_id', $user->id)
            ->where('authorization_id', $authorization->id)
            ->where('status', CommercialListingStatus::Published)
            ->get()
            ->each(function (CommercialListing $listing) use ($reason): void {
                $listing->fill([
                    'status' => CommercialListingStatus::Paused,
                    'unpublished_at' => now(),
                    'unpublish_reason' => mb_substr($reason, 0, 120),
                ])->save();
            });
    }

    public function storePhoto(User $user, CommercialListing $listing, UploadedFile $file, bool $licensed, bool $cover): CommercialListingPhoto
    {
        $this->entitlement->assert($user, 'commercial_wallet');
        $this->assertListingOwner($user, $listing);
        if (! $licensed) {
            throw ValidationException::withMessages(['licensed_for_publication' => 'Marque a licença para usar esta foto na divulgação.']);
        }
        if ($listing->photos()->count() >= 20) {
            throw ValidationException::withMessages(['photo' => 'Limite de 20 fotos por imóvel.']);
        }
        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw ValidationException::withMessages(['photo' => 'Use JPG, PNG ou WebP.']);
        }
        $path = 'commercial-listings/'.$listing->id.'/'.Str::uuid().'.'.$extension;
        if (! CommercialPhotoPathPolicy::allows($path)) {
            throw ValidationException::withMessages(['photo' => 'Caminho de foto inválido.']);
        }
        Storage::disk('local')->put($path, $file->getContent());

        return DB::transaction(function () use ($user, $listing, $path, $file, $cover) {
            if ($cover) {
                $listing->photos()->update(['is_cover' => false]);
            }
            $photo = $listing->photos()->create([
                'path' => $path,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 180),
                'licensed_for_publication' => true,
                'is_cover' => $cover || $listing->photos()->count() === 0,
                'sort_order' => (int) $listing->photos()->max('sort_order') + 1,
            ]);
            $this->audit->record($user, 'listing.photo_added', $listing, ['photo_id' => $photo->id]);

            return $photo;
        });
    }

    public function streamPhoto(CommercialListing $listing, CommercialListingPhoto $photo, bool $public): StreamedResponse
    {
        if ($photo->commercial_listing_id !== $listing->id) {
            abort(404);
        }
        if ($public && (! $photo->licensed_for_publication || $listing->status !== CommercialListingStatus::Published)) {
            abort(404);
        }
        if (! CommercialPhotoPathPolicy::allows($photo->path) || ! Storage::disk('local')->exists($photo->path)) {
            abort(404);
        }

        return Storage::disk('local')->response($photo->path, $photo->original_name, [
            'Content-Disposition' => 'inline',
        ]);
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $i = 1;
        while (CommercialListing::withoutGlobalScopes()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    private function assertListingOwner(User $user, CommercialListing $listing): void
    {
        if ($user->id !== $listing->user_id || (int) $user->tenant_id !== (int) $listing->tenant_id) {
            abort(404);
        }
    }

    private function assertAuthorizationOwner(User $user, CommercializationAuthorization $authorization): void
    {
        if ($user->id !== $authorization->user_id || (int) $user->tenant_id !== (int) $authorization->tenant_id) {
            abort(404);
        }
    }
}
