<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommercialListingResource;
use App\Models\CommercializationAuthorization;
use App\Models\CommercialListing;
use App\Models\CommercialListingPhoto;
use App\Services\Marketplace\CommercialListingService;
use App\Services\Marketplace\MarketingCopyService;
use App\Services\Workspace\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommercialListingController extends Controller
{
    public function __construct(
        private readonly CommercialListingService $listings,
        private readonly MarketingCopyService $copy,
        private readonly OrganizationService $organizations,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = CommercialListing::query()->where('user_id', $user->id);
        $membership = $this->organizations->membership($user);
        if ($membership) {
            $query = CommercialListing::query()->where(function ($inner) use ($user, $membership) {
                $inner->where('user_id', $user->id)->orWhere('organization_id', $membership->organization_id);
            });
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($inner) use ($search) {
                $inner->where('title', 'like', '%'.$search.'%')
                    ->orWhere('city', 'like', '%'.$search.'%')
                    ->orWhere('neighborhood', 'like', '%'.$search.'%');
            });
        }
        $sort = $request->query('sort', 'latest');
        $query = match ($sort) {
            'price_asc' => $query->orderBy('asking_price_cents'),
            'price_desc' => $query->orderByDesc('asking_price_cents'),
            'title' => $query->orderBy('title'),
            default => $query->latest(),
        };
        $paginator = $query
            ->with(['photos', 'authorization', 'listingSource'])
            ->paginate($request->integer('per_page', 12));

        return response()->json([
            'data' => CommercialListingResource::collection($paginator->items())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'authorization_id' => ['required', 'integer', 'min:1'],
            'title' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'asking_price_cents' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
        ]);
        $authorization = CommercializationAuthorization::query()->findOrFail($data['authorization_id']);
        $this->authorize('view', $authorization);
        $listing = $this->listings->createFromAuthorization($request->user(), $authorization, $data);

        return (new CommercialListingResource($listing->load(['photos', 'authorization', 'listingSource'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(CommercialListing $commercialListing): CommercialListingResource
    {
        $this->authorize('view', $commercialListing);

        return new CommercialListingResource($commercialListing->load(['photos', 'authorization', 'listingSource']));
    }

    public function update(Request $request, CommercialListing $commercialListing): CommercialListingResource
    {
        $this->authorize('update', $commercialListing);
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:180'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'asking_price_cents' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:999999999999'],
            'area_m2' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999'],
            'bedrooms' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:20'],
            'bathrooms' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:20'],
            'parking_spaces' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:20'],
            'neighborhood' => ['sometimes', 'nullable', 'string', 'max:120'],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'state' => ['sometimes', 'nullable', 'string', 'size:2'],
            'highlight' => ['sometimes', 'boolean'],
        ]);

        return new CommercialListingResource(
            $this->listings->update($request->user(), $commercialListing, $data)->load(['photos', 'authorization', 'listingSource'])
        );
    }

    public function publish(Request $request, CommercialListing $commercialListing): CommercialListingResource
    {
        $this->authorize('update', $commercialListing);

        return new CommercialListingResource(
            $this->listings->publish($request->user(), $commercialListing)->load(['photos', 'authorization', 'listingSource'])
        );
    }

    public function unpublish(Request $request, CommercialListing $commercialListing): CommercialListingResource
    {
        $this->authorize('update', $commercialListing);

        return new CommercialListingResource(
            $this->listings->unpublish($request->user(), $commercialListing)->load(['photos', 'authorization', 'listingSource'])
        );
    }

    public function storePhoto(Request $request, CommercialListing $commercialListing): JsonResponse
    {
        $this->authorize('update', $commercialListing);
        $request->validate([
            'photo' => ['required', 'image', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
            'licensed_for_publication' => ['required', 'accepted'],
            'is_cover' => ['sometimes', 'boolean'],
        ]);
        $photo = $this->listings->storePhoto(
            $request->user(),
            $commercialListing,
            $request->file('photo'),
            $request->boolean('licensed_for_publication'),
            $request->boolean('is_cover'),
        );

        return response()->json(['data' => [
            'id' => $photo->id,
            'url' => '/api/commercial-listings/'.$commercialListing->id.'/photos/'.$photo->id.'/file',
            'licensed_for_publication' => true,
            'is_cover' => $photo->is_cover,
        ]], 201);
    }

    public function photoFile(Request $request, CommercialListing $commercialListing, CommercialListingPhoto $photo): StreamedResponse
    {
        $this->authorize('view', $commercialListing);

        return $this->listings->streamPhoto($commercialListing, $photo, false);
    }

    public function copy(Request $request, CommercialListing $commercialListing): JsonResponse
    {
        $this->authorize('view', $commercialListing);
        $data = $request->validate(['style' => ['required', 'string', 'max:40']]);

        return response()->json([
            'data' => $this->copy->compose($request->user(), $commercialListing, $data['style']),
        ]);
    }
}
