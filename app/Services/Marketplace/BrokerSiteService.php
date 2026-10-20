<?php

namespace App\Services\Marketplace;

use App\Enums\BrokerSiteStatus;
use App\Models\BrokerSite;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Services\Ai\ProductEntitlement;
use App\Support\BrokerThemeSanitizer;
use Illuminate\Support\Str;

class BrokerSiteService
{
    public function __construct(
        private readonly ProductEntitlement $entitlement,
        private readonly MarketplaceAudit $audit,
    ) {}

    public function forUser(User $user): BrokerSite
    {
        $site = BrokerSite::query()->where('user_id', $user->id)->first();
        if ($site) {
            return $this->syncStatus($user, $site);
        }

        $site = BrokerSite::query()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'slug' => $this->uniqueSlug(Str::slug($user->name) ?: 'corretor-'.$user->id),
            'status' => $this->statusFromEntitlement($user),
            'theme_json' => BrokerThemeSanitizer::clean(['display_name' => $user->name]),
        ]);
        $this->audit->record($user, 'broker_site.created', $site);

        return $site;
    }

    public function update(User $user, array $data): BrokerSite
    {
        $site = $this->forUser($user);
        $slug = isset($data['slug']) ? Str::slug((string) $data['slug']) : $site->slug;
        if ($slug === '') {
            $slug = $site->slug;
        }
        if ($slug !== $site->slug) {
            $exists = BrokerSite::withoutGlobalScope(TenantScope::class)
                ->where('slug', $slug)
                ->where('id', '!=', $site->id)
                ->exists();
            if ($exists) {
                abort(422, 'Este endereço de página já está em uso.');
            }
        }
        $site->fill([
            'slug' => $slug,
            'theme_json' => BrokerThemeSanitizer::clean($data['theme'] ?? $site->theme_json),
            'status' => $this->statusFromEntitlement($user),
        ])->save();
        $this->audit->record($user, 'broker_site.updated', $site);

        return $site->fresh();
    }

    public function publicBySlug(string $slug): ?BrokerSite
    {
        $slug = Str::slug($slug);
        if ($slug === '') {
            return null;
        }

        $site = BrokerSite::withoutGlobalScope(TenantScope::class)->where('slug', $slug)->first();
        if (! $site || ! $site->status->isPubliclyReachable()) {
            return null;
        }

        $owner = User::query()->find($site->user_id);
        if (! $owner || ! $this->entitlement->allows($owner, 'public_listing')) {
            return null;
        }

        return $site;
    }

    public function isLive(User $user, ?BrokerSite $site = null): bool
    {
        $site ??= BrokerSite::query()->where('user_id', $user->id)->first();
        if (! $site) {
            return false;
        }

        return $site->status->isPubliclyReachable() && $this->entitlement->allows($user, 'public_listing');
    }

    public function syncStatus(User $user, BrokerSite $site): BrokerSite
    {
        $next = $this->statusFromEntitlement($user);
        if ($site->status !== $next) {
            $site->status = $next;
            $site->save();
        }

        return $site;
    }

    private function statusFromEntitlement(User $user): BrokerSiteStatus
    {
        return $this->entitlement->allows($user, 'public_listing')
            ? BrokerSiteStatus::Active
            : BrokerSiteStatus::Suspended;
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $i = 1;
        while (BrokerSite::withoutGlobalScope(TenantScope::class)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
