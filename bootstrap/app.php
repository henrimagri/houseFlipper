<?php

use App\Http\Middleware\ApiSecurityHeaders;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureProductAccess;
use App\Http\Middleware\EnsureVerifiedAccount;
use App\Http\Middleware\ObservePlatformRequest;
use App\Http\Middleware\ValidatePagination;
use App\Http\Middleware\ValidateResourceIdentifiers;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;

return Application::configure(basePath: dirname(__DIR__))

    ->withRouting(

        web: __DIR__.'/../routes/web.php',

        api: __DIR__.'/../routes/api.php',

        commands: __DIR__.'/../routes/console.php',

        health: '/up',

    )

    ->withMiddleware(function (Middleware $middleware): void {

        $trustedProxies = trim((string) env('TRUSTED_PROXIES', ''));

        if ($trustedProxies !== '') {

            $middleware->trustProxies(

                at: $trustedProxies === '*' ? '*' : preg_split('/\s*,\s*/', $trustedProxies, -1, PREG_SPLIT_NO_EMPTY),

                headers: Request::HEADER_X_FORWARDED_FOR

                    | Request::HEADER_X_FORWARDED_HOST

                    | Request::HEADER_X_FORWARDED_PORT

                    | Request::HEADER_X_FORWARDED_PROTO

                    | Request::HEADER_X_FORWARDED_PREFIX,

            );

        }

        $middleware->redirectGuestsTo(

            fn (Request $request): ?string => $request->is('api/*') ? null : '/login'

        );

        $middleware->api(prepend: [ApiSecurityHeaders::class]);

        $middleware->append(ObservePlatformRequest::class);

        $middleware->api(append: [ValidateResourceIdentifiers::class]);

        $middleware->api(append: [ValidatePagination::class, 'throttle:api']);

        $middleware->alias([

            'active-account' => EnsureActiveAccount::class,

            'verified-account' => EnsureVerifiedAccount::class,

            'product' => EnsureProductAccess::class,

            'platform-admin' => EnsurePlatformAdmin::class,

        ]);

        $middleware->prependToPriorityList([ThrottleRequests::class, SubstituteBindings::class, Authorize::class], EnsureVerifiedAccount::class);

        $middleware->prependToPriorityList(EnsureVerifiedAccount::class, EnsureActiveAccount::class);

        $middleware->prependToPriorityList([ThrottleRequests::class, SubstituteBindings::class, Authorize::class], EnsurePlatformAdmin::class);

    })

    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->shouldRenderJsonWhen(

            fn (Request $request, Throwable $exception): bool => $request->is('api/*') || $request->expectsJson()

        );

        $exceptions->respond(function ($response) {

            if (! request()->is('api/*')) {

                return $response;

            }

            if ($response instanceof JsonResponse) {

                $payload = (array) $response->getData(true);

                unset($payload['exception'], $payload['file'], $payload['line'], $payload['trace']);

                if ($response->getStatusCode() >= 500) {

                    $payload = ['message' => 'Server Error'];

                }

                $response->setData($payload);

            }

            return ApiSecurityHeaders::apply($response);

        });

    })->create();
