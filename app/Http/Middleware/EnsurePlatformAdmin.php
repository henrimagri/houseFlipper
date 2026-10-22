<?php

namespace App\Http\Middleware;

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

        return $next($request);
    }
}
