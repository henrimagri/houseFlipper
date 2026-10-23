<?php

// This program NEVER accepts a database argument and NEVER reads a production DB.
// Set isolation before Laravel/Dotenv/config can load; all generated fixtures die at process exit.
foreach ([
    'APP_ENV' => 'testing', 'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
    'APP_CONFIG_CACHE' => __DIR__.'/../bootstrap/cache/platform-benchmark-config-does-not-exist.php',
    'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
    'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'array', 'LOG_CHANNEL' => 'stderr', 'BCRYPT_ROUNDS' => '4',
    'AI_PROVIDER' => 'deterministic', 'AI_VECTOR_ENABLED' => 'false',
] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
ini_set('memory_limit', '384M');
set_time_limit(180);
require __DIR__.'/../vendor/autoload.php';

use App\Http\Middleware\ObservePlatformRequest;
use App\Models\PlatformAdmin;
use App\Models\PlatformMfaCredential;
use App\Models\User;
use App\Services\Platform\EventRecorder;
use App\Services\Platform\PlatformQueries;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
if (! $app->environment('testing') || $app->configurationIsCached() || config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:' || DB::connection()->getDatabaseName() !== ':memory:') {
    throw new RuntimeException('Refusing benchmark outside isolated SQLite memory.');
}

$started = hrtime(true);
$stage = 'migrations';
$checkpoint = function (string $label) use (&$stage, $started): void {
    $stage = $label;
    fwrite(STDERR, json_encode(['benchmark_stage' => $label, 'elapsed_ms' => round((hrtime(true) - $started) / 1e6, 3)]).PHP_EOL);
};
register_shutdown_function(function () use (&$stage, $started) {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        fwrite(STDERR, json_encode(['benchmark_complete' => false, 'failed_stage' => $stage, 'elapsed_ms' => round((hrtime(true) - $started) / 1e6, 3), 'peak_php_allocated_mb' => round(memory_get_peak_usage(true) / 1048576, 2)]).PHP_EOL);
    }
});
Artisan::call('migrate', ['--force' => true]);
$checkpoint('seed_users_and_events');
$now = now()->format('Y-m-d H:i:s');
$passwordHash = password_hash(bin2hex(random_bytes(24)), PASSWORD_BCRYPT, ['cost' => 4]);
DB::transaction(function () use ($now, $passwordHash) {
    for ($offset = 0; $offset < 10000; $offset += 500) {
        $rows = [];
        for ($i = $offset + 1; $i <= $offset + 500; $i++) {
            $rows[] = ['name' => 'Synthetic volume '.$i, 'email' => 'volume-'.$i.'@example.test', 'email_verified_at' => $now, 'password' => $passwordHash, 'created_at' => $now, 'updated_at' => $now];
        }
        DB::table('users')->insert($rows);
    }
    for ($offset = 0; $offset < 100000; $offset += 500) {
        $rows = [];
        for ($i = $offset + 1; $i <= $offset + 500; $i++) {
            $api = $i % 2 === 0;
            $rows[] = ['category' => $api ? 'api' : 'audit', 'event' => $api ? 'api.request.completed' : 'benchmark.synthetic', 'user_id' => ($i % 10000) + 1, 'request_id' => sprintf('00000000-0000-4000-8000-%012d', $i), 'route' => 'api/benchmark/'.($i % 10), 'status' => 200, 'duration_ms' => $i % 1000, 'metadata' => '{"source":"synthetic_benchmark"}', 'created_at' => $now];
        }
        DB::table('platform_events')->insert($rows);
    }
});

$operator = User::findOrFail(1);
PlatformAdmin::create(['user_id' => $operator->id, 'email' => $operator->email]);
// Ephemeral test-only enrolment; never output the secret or bearer token.
PlatformMfaCredential::create(['user_id' => $operator->id, 'secret' => 'JBSWY3DPEHPK3PXP', 'confirmed_at' => now()]);
$access = $operator->createToken('isolated-volume-fixture');
$access->accessToken->forceFill(['platform_mfa_at' => now(), 'platform_recent_auth_at' => now()])->save();
$operator->withAccessToken($access->accessToken);
$seedMs = (hrtime(true) - $started) / 1e6;
$checkpoint('measure_queries');

