<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

// Local, unauthenticated timing probe. Never use as a public application router.
if (PHP_SAPI !== 'cli-server' || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' || ! in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true) || parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) !== '/api/platform/overview' || isset($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(404);
    exit;
}
$entry = hrtime(true);
require __DIR__.'/../vendor/autoload.php';
$autoloadMs = (hrtime(true) - $entry) / 1e6;
$definitionStart = hrtime(true);
$app = require __DIR__.'/../bootstrap/app.php';
$definitionMs = (hrtime(true) - $definitionStart) / 1e6;
$request = Request::capture();
$app->instance('request', $request);
$bootstrapStart = hrtime(true);
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
$bootstrapMs = (hrtime(true) - $bootstrapStart) / 1e6;
if (! $app->environment('local')) {
    http_response_code(503);
    exit;
}
config(['observability.diagnostics' => true]);
$handleStart = hrtime(true);
$app->handleRequest($request);
$r = $app['request'];
$report = ['checked_at' => gmdate('c'), 'scope' => 'local_unauthenticated_probe_not_browser_qa', 'status' => http_response_code(), 'autoload_ms' => round($autoloadMs, 2), 'application_definition_ms' => round($definitionMs, 2), 'kernel_bootstrap_ms' => round($bootstrapMs, 2), 'handle_and_terminate_ms' => round((hrtime(true) - $handleStart) / 1e6, 2), 'entry_total_ms' => round((hrtime(true) - $entry) / 1e6, 2), 'database_count' => $r->attributes->get('platform_db_count', 0), 'database_ms' => round($r->attributes->get('platform_db_ms', 0), 2), 'php_peak_bytes' => memory_get_peak_usage(true), 'included_files' => count(get_included_files())];
file_put_contents(__DIR__.'/../storage/logs/platform-request-diagnostic.json', json_encode($report, JSON_PRETTY_PRINT));
