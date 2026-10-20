<?php

namespace App\Http\Middleware;

use App\Services\Ai\ProductEntitlement;
use App\Support\ProductContext;
use Closure;
use Illuminate\Http\Request;

class EnsureProductAccess
{
    public function __construct(
        private readonly ProductEntitlement $entitlement,
        private readonly ProductContext $context,
    ) {}

    public function handle(Request $request, Closure $next, string ...$products)
    {
        $user = $request->user();
        abort_if(! $user, 401);

        $required = array_values(array_filter(array_map(
            fn (string $slug) => $this->context->normalize($slug),
            $products,
        )));
        if ($required === []) {
            return $next($request);
        }

        foreach ($required as $slug) {
            if ($this->entitlement->canAccessProduct($user, $slug)) {
                return $next($request);
            }
        }

        abort(403, 'Este produto não está liberado para a conta.');
    }
}
