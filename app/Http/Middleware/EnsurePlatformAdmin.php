<?php

namespace App\Http\Middleware;

use App\Services\Platform\EventRecorder;
use App\Services\Platform\PlatformMfa;
use Closure;
use Illuminate\Http\Request;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        abort_if(! $user, 401);
        abort_unless($user->hasVerifiedEmail(), 403, 'Confirme seu e-mail para acessar a central.');
        abort_unless($user->isPlatformAdmin(), 403, 'Acesso restrito à operação da plataforma.');

        if (! $request->routeIs('platform.mfa.*') && ! app(PlatformMfa::class)->verified($request)) {
            return response()->json(['code' => 'platform_mfa_required', 'message' => 'Valide o segundo fator para acessar a central.'], 403);
        }

        if ($request->isMethod('GET')) {
            app(EventRecorder::class)->audit($request, 'admin.resource.viewed', 'route', $request->route()?->uri() ?? 'unknown');
        }

        return $next($request);
    }
}
