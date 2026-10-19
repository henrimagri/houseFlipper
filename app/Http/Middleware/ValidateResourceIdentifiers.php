<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ValidateResourceIdentifiers
{
    public function handle(Request $request, Closure $next)
    {
        foreach (['id', 'propriedade', 'projeto', 'despesa', 'venda', 'tarefa', 'tokenId'] as $parameter) {
            $value = $request->route($parameter);
            if ($value === null || $value instanceof Model) {
                continue;
            }
            abort_unless(is_scalar($value) && filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false, 404);
        }

        return $next($request);
    }
}
