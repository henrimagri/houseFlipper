<?php

namespace App\Http\Middleware;

use App\Services\Platform\EventRecorder;
use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ObservePlatformRequest
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->is('api/*')) {
            return $next($request);
        }
        $id = (string) Str::uuid();
        $request->attributes->set('platform_request_id', $id);
        $start = hrtime(true);
        $exceptionType = null;
        try {
            $ipHash=hash_hmac('sha256',(string)$request->ip(),(string)config('app.key'));
            $blocked=false;
            try { $blocked=\App\Models\PlatformControl::where('kind','ip')->where('key',$ipHash)->where('expires_at','>',now())->where('value->status','blocked')->exists(); } catch (\Throwable) {}
            $response = $blocked ? response()->json(['message'=>'Acesso temporariamente restrito.'],403) : $next($request);
        } catch (\Throwable $e) {
            $exceptionType = class_basename($e);
            app(ExceptionHandler::class)->report($e);
            $response = app(ExceptionHandler::class)->render($request, $e);
        }
        $response->headers->set('X-Request-ID', $id);
        if (! config('observability.enabled')) {
            return $response;
        }
        try {
            $rec = app(EventRecorder::class);
            $status = $response->getStatusCode();
            foreach(app(\App\Services\Platform\SecuritySignals::class)->inspect($request) as $rule) $rec->record('security','security.signal.detected',['rule'=>$rule,'outcome'=>'observed'],$request,'medium');
            $row = $rec->record('api', $status >= 400 ? 'api.request.failed' : 'api.request.completed', ['exception_type' => $status >= 500 ? $exceptionType : null], $request, $status >= 500 ? 'high' : 'info');
            $row->update(['status' => $status, 'duration_ms' => min(4294967295, (int) round((hrtime(true) - $start) / 1e6)),
                'request_bytes' => is_numeric($request->header('Content-Length')) ? min(1000000000, max(0, (int) $request->header('Content-Length'))) : null,
                'response_bytes' => $response instanceof StreamedResponse ? null : strlen((string) $response->getContent())]);
            if (in_array($status, [401, 403, 429], true)) {
                $rec->record('security', $status === 429 ? 'auth.rate_limited' : 'security.access.denied', ['outcome' => 'blocked', 'rule' => 'http_'.$status], $request, $status === 429 ? 'medium' : 'low')->update(['status' => $status]);
            }
            if ($request->is('api/auth/*')) {
                $action = match (true) {
                    $request->is('api/auth/login') => 'login', $request->is('api/auth/register') => 'registration', $request->is('api/auth/email/verify/*') => 'email_verification', $request->is('api/auth/password/*') => 'password_request', $request->is('api/auth/logout*') => 'logout', default => null
                };
                if ($action) {
                    $rec->record('auth', 'auth.'.$action.'.'.($status < 400 ? 'success' : 'failed'), ['outcome' => $status < 400 ? 'success' : 'failed'], $request);
                }
            }
            if ($request->is('api/platform/*') && $request->isMethod('GET') && $status < 400) {
                $rec->audit($request, 'admin.resource.viewed', 'route', $request->route()?->uri() ?? 'unknown');
            }
        } catch (\Throwable) {
            Log::warning('Platform telemetry write failed; request preserved.');
        }

        return $response;
    }
}
