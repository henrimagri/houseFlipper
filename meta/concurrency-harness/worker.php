<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$fixture = json_decode(file_get_contents(__DIR__.'/fixture.private'), true);
$job = json_decode(file_get_contents($argv[1]), true);
file_put_contents($argv[2].'.ready', 'ready');
$deadline = microtime(true) + 15;
while (! file_exists($argv[2].'.go')) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Barrier timeout');
    }
    usleep(10000);
}
$startedAt = microtime(true);
if (! empty($job['delay_ms'])) {
    usleep((int) $job['delay_ms'] * 1000);
}
$server = ['HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$fixture['token']];
if (! empty($job['file'])) {
    $file = UploadedFile::fake()->createWithContent('concurrency.pdf', "%PDF-1.4\n% isolated fictional audit file\n");
    $request = Request::create($job['uri'], $job['method'], $job['body'] ?? [], [], ['arquivo' => $file], $server);
} else {
    $server['CONTENT_TYPE'] = 'application/json';
    $request = Request::create($job['uri'], $job['method'], [], [], [], $server, json_encode($job['body'] ?? []));
}
$response = $kernel->handle($request);
file_put_contents($argv[2].'.result', json_encode(['status' => $response->getStatusCode(), 'started_at' => $startedAt, 'finished_at' => microtime(true)]));
exit(0);
