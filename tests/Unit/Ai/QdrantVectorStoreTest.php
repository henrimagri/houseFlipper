<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\QdrantVectorStore;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class QdrantVectorStoreTest extends TestCase
{
    public function test_search_sends_tenant_and_product_filters(): void
    {
        config(['ai.vector.enabled' => true, 'ai.vector.endpoint' => 'http://127.0.0.1:6333', 'ai.vector.collection' => 'test']);
        Http::fake(['127.0.0.1:6333/*' => Http::response(['result' => ['points' => [[
            'id' => 5, 'score' => 0.9, 'payload' => ['chunk_id' => 5],
        ]]]])]);

        $items = app(QdrantVectorStore::class)->search([0.1, 0.2], 9, 'corretor');

        $this->assertSame(5, $items[0]['chunk_id']);
        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $request->url() === 'http://127.0.0.1:6333/collections/test/points/query'
                && data_get($payload, 'filter.must.0.should.0.match.value') === 9
                && data_get($payload, 'filter.must.1.should.0.match.value') === 'corretor';
        });
    }

    public function test_remote_host_is_rejected(): void
    {
        config(['ai.vector.endpoint' => 'https://example.com']);
        $this->expectException(RuntimeException::class);
        app(QdrantVectorStore::class)->search([0.1], 1, 'corretor');
    }
}
