<?php

use Illuminate\Support\Facades\Route;

Route::get('/{path?}', function (?string $path = null) {
    $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
    $destination = $frontendUrl.($path ? '/'.$path : '');

    if ($query = request()->getQueryString()) {
        $destination .= '?'.$query;
    }

    return redirect()->away($destination);
})->where('path', '^(?!api(?:/|$)|storage(?:/|$)).*');
