<?php

namespace App\Services\Platform;

use App\Models\PlatformEvent;
use Illuminate\Http\Request;

class EventRecorder
{
    // Values are typed and selected by producers. Arbitrary payloads are never copied.
    private const KEYS = ['rule', 'outcome', 'target_type', 'target_id', 'before', 'after', 'reason_code', 'count', 'exception_type', 'device', 'bytes', 'feature', 'source', 'campaign', 'model', 'provider', 'tokens', 'latency_ms', 'tool', 'method', 'job_id', 'status', 'product'];

    public function record(string $category, string $event, array $metadata = [], ?Request $request = null, string $severity = 'info', ?string $dedupe = null): PlatformEvent
    {
        $request ??= request();
        $meta = array_intersect_key($metadata, array_flip(self::KEYS));
        foreach ($meta as $key => $value) {
            if (in_array($key, ['before', 'after'])) {
                $meta[$key] = is_array($value) ? array_intersect_key($value, array_flip(['ativo_flg', 'status', 'enabled', 'assigned_user_id', 'expires_at', 'severity'])) : null;
            } elseif (is_string($value)) {
                $meta[$key] = mb_substr(preg_replace('/[\x00-\x1F]/u', '', $value), 0, 180);
            } elseif (! is_scalar($value) && $value !== null) {
                unset($meta[$key]);
            }
        }
        $ip = (string) $request->ip();
        $mask = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? preg_replace('/\.\d+$/', '.x', $ip) : (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? implode(':', array_slice(explode(':', $ip), 0, 3)).'::/48' : null);
        $product = $request->header('X-Product-Context');
        if (! in_array($product, ['corretor', 'imobiliaria', 'house_flipper'], true)) {
            $product = null;
        }
        $route = $request->route()?->uri();
        $attributes = ['category' => $category, 'event' => $event, 'severity' => $severity, 'user_id' => $request->user()?->id, 'tenant_id' => $request->user()?->tenant_id, 'product' => $product,
            'request_id' => $request->attributes->get('platform_request_id'), 'trace_id' => $request->attributes->get('platform_request_id'),
            'ip_hash' => hash_hmac('sha256', $ip, (string) config('app.key')), 'ip_mask' => $mask,
            'route' => $route ? mb_substr($route, 0, 220) : '[unmatched]', 'method' => $request->method(), 'metadata' => $meta, 'created_at' => now()];

        return $dedupe ? PlatformEvent::firstOrCreate(['dedupe_key' => hash('sha256', $dedupe)], $attributes) : PlatformEvent::create($attributes);
    }

    public function audit(Request $request, string $action, string $targetType, int|string $target, array $before = [], array $after = [], string $reasonCode = 'operator_confirmed'): PlatformEvent
    {
        return $this->record('audit', $action, ['target_type' => $targetType, 'target_id' => (string) $target, 'before' => $before, 'after' => $after, 'reason_code' => $reasonCode], $request);
    }
}
