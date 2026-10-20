<?php

namespace App\Http\Controllers\Api;

use App\Enums\BrokerSiteStatus;
use App\Http\Controllers\Controller;
use App\Models\AgencySite;
use App\Models\CommercialListing;
use App\Models\Scopes\TenantScope;
use App\Services\Ai\ProductEntitlement;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class PublicAgencyController extends Controller
{
    public function __construct(private readonly ProductEntitlement $entitlement) {}

    public function show(string $slug): JsonResponse
    {
        $site = AgencySite::withoutGlobalScope(TenantScope::class)->where('slug', $slug)->first();
        $theme = is_array($site?->theme_json) ? $site->theme_json : [];
        $published = ! array_key_exists('published', $theme) || ! empty($theme['published']);
        if (! $site || ! $site->status?->isPubliclyReachable() || ! $published) {
            abort(404);
        }
        $owner = User::query()->where('tenant_id', $site->tenant_id)->orderBy('id')->first();
        if (! $owner || ! $this->entitlement->canUseFeature($owner, 'public_listing', 'imobiliaria')) {
            abort(404);
        }
        $listings = CommercialListing::withoutGlobalScope(TenantScope::class)
            ->publiclyVisible()
            ->where('organization_id', $site->organization_id)
            ->get(['id', 'slug', 'title', 'city', 'neighborhood', 'asking_price_cents', 'area_m2', 'property_type']);

        return response()->json([
            'site' => [
                'slug' => $site->slug,
                'theme' => $site->theme_json,
            ],
            'listings' => $listings,
        ]);
    }
}
