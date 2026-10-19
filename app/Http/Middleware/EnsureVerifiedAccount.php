<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureVerifiedAccount
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->requires_email_verification && ! $request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Confirme seu e-mail para acessar esta funcionalidade.', 'code' => 'email_verification_required'], 403);
        }

        return $next($request);
    }
}
