<?php

namespace App\Services\Ai;

use App\Models\TenantProductSubscription;
use App\Models\User;

class ProductEntitlement
{
    public function productsFor(User $user): array
    {
        $subscribed = TenantProductSubscription::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('active', true)
            ->pluck('product_slug')
            ->all();

        if ($subscribed === []) {
            $subscribed = [config('products.default', 'house_flipper')];
        }

        return array_values(array_unique($subscribed));
    }

    public function allows(User $user, string $feature, ?string $product = null): bool
    {
        $products = $product ? [$product] : $this->productsFor($user);
        $catalog = config('products.catalog', []);

        foreach ($products as $slug) {
            $features = $catalog[$slug]['features'] ?? [];
            if (in_array($feature, $features, true)) {
                return true;
            }
        }

        return false;
    }

    public function assert(User $user, string $feature, ?string $product = null): void
    {
        if (! $this->allows($user, $feature, $product)) {
            abort(403, 'Este recurso não está incluído no produto contratado.');
        }
    }
}
