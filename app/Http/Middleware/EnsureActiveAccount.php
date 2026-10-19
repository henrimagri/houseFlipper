<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        abort_if($user && (! $user->ativo_flg || $user->deletado_flg), 401, 'Conta indisponível.');
        if ($user?->tenant_id) {
            $tenant = $user->tenant;
            abort_if(! $tenant || ! $tenant->ativo_flg || $tenant->deletado_flg, 401, 'Conta indisponível.');
        }

        return $next($request);
    }
}
