<?php

use App\Models\PlatformAdmin;
use App\Models\PlatformMfaCredential;
use App\Models\User;
use App\Services\Platform\Totp;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;

// Local API integration check. Never uses a browser session or logs credentials.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('local')) {
    fwrite(STDERR, "This smoke check is restricted to APP_ENV=local.\n");
    exit(2);
}
$base = getenv('PLATFORM_SMOKE_BASE_URL') ?: 'http://127.0.0.1:8000';
if (! preg_match('#^http://127\.0\.0\.1:[0-9]{4,5}$#D', $base)) {
    exit(2);
}
$password = bin2hex(random_bytes(24));
$user = new User(['name' => 'Platform Smoke QA', 'email' => 'platform-smoke-'.bin2hex(random_bytes(8)).'@example.test', 'password' => $password]);
$user->forceFill(['email_verified_at' => now(), 'requires_email_verification' => false])->save();
$admin = PlatformAdmin::create(['user_id' => $user->id, 'email' => $user->email]);
$results = [];
$failed = false;
try {
    $client = Http::acceptJson()->connectTimeout(2)->timeout(15)->withOptions(['allow_redirects' => false]);
    $login = $client->post($base.'/api/auth/login', ['email' => $user->email, 'password' => $password, 'device_name' => 'platform-smoke']);
    if (! $login->successful() || ! is_string($token = $login->json('token'))) {
        throw new RuntimeException('Local QA login failed (HTTP '.$login->status().').');
    }
    $locked = (clone $client)->withToken($token)->get($base.'/api/platform/overview');
    $results[] = ['endpoint' => 'mfa_gate_before_enrolment', 'status' => $locked->status()];
    if ($locked->status() !== 403) {
        throw new RuntimeException('MFA gate did not block unverified session');
    }
    $enrol = (clone $client)->withToken($token)->post($base.'/api/platform/mfa/enrol', ['password' => $password]);
    if (! $enrol->successful() || ! is_string($mfaSecret = $enrol->json('secret'))) {
        throw new RuntimeException('Local MFA enrolment failed');
    }
    $confirm = (clone $client)->withToken($token)->post($base.'/api/platform/mfa/confirm', ['code' => app(Totp::class)->code($mfaSecret, now()->timestamp)]);
    $results[] = ['endpoint' => 'mfa_confirm', 'status' => $confirm->status()];
    if (! $confirm->successful()) {
        throw new RuntimeException('Local MFA confirmation failed');
    }
    unset($mfaSecret, $enrol, $confirm);
    foreach (['overview', 'clients', 'trials', 'funnel', 'cohorts', 'churn', 'events', 'incidents', 'sessions', 'ips', 'health', 'performance', 'errors', 'ai', 'tools', 'rag', 'jobs', 'storage', 'queues', 'subscriptions', 'billing', 'webhooks', 'emails', 'controls', 'saas-leads', 'heartbeats', 'apm', 'slow', 'inventory', 'readiness', 'administrators', 'privacy', 'exports', 'healthHistory'] as $endpoint) {
        $start = hrtime(true);
        $response = (clone $client)->withToken($token)->get($base.'/api/platform/'.$endpoint, ['per_page' => 2]);
        $results[] = ['endpoint' => $endpoint, 'status' => $response->status(), 'ms' => round((hrtime(true) - $start) / 1e6, 1), 'json' => is_array($response->json())];
        $failed = $failed || ! $response->successful() || ! is_array($response->json());
    }
    $response = (clone $client)->withToken($token)->get($base.'/api/platform/clients/'.$user->id);
    $results[] = ['endpoint' => 'client_360', 'status' => $response->status()];
    $failed = $failed || ! $response->successful();
    $response = (clone $client)->withToken($token)->post($base.'/api/platform/incidents', ['title' => 'Local smoke QA — safe operational event', 'severity' => 'info']);
    $incidentId = $response->json('data.id');
    $results[] = ['endpoint' => 'incident_create', 'status' => $response->status()];
    $failed = $failed || ! $response->successful();
    if ($incidentId) {
        $response = (clone $client)->withToken($token)->patch($base.'/api/platform/incidents/'.$incidentId, ['status' => 'false_positive', 'resolution' => 'Safe local smoke QA; no external attack.']);
        $results[] = ['endpoint' => 'incident_close', 'status' => $response->status()];
        $failed = $failed || ! $response->successful();
    }
    $response = (clone $client)->withToken($token)->post($base.'/api/auth/logout');
    $results[] = ['endpoint' => 'logout', 'status' => $response->status()];
} catch (Throwable $e) {
    $failed = true;
    $results[] = ['failure_type' => class_basename($e)];
} finally {
    // Only records created by this invocation, retained audit events are untouched.
    $user->tokens()->delete();
    PlatformMfaCredential::where('user_id', $user->id)->delete();
    $admin->delete();
    $user->delete();
}
$report = ['passed' => ! $failed, 'checked_at' => now()->toIso8601String(), 'browser_qa' => 'reported_separately_in_VALIDATION.md', 'checks' => $results];
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($failed ? 1 : 0);
