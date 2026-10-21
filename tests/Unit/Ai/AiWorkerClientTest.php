<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\AiWorkerClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class AiWorkerClientTest extends TestCase
{
    public function test_worker_uses_backend_credential_and_loopback(): void
    {
        config(['ai.worker.endpoint' => 'http://127.0.0.1:8010', 'ai.worker.token' => 'secret']);
        Http::fake(['127.0.0.1:8010/health' => Http::response(['status' => 'ok'])]);

        $this->assertSame('ok', app(AiWorkerClient::class)->health()['status']);
        Http::assertSent(fn ($request) => $request->hasHeader('X-Worker-Token', 'secret'));
    }

    public function test_worker_rejects_remote_host(): void
    {
        config(['ai.worker.endpoint' => 'https://example.com', 'ai.worker.token' => 'secret']);
        $this->expectException(RuntimeException::class);
        app(AiWorkerClient::class)->health();
    }

    public function test_worker_sends_private_vision_upload_and_downloads_only_valid_artifact_ids(): void
    {
        config(['ai.worker.endpoint' => 'http://127.0.0.1:8010', 'ai.worker.token' => 'secret']);
        Http::fake([
            '127.0.0.1:8010/v1/vision/analyze' => Http::response(['job_id' => '00000000-0000-0000-0000-000000000001']),
            '127.0.0.1:8010/v1/jobs/*' => Http::response('private-bytes'),
        ]);

        $client = app(AiWorkerClient::class);
        $this->assertSame('00000000-0000-0000-0000-000000000001', $client->analyzeVision('image', 'room.jpg')['job_id']);
        $this->assertSame('private-bytes', $client->artifact('00000000-0000-0000-0000-000000000001', 'depth.png'));
        Http::assertSent(fn ($request) => $request->hasHeader('X-Worker-Token', 'secret'));
    }
}
