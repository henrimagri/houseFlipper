<?php

namespace App\Services\Marketplace;

use App\Enums\BrokerSiteStatus;
use App\Enums\SubscriptionStatus;
use App\Models\BrokerSite;
use App\Models\SubscriptionEvent;
use App\Models\TenantProductSubscription;
use App\Models\User;
use App\Services\Ai\ProductEntitlement;
use App\Services\Workspace\OrganizationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillingService
{
    public function __construct(
        private readonly ProductEntitlement $entitlement,
        private readonly MarketplaceAudit $audit,
        private readonly BrokerSiteService $sites,
        private readonly OrganizationService $organizations,
    ) {}

    public function snapshot(User $user): array
    {
        $products = $this->entitlement->productsFor($user);
        $rows = TenantProductSubscription::query()
            ->where('tenant_id', $user->tenant_id)
            ->get()
            ->map(fn (TenantProductSubscription $row) => [
                'product_slug' => $row->product_slug,
                'label' => config('products.catalog.'.$row->product_slug.'.label', $row->product_slug),
                'active' => $row->active,
                'status' => $row->status?->value ?? 'active',
                'trial_ends_at' => $row->trial_ends_at?->toIso8601String(),
                'grace_until' => $row->grace_until?->toIso8601String(),
            ])
            ->all();

        return [
            'products' => $products,
            'features' => $this->entitlement->featuresFor($user),
            'catalog' => $this->entitlement->catalogPayload($user),
            'subscriptions' => $rows,
            'gateways' => [
                'mercado_pago' => filled(config('services.mercado_pago.access_token')) ? 'configured' : 'not_wired',
                'stripe' => filled(config('services.stripe.secret')) ? 'configured' : 'not_wired',
            ],
            'checkout' => 'not_wired',
            'public_listing_live' => $this->sites->isLive($user),
        ];
    }

    public function startTrial(User $user, string $slug): TenantProductSubscription
    {
        $catalog = config('products.catalog', []);
        if (! isset($catalog[$slug])) {
            throw ValidationException::withMessages(['product' => 'Plano desconhecido.']);
        }

        return DB::transaction(function () use ($user, $slug) {
            $row = TenantProductSubscription::query()->firstOrNew([
                'tenant_id' => $user->tenant_id,
                'product_slug' => $slug,
            ]);
            if ($row->exists && $this->entitlement->subscriptionGrants($row)) {
                return $row;
            }
            if ($row->exists && $row->trial_starts_at) {
                throw ValidationException::withMessages(['product' => 'O teste deste produto já foi utilizado nesta conta.']);
            }
            $days = (int) (config('products.catalog.'.$slug.'.trial_days') ?? 7);
            $row->fill([
                'active' => true,
                'status' => SubscriptionStatus::Trial,
                'plan_slug' => $slug.'_trial',
                'trial_starts_at' => now(),
                'trial_ends_at' => now()->addDays($days),
                'grace_until' => null,
                'payment_provider' => 'local_trial',
            ]);
            $row->save();
            SubscriptionEvent::query()->create([
                'tenant_product_subscription_id' => $row->id,
                'event_type' => 'trial_started',
                'payload' => ['product' => $slug, 'days' => $days],
            ]);
            if ($slug === 'imobiliaria') {
                $this->organizations->ensureForTenant($user);
            }
            if (in_array($slug, ['corretor', 'imobiliaria'], true)) {
                $this->sites->syncStatus($user, $this->sites->forUser($user));
            }
            $this->audit->record($user, 'billing.trial_started', $row, ['product' => $slug]);

            return $row;
        });
    }

    public function cancel(User $user, string $slug): TenantProductSubscription
    {
        $row = TenantProductSubscription::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('product_slug', $slug)
            ->firstOrFail();
        $row->fill([
            'active' => false,
            'status' => SubscriptionStatus::Cancelled,
        ])->save();
        $site = BrokerSite::query()->where('user_id', $user->id)->first();
        if ($site && in_array($slug, ['corretor', 'imobiliaria'], true)) {
            $site->status = BrokerSiteStatus::Cancelled;
            $site->save();
        }
        $this->audit->record($user, 'billing.cancelled', $row, ['product' => $slug]);

        return $row;
    }

    /** @deprecated Native adapters require Request headers and timestamp verification. */
    public function handleWebhook(string $provider, string $rawPayload, ?string $signature): array
    {
        abort(503, 'Use o callback nativo autenticado; o contrato HMAC legado foi desabilitado.');
    }
}
