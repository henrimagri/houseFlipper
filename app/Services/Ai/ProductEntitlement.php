<?php

namespace App\Services\Ai;

use App\Enums\SubscriptionStatus;
use App\Models\TenantProductSubscription;
use App\Models\User;
use App\Support\ProductContext;

class ProductEntitlement
{
    public function __construct(private readonly ProductContext $context) {}

    public function productsFor(User $user): array
    {
        return array_values(array_unique(
            $this->grantingSubscriptions($user)->pluck('product_slug')->all()
        ));
    }

    public function canAccessProduct(User $user, string $product): bool
    {
        $slug = $this->context->normalize($product);
        if (! $slug || ! isset(config('products.catalog')[$slug])) {
            return false;
        }

        return in_array($slug, $this->productsFor($user), true);
    }

    public function canUseFeature(User $user, string $feature, ?string $product = null): bool
    {
        $product = $this->context->normalize($product) ?? $this->context->current();
        if ($product) {
            if (! $this->canAccessProduct($user, $product)) {
                return false;
            }

            return in_array($feature, config('products.catalog.'.$product.'.features', []), true);
        }

        foreach ($this->productsFor($user) as $slug) {
            if (in_array($feature, config('products.catalog.'.$slug.'.features', []), true)) {
                return true;
            }
        }

        return false;
    }

    public function allows(User $user, string $feature, ?string $product = null): bool
    {
        return $this->canUseFeature($user, $feature, $product);
    }

    public function assert(User $user, string $feature, ?string $product = null): void
    {
        if (! $this->canUseFeature($user, $feature, $product)) {
            abort(403, 'Este recurso não está incluído no produto contratado.');
        }
    }

    public function assertProduct(User $user, string $product): void
    {
        if (! $this->canAccessProduct($user, $product)) {
            abort(403, 'Este produto não está liberado para a conta.');
        }
    }

    public function featuresFor(User $user, ?string $product = null): array
    {
        $product = $this->context->normalize($product) ?? $this->context->current();
        $slugs = $product ? [$product] : $this->productsFor($user);
        $features = [];
        foreach ($slugs as $slug) {
            if ($product && ! $this->canAccessProduct($user, $slug)) {
                continue;
            }
            foreach (config('products.catalog.'.$slug.'.features', []) as $feature) {
                $features[$feature] = true;
            }
        }

        return array_keys($features);
    }

    public function mapModesFor(User $user, ?string $product = null): array
    {
        $product = $this->context->normalize($product) ?? $this->context->current();
        $allowed = [];
        $slugs = $product ? [$product] : $this->productsFor($user);
        foreach ($slugs as $slug) {
            if (! $this->canAccessProduct($user, $slug)) {
                continue;
            }
            foreach (config('products.catalog.'.$slug.'.map_modes', []) as $mode) {
                $allowed[$mode] = true;
            }
        }

        return array_keys($allowed);
    }

    public function navigationFor(User $user, string $product): array
    {
        $slug = $this->context->normalize($product);
        if (! $slug || ! $this->canAccessProduct($user, $slug)) {
            return [];
        }
        $items = [];
        foreach (config('products.catalog.'.$slug.'.navigation', []) as $item) {
            $feature = $item['feature'] ?? null;
            if ($feature && ! $this->canUseFeature($user, $feature, $slug)) {
                continue;
            }
            $items[] = $item;
        }

        return $items;
    }

    public function getProductSubscription(User $user, string $product): ?TenantProductSubscription
    {
        $slug = $this->context->normalize($product);
        if (! $slug || ! $user->tenant_id) {
            return null;
        }

        return TenantProductSubscription::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('product_slug', $slug)
            ->first();
    }

