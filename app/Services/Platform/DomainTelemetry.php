<?php

namespace App\Services\Platform;

use App\Models\AiToolRun;
use App\Models\PropertyProcessingJob;
use App\Models\TenantProductSubscription;
use Illuminate\Support\Facades\Log;

class DomainTelemetry
{
    public static function boot(): void
    {
        AiToolRun::created(function ($m) {
            self::record($m, 'analytics', 'ai.tool.'.$m->status, ['tool' => $m->tool, 'provider' => $m->provider, 'model' => $m->model, 'latency_ms' => $m->latency_ms], 'tool-'.$m->id);
        });
        PropertyProcessingJob::saved(function ($m) {
            if ($m->wasRecentlyCreated || $m->wasChanged('status')) {
                self::record($m, 'analytics', '3d.job.'.$m->status, ['job_id' => (string) $m->id, 'status' => $m->status], 'job-'.$m->id.'-'.$m->status);
            }
        });
        TenantProductSubscription::saved(function ($m) {
            if ($m->wasRecentlyCreated || $m->wasChanged('status')) {
                self::record($m, 'analytics', 'billing.subscription.'.($m->status?->value ?? 'unknown'), ['status' => $m->status?->value], 'subscription-'.$m->id.'-'.$m->updated_at?->format('YmdHis.u').'-'.$m->status?->value);
            }
        });
    }

    private static function record($model, string $category, string $event, array $meta, string $dedupe): void
    {
        try {
            $row = app(EventRecorder::class)->record($category, $event, $meta, dedupe: $dedupe);
            $row->update(['user_id' => $model->user_id ?? request()->user()?->id, 'tenant_id' => $model->tenant_id, 'product' => $model->product_slug ?? ($model instanceof PropertyProcessingJob ? 'house_flipper' : null)]);
        } catch (\Throwable) {
            Log::warning('Domain telemetry unavailable.');
        }
    }
}
