<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class PlatformAdminController extends Controller
{
    public function overview(): JsonResponse
    {
        return response()->json([
            'tenants' => Tenant::query()->count(),
            'users' => User::query()->count(),
            'subscriptions' => TenantProductSubscription::query()
                ->selectRaw('product_slug, status, count(*) as total')
                ->groupBy('product_slug', 'status')
                ->get(),
            'platform_admins' => PlatformAdmin::query()->count(),
            'note' => 'Indicadores consolidados sem PII. Este ambiente não é um produto comercial.',
        ]);
    }
}
