<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\SaasLead;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\User;
use App\Services\Ai\ProductEntitlement;
use App\Services\Marketplace\BillingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VerifyProductSuiteCommand extends Command
{
    protected $signature = 'suite:verify';

    protected $description = 'Verifica isolamento de produtos, entitlements e bloqueios de API';

    public function handle(ProductEntitlement $entitlement): int
    {
        $failures = 0;
        $corretor = $this->persona('verify-corretor', 'corretor', true);
        $imob = $this->persona('verify-imob', 'imobiliaria', true);
        $flip = $this->persona('verify-flip', 'house_flipper', true);
        $combo = $this->persona('verify-combo', 'corretor', true);
        TenantProductSubscription::query()->updateOrCreate(
            ['tenant_id' => $combo->tenant_id, 'product_slug' => 'house_flipper'],
            ['active' => true, 'status' => SubscriptionStatus::Active],
        );
        $expired = $this->persona('verify-expired', 'corretor', false);

        $failures += $this->expect('A acessa corretor', $entitlement->canAccessProduct($corretor, 'corretor') === true);
        $failures += $this->expect('A bloqueia imobiliaria', $entitlement->canAccessProduct($corretor, 'imobiliaria') === false);
        $failures += $this->expect('A bloqueia house_flipper', $entitlement->canAccessProduct($corretor, 'house_flipper') === false);
        $failures += $this->expect('A usa capture', $entitlement->canUseFeature($corretor, 'capture', 'corretor') === true);
        $failures += $this->expect('A não usa reforma', $entitlement->canUseFeature($corretor, 'renovation_simulation') === false);

        $failures += $this->expect('B acessa imobiliaria', $entitlement->canAccessProduct($imob, 'imobiliaria') === true);
        $failures += $this->expect('B não ganha corretor individual', $entitlement->canAccessProduct($imob, 'corretor') === false);
        $failures += $this->expect('B não acessa flipper', $entitlement->canAccessProduct($imob, 'house_flipper') === false);

        $failures += $this->expect('C acessa flipper', $entitlement->canAccessProduct($flip, 'house_flipper') === true);
        $failures += $this->expect('C bloqueia public_listing', $entitlement->canUseFeature($flip, 'public_listing') === false);
        $failures += $this->expect('C usa simulador', $entitlement->canUseFeature($flip, 'renovation_simulation') === true);

        $failures += $this->expect('D acessa dois produtos', $entitlement->canAccessProduct($combo, 'corretor') && $entitlement->canAccessProduct($combo, 'house_flipper'));
        $failures += $this->expect('D não ganha imobiliaria', $entitlement->canAccessProduct($combo, 'imobiliaria') === false);

        $failures += $this->expect('E expirado sem acesso', $entitlement->canAccessProduct($expired, 'corretor') === false);

        $full = $this->persona('verify-full', 'corretor', true);
        TenantProductSubscription::query()->updateOrCreate(
            ['tenant_id' => $full->tenant_id, 'product_slug' => 'imobiliaria'],
            ['active' => true, 'status' => SubscriptionStatus::Active],
        );
        TenantProductSubscription::query()->updateOrCreate(
            ['tenant_id' => $full->tenant_id, 'product_slug' => 'house_flipper'],
            ['active' => true, 'status' => SubscriptionStatus::Active],
        );
        $full = $full->fresh();
        $failures += $this->expect('F acessa os três produtos', $entitlement->canAccessProduct($full, 'corretor') && $entitlement->canAccessProduct($full, 'imobiliaria') && $entitlement->canAccessProduct($full, 'house_flipper'));
        $failures += $this->expect('A não vê menu imobiliária', $entitlement->navigationFor($corretor, 'imobiliaria') === []);
        $failures += $this->expect('C não vê carteira comercial', $entitlement->canUseFeature($flip, 'commercial_wallet') === false);

        $public = $entitlement->publicCatalog();
        $failures += $this->expect('catálogo público tem 3 produtos', count($public['products']) === 3);
        $failures += $this->expect('checkout público not_wired', ($public['checkout'] ?? '') === 'not_wired');

        $billing = app(BillingService::class);
        $trialOnceFailed = false;
        try {
            $billing->startTrial($expired, 'corretor');
        } catch (ValidationException) {
            $trialOnceFailed = true;
        }
        $failures += $this->expect('trial não reinicia após uso', $trialOnceFailed);

        $second = $billing->startTrial($corretor, 'house_flipper');
        $corretor = $corretor->fresh();
        $failures += $this->expect('segundo produto não remove o primeiro', $entitlement->canAccessProduct($corretor, 'corretor') && $second->product_slug === 'house_flipper');
        $failures += $this->expect('segundo produto não libera imobiliária', $entitlement->canAccessProduct($corretor, 'imobiliaria') === false);

        $lead = SaasLead::query()->create([
            'name' => 'Lead Verify',
            'email' => 'lead-verify@houseflipper.local',
            'intent' => 'demo',
            'product_slug' => 'imobiliaria',
            'status' => 'novo',
            'consent' => true,
        ]);
        $failures += $this->expect('lead comercial persiste', SaasLead::query()->whereKey($lead->id)->exists());

        if ($failures > 0) {
            $this->error($failures.' checagens falharam.');

            return self::FAILURE;
        }
        $this->info('suite:verify PASS');

        return self::SUCCESS;
    }

    private function persona(string $slug, string $product, bool $active): User
    {
        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => $slug],
            ['nome' => $slug, 'email_contato' => $slug.'@houseflipper.local'],
        );
        $user = User::query()->firstOrCreate(
            ['email' => $slug.'@houseflipper.local'],
            [
                'tenant_id' => $tenant->id,
                'name' => $slug,
                'password' => Hash::make(Str::random(24)),
            ],
        );
        // The verifier is intentionally repeatable. Remove subscriptions left by a
        // previous run before recreating the persona's single expected entitlement.
        TenantProductSubscription::query()
            ->where('tenant_id', $tenant->id)
            ->where('product_slug', '!=', $product)
            ->delete();
        TenantProductSubscription::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'product_slug' => $product],
            [
                'active' => $active,
                'status' => $active ? SubscriptionStatus::Trial : SubscriptionStatus::Cancelled,
                'trial_starts_at' => $active ? now() : now()->subDays(20),
                'trial_ends_at' => $active ? now()->addDays(7) : now()->subDay(),
            ],
        );

        return $user->fresh();
    }

    private function expect(string $label, bool $ok): int
    {
        $this->line(($ok ? 'PASS' : 'FAIL').' '.$label);

        return $ok ? 0 : 1;
    }
}
