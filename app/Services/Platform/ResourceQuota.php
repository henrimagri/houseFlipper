<?php

namespace App\Services\Platform;

use App\Models\TenantProductSubscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ResourceQuota
{
    public function admit(User $user, string $product, string $resource, string $operation): void
    {
        abort_unless(in_array($resource, ['ai_requests', 'reconstructions'], true) && Str::isUuid($operation), 422);
        // Keeps entitlement/ownership checks in the calling service; this adds a tenant budget.
        abort_unless($user->tenant_id && in_array($product, ['corretor', 'imobiliaria', 'house_flipper'], true), 403);
        DB::transaction(function () use ($user, $product, $resource, $operation) {
            DB::table('tenants')->where('id', $user->tenant_id)->lockForUpdate()->first();
            if (DB::table('platform_resource_admissions')->where(['tenant_id' => $user->tenant_id, 'product' => $product, 'resource' => $resource, 'operation_id' => $operation])->exists()) {
                return;
            }
            $scope = ['tenant_id' => $user->tenant_id, 'product' => $product, 'resource' => $resource, 'period' => now()->format('Y-m')];
            DB::table('platform_resource_usage')->insertOrIgnore($scope + ['used' => 0]);
            $plan = TenantProductSubscription::where('tenant_id', $user->tenant_id)->where('product_slug', $product)->value('plan_slug');
            $limit = config('platform_resources.plans.'.$plan.'.'.$resource);
            abort_if($limit !== null && (! is_int($limit) || $limit < 0), 503, 'Limite do plano inválido.');
            $query = DB::table('platform_resource_usage')->where($scope);
            if ($limit !== null) {
                $query->where('used', '<', $limit);
            }
            abort_unless($query->increment('used') === 1, 429, 'Limite mensal de solicitações atingido.');
            DB::table('platform_resource_admissions')->insert(['tenant_id' => $user->tenant_id, 'product' => $product, 'resource' => $resource, 'operation_id' => $operation, 'created_at' => now()]);
        });
    }

    public function snapshot(int $tenant, string $product): array
    {
        $plan = TenantProductSubscription::where('tenant_id', $tenant)->where('product_slug', $product)->value('plan_slug');
        $usage = DB::table('platform_resource_usage')->where('tenant_id', $tenant)->where('product', $product)->where('period', now()->format('Y-m'))->pluck('used', 'resource');
        $rows = [];
        foreach (['ai_requests', 'tokens', 'images', 'reconstructions', 'storage_bytes', 'concurrent_jobs'] as $resource) {
            $rows[] = ['resource' => $resource, 'used' => $usage[$resource] ?? null, 'limit' => config('platform_resources.plans.'.$plan.'.'.$resource), 'enforcement_supported' => in_array($resource, ['ai_requests', 'reconstructions'], true), 'enforced' => config('platform_resources.plans.'.$plan.'.'.$resource) !== null && in_array($resource, ['ai_requests', 'reconstructions'], true), 'period' => now()->format('Y-m')];
        }

        return ['tenant_id' => $tenant, 'product' => $product, 'plan' => $plan, 'data' => $rows, 'method' => 'Solicitações admitidas, inclusive tentativas que falham depois da admissão; não é cobrança nem quantidade concluída. Recursos sem medição permanecem nulos.'];
    }
}
