<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommercialListing;
use App\Models\CommercialListingPhoto;
use App\Models\CrmContact;
use App\Models\Scopes\TenantScope;
use App\Services\Marketplace\BrokerSiteService;
use App\Services\Marketplace\CommercialListingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicBrokerController extends Controller
{
    public function __construct(
        private readonly BrokerSiteService $sites,
        private readonly CommercialListingService $listings,
    ) {}

    public function show(string $slug): JsonResponse
    {
        $site = $this->sites->publicBySlug($slug);
        if (! $site) {
            abort(404);
        }
        $site->increment('views_count');
        $listings = CommercialListing::withoutGlobalScope(TenantScope::class)
            ->publiclyVisible()
            ->where('user_id', $site->user_id)
            ->with(['photos' => fn ($q) => $q->where('licensed_for_publication', true)])
            ->orderByDesc('highlight')
            ->latest('published_at')
            ->get();

        return response()->json([
            'data' => [
                'slug' => $site->slug,
                'status' => $site->status->value,
                'theme' => $site->theme_json,
                'listings' => $listings->map(fn (CommercialListing $listing) => $this->publicListing($listing, $site->slug)),
            ],
        ]);
    }

    public function listing(string $listingSlug): JsonResponse
    {
        $listing = $this->visibleListing($listingSlug);
        $site = $this->sites->publicBySlug($this->siteSlugFor((int) $listing->user_id));
        if (! $site) {
            abort(404);
        }
        $listing->increment('views_count');
        $listing->load(['photos' => fn ($q) => $q->where('licensed_for_publication', true)]);

        return response()->json([
            'data' => [
                'broker' => [
                    'slug' => $site->slug,
                    'theme' => $site->theme_json,
                ],
                'listing' => $this->publicListing($listing, $site->slug),
            ],
        ]);
    }

    public function photo(string $listingSlug, CommercialListingPhoto $photo): StreamedResponse
    {
        $listing = $this->visibleListing($listingSlug);

        return $this->listings->streamPhoto($listing, $photo, true);
    }

    public function lead(Request $request, string $listingSlug): JsonResponse
    {
        $listing = $this->visibleListing($listingSlug);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'email' => ['nullable', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'message' => ['nullable', 'string', 'max:2000'],
            'consent' => ['required', 'accepted'],
        ]);
        if (blank($data['email'] ?? null) && blank($data['phone'] ?? null)) {
            return response()->json(['message' => 'Informe e-mail ou telefone.'], 422);
        }

        CrmContact::query()->create([
            'tenant_id' => $listing->tenant_id,
            'user_id' => $listing->user_id,
            'commercial_listing_id' => $listing->id,
            'kind' => 'lead',
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'message' => $data['message'] ?? null,
            'consent' => true,
            'source' => 'public_form',
            'status' => 'new',
        ]);

        return response()->json(['message' => 'Recebemos seu interesse. O corretor responsável fará o contato.'], 201);
    }

    private function visibleListing(string $listingSlug): CommercialListing
    {
        $listing = CommercialListing::withoutGlobalScope(TenantScope::class)
            ->publiclyVisible()
            ->where('slug', $listingSlug)
            ->first();
        if (! $listing) {
            abort(404);
        }

        return $listing;
    }

    private function siteSlugFor(int $userId): string
    {
        $site = \App\Models\BrokerSite::withoutGlobalScope(TenantScope::class)->where('user_id', $userId)->first();

        return $site?->slug ?? '';
    }

    private function publicListing(CommercialListing $listing, string $brokerSlug): array
    {
        return [
            'slug' => $listing->slug,
            'title' => $listing->title,
            'description' => $listing->description,
            'property_type' => $listing->property_type->value,
            'property_type_label' => $listing->property_type->label(),
            'asking_price_cents' => $listing->asking_price_cents,
            'area_m2' => $listing->area_m2,
            'bedrooms' => $listing->bedrooms,
            'bathrooms' => $listing->bathrooms,
            'parking_spaces' => $listing->parking_spaces,
            'neighborhood' => $listing->neighborhood,
            'city' => $listing->city,
            'state' => $listing->state,
            'highlight' => $listing->highlight,
            'photos' => $listing->photos
                ->where('licensed_for_publication', true)
                ->values()
                ->map(fn (CommercialListingPhoto $photo) => [
                    'id' => $photo->id,
                    'url' => '/api/public/listings/'.$listing->slug.'/photos/'.$photo->id,
                    'is_cover' => $photo->is_cover,
                ]),
            'path' => '/corretor/'.$brokerSlug.'/imovel/'.$listing->slug,
        ];
    }
}