    public function publicCatalog(): array
    {
        $items = [];
        foreach (config('products.catalog', []) as $slug => $product) {
            if (! ($product['enabled'] ?? true)) {
                continue;
            }
            $items[] = [
                'slug' => $slug,
                'code' => $product['code'],
                'label' => $product['label'],
                'description' => $product['description'],
                'path' => $product['path'],
                'trial_days' => (int) ($product['trial_days'] ?? 0),
                'features' => array_values($product['features'] ?? []),
                'plans' => array_values(array_map(function (array $plan, string $planSlug) {
                    return [
                        'slug' => $planSlug,
                        'name' => $plan['name'],
                        'billing_interval' => $plan['billing_interval'],
                        'price' => $plan['price'],
                        'currency' => $plan['currency'],
                        'enabled' => (bool) ($plan['enabled'] ?? true),
                        'checkout' => $plan['checkout'] ?? ($plan['price'] > 0 ? 'not_wired' : 'trial'),
                    ];
                }, $product['plans'] ?? [], array_keys($product['plans'] ?? []))),
            ];
        }

        return [
            'products' => $items,
            'billing_cycles' => config('products.billing_cycles', []),
            'checkout' => 'not_wired',
            'gateways' => [
                'mercado_pago' => filled(config('services.mercado_pago.access_token')) ? 'configured' : 'not_wired',
                'stripe' => filled(config('services.stripe.secret')) ? 'configured' : 'not_wired',
            ],
        ];
    }

    public function catalogPayload(User $user): array
    {
        $entitled = $this->productsFor($user);
        $items = [];
        foreach (config('products.catalog', []) as $slug => $product) {
            $subscription = $this->getProductSubscription($user, $slug);
            $items[] = [
                'slug' => $slug,
                'code' => $product['code'],
                'label' => $product['label'],
                'description' => $product['description'],
                'path' => $product['path'],
                'enabled' => (bool) ($product['enabled'] ?? true),
                'entitled' => in_array($slug, $entitled, true),
                'subscription' => $subscription ? [
                    'status' => $subscription->status?->value ?? 'active',
                    'active' => (bool) $subscription->active,
                    'plan_slug' => $subscription->plan_slug,
                    'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
                    'grace_until' => $subscription->grace_until?->toIso8601String(),
                    'current_period_end' => $subscription->current_period_end?->toIso8601String(),
                ] : null,
                'plans' => array_values(array_map(function (array $plan, string $planSlug) {
                    return [
                        'slug' => $planSlug,
                        'name' => $plan['name'],
                        'billing_interval' => $plan['billing_interval'],
                        'price' => $plan['price'],
                        'currency' => $plan['currency'],
                        'checkout' => $plan['checkout'] ?? ($plan['price'] > 0 ? 'not_wired' : 'trial'),
                    ];
                }, $product['plans'] ?? [], array_keys($product['plans'] ?? []))),
            ];
        }

        return $items;
    }

    public function sessionPayload(User $user): array
    {
        $products = $this->productsFor($user);
        $navigation = [];
        foreach ($products as $slug) {
            $navigation[$slug] = $this->navigationFor($user, $slug);
        }

        return [
            'products' => $products,
            'features' => $this->featuresFor($user),
            'catalog' => $this->catalogPayload($user),
            'navigation' => $navigation,
            'last_product_slug' => $this->context->normalize($user->last_product_slug),
            'is_platform_admin' => $user->isPlatformAdmin(),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, TenantProductSubscription>
     */
    public function grantingSubscriptions(User $user)
    {
        if (! $user->tenant_id) {
            return collect();
        }

        return TenantProductSubscription::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('active', true)
            ->get()
            ->filter(fn (TenantProductSubscription $row) => $this->subscriptionGrants($row));
    }

    public function subscriptionGrants(TenantProductSubscription $row): bool
    {
        $status = $row->status instanceof SubscriptionStatus
            ? $row->status
            : SubscriptionStatus::tryFrom((string) $row->status) ?? SubscriptionStatus::Active;

        if (! $status->grantsAccess()) {
            return false;
        }

        if ($status === SubscriptionStatus::Trial && $row->trial_ends_at && $row->trial_ends_at->isPast()) {
            return false;
        }

        if ($status === SubscriptionStatus::Grace && $row->grace_until && $row->grace_until->isPast()) {
            return false;
        }

        return true;
    }
}
