<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiResponseFeedback;
use App\Models\AiSearchSession;
use App\Models\AiToolRun;
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
            'ai' => [
                'sessions_30d' => AiSearchSession::query()->where('created_at', '>=', now()->subDays(30))->count(),
                'tool_runs_30d' => AiToolRun::query()->where('created_at', '>=', now()->subDays(30))->count(),
                'tool_failures_30d' => AiToolRun::query()->where('created_at', '>=', now()->subDays(30))->where('status', 'failed')->count(),
                'average_latency_ms_30d' => (int) AiToolRun::query()->where('created_at', '>=', now()->subDays(30))->avg('latency_ms'),
                'helpful_30d' => AiResponseFeedback::query()->where('created_at', '>=', now()->subDays(30))->where('rating', 'helpful')->count(),
                'not_helpful_30d' => AiResponseFeedback::query()->where('created_at', '>=', now()->subDays(30))->where('rating', 'not_helpful')->count(),
            ],
            'note' => 'Indicadores consolidados sem PII. Este ambiente não é um produto comercial.',
        ]);
    }
}