$count = 0;
$dbMs = 0.0;
$tracking = false;
DB::listen(function ($query) use (&$count, &$dbMs, &$tracking) {
    if ($tracking) {
        $count++;
        $dbMs += $query->time;
    }
});
$measure = function (callable $work, int $runs = 5) use (&$count, &$dbMs, &$tracking): array {
    $times = $queries = $sqlTimes = [];
    for ($i = 0; $i < $runs; $i++) {
        $count = 0;
        $dbMs = 0.0;
        $tracking = true;
        $start = hrtime(true);
        $result = $work();
        $times[] = round((hrtime(true) - $start) / 1e6, 3);
        $tracking = false;
        $queries[] = $count;
        $sqlTimes[] = round($dbMs, 3);
        unset($result);
    }
    sort($times);

    return ['runs' => $runs, 'min_ms' => $times[0], 'median_ms' => $times[(int) floor(($runs - 1) / 2)], 'p95_nearest_rank_ms' => $times[(int) ceil(.95 * $runs) - 1], 'queries_min' => min($queries), 'queries_max' => max($queries), 'sql_ms_max' => max($sqlTimes)];
};
$queries = app(PlatformQueries::class);
$request = fn (string $path, array $params = []) => Request::create($path, 'GET', $params, server: ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_ACCEPT' => 'application/json']);
$results = [];
foreach ([10, 100] as $size) {
    $checkpoint('clients_'.$size);
    $results['clients_'.$size] = $measure(fn () => $queries->clients($request('/api/platform/clients', ['per_page' => $size])));
}
$checkpoint('events_page');
$results['events_page'] = $measure(fn () => $queries->events($request('/api/platform/events', ['per_page' => 25])));
$checkpoint('events_exact_request_search');
$results['events_exact_request_search'] = $measure(fn () => $queries->events($request('/api/platform/events', ['search' => '00000000-0000-4000-8000-000000050000'])));
$checkpoint('overview_cold_cache');
$results['overview_cold_cache'] = $measure(function () use ($queries, $request) {
    Cache::flush();

    return $queries->overview($request('/api/platform/overview'));
});
$checkpoint('overview_warm_cache');
$results['overview_warm_cache'] = $measure(fn () => $queries->overview($request('/api/platform/overview')));
$payload = $queries->clients($request('/api/platform/clients', ['per_page' => 100]));
$results['serialize_clients_100'] = $measure(fn () => json_encode($payload, JSON_THROW_ON_ERROR));
$results['audit_insert'] = $measure(function () use ($request, $operator) {
    $r = $request('/api/platform/events');
    $r->setUserResolver(fn () => $operator);

    return app(EventRecorder::class)->audit($r, 'benchmark.read', 'route', 'events');
});
$results['telemetry_middleware_with_noop_handler'] = $measure(fn () => app(ObservePlatformRequest::class)->handle($request('/api/benchmark'), fn () => response()->json(['ok' => true])));
$kernel = app(HttpKernel::class);
foreach (['/api/auth/me', '/api/platform/clients', '/api/platform/overview', '/api/platform/events'] as $path) {
    $checkpoint('http_kernel:'.$path);
    $results['http_kernel:'.$path] = $measure(function () use ($kernel, $path, $request, $access) {
        app('auth')->forgetGuards();
        $r = $request($path);
        $r->headers->set('Authorization', 'Bearer '.$access->plainTextToken);
        $response = $kernel->handle($r);
        $kernel->terminate($r, $response);
        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException('Benchmark route returned HTTP '.$response->getStatusCode().' on '.$path);
        }

        return $response;
    }, 3);
}

$report = [
    'schema' => 1, 'checked_at' => now()->toIso8601String(), 'environment' => 'testing', 'driver' => 'sqlite', 'database' => ':memory:',
    'fixtures' => ['users' => 10000, 'events_before_measurements' => 100000, 'api_events_before_measurements' => 50000, 'kind' => 'synthetic_authorized_local'],
    'seed_and_migration_ms' => round($seedMs, 3), 'results' => $results,
    'clients_query_count_bounded' => $results['clients_10']['queries_max'] === $results['clients_100']['queries_max'],
    'peak_php_allocated_mb' => round(memory_get_peak_usage(true) / 1048576, 2), 'memory_limit_mb' => 384, 'time_limit_seconds' => 180,
    'total_ms' => round((hrtime(true) - $started) / 1e6, 3),
    'limits' => ['In-process SQLite memory only; no MySQL, network latency, concurrency or production capacity claim.', 'HTTP kernel timings include auth, MFA, audit, middleware, query and response serialization; they are not additive isolated spans.', 'PHP peak allocation is measured; process RSS, GPU and VRAM are not measured.', 'Clients fixtures have no subscriptions; per-trial feature-count queries require separate N+1 profiling.'],
];
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
