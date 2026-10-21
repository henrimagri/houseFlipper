<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\AiKnowledgeDocument;
use App\Models\AiSearchSession;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\User;
use App\Services\Ai\AiAgentCatalog;
use App\Services\Ai\AiConversationService;
use App\Services\Ai\AiKnowledgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AiPlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversations_feedback_and_memory_are_isolated_by_user(): void
    {
        [$owner] = $this->account('ai-owner');
        [$foreign] = $this->account('ai-foreign');

        Sanctum::actingAs($owner);
        $session = $this->withHeader('X-Product-Context', 'corretor')
            ->postJson('/api/ai/conversations/session', [])->assertCreated()->json('data');
        Sanctum::actingAs($foreign);
        $this->withHeader('X-Product-Context', 'corretor')
            ->getJson('/api/ai/conversations?session_id='.$session['session_id'])->assertNotFound();

        Sanctum::actingAs($owner);
        $this->withHeader('X-Product-Context', 'corretor')->putJson('/api/ai/preferences', [
            'enabled' => true,
            'preferences' => ['search_defaults' => ['required' => ['city' => 'Curitiba']]],
        ])->assertOk()->assertJsonPath('data.enabled', true);
        Sanctum::actingAs($foreign);
        $this->withHeader('X-Product-Context', 'corretor')
            ->getJson('/api/ai/preferences')->assertOk()->assertJsonPath('data.enabled', false);

        $model = AiSearchSession::query()->findOrFail($session['session_id']);
        $message = $model->messages()->create(['role' => 'assistant', 'content' => 'Resposta']);
        Sanctum::actingAs($owner);
        $this->postJson('/api/ai/conversations/'.$model->id.'/feedback', [
            'message_id' => $message->id, 'rating' => 'helpful',
        ])->assertCreated();
        Sanctum::actingAs($foreign);
        $this->postJson('/api/ai/conversations/'.$model->id.'/feedback', [
            'message_id' => $message->id, 'rating' => 'helpful',
        ])->assertNotFound();
        $this->assertDatabaseHas('ai_response_feedback', ['user_id' => $owner->id, 'rating' => 'helpful']);
    }

    public function test_knowledge_retrieval_never_crosses_tenants(): void
    {
        [$owner] = $this->account('rag-owner');
        [$foreign] = $this->account('rag-foreign');
        $public = $this->document(null, 'Guia público ITBI', 'itbi prefeitura municipal');
        $private = $this->document($owner->tenant_id, 'Manual interno exclusivo', 'regra exclusiva confidencial');
        $service = app(AiKnowledgeService::class);

        $this->assertCount(1, $service->retrieve($foreign, 'corretor', 'Como funciona o ITBI?'));
        $this->assertSame([], $service->retrieve($foreign, 'corretor', 'regra exclusiva confidencial'));
        $this->assertSame('Manual interno exclusivo', $service->retrieve($owner, 'corretor', 'regra exclusiva confidencial')[0]['title']);
    }

    public function test_agent_tool_allowlist_blocks_unapproved_actions(): void
    {
        $this->expectException(HttpException::class);
        app(AiAgentCatalog::class)->assertToolAllowed('corretor', 'broker_assistant', 'run_sql');
    }

    public function test_agent_tool_budget_is_enforced_before_an_extra_call(): void
    {
        [$owner] = $this->account('budget-owner');
        config(['ai.max_tool_calls' => 1, 'ai.provider' => 'deterministic', 'ai.vector.enabled' => false]);
        $service = app(AiConversationService::class);
        $session = $service->createSession($owner, 'corretor');

        try {
            $service->reply($owner, $session, 'Apartamento em São Paulo até R$ 500 mil');
            $this->fail('O segundo uso de ferramenta deveria ser bloqueado.');
        } catch (HttpException $exception) {
            $this->assertSame(429, $exception->getStatusCode());
        }
        $this->assertDatabaseCount('ai_tool_runs', 1);
    }

    public function test_vector_results_are_post_filtered_by_relational_tenant_scope(): void
    {
        [$owner] = $this->account('vector-owner');
        [$foreign] = $this->account('vector-foreign');
        $public = $this->document(null, 'Documento público', 'conteúdo autorizado');
        $private = $this->document($owner->tenant_id, 'Documento privado', 'segredo de outro tenant');
        $publicChunk = $public->chunks()->firstOrFail();
        $privateChunk = $private->chunks()->firstOrFail();
        config([
            'ai.vector.enabled' => true,
            'ai.vector.endpoint' => 'http://127.0.0.1:6333',
            'ai.vector.collection' => 'test',
            'ai.embeddings.dimensions' => 3,
            'ai.local.endpoint' => 'http://127.0.0.1:11434',
        ]);
        Http::fake([
            '127.0.0.1:11434/api/embed' => Http::response(['embeddings' => [[0.1, 0.2, 0.3]]]),
            '127.0.0.1:6333/*' => Http::response(['result' => ['points' => [
                ['score' => 0.99, 'payload' => ['chunk_id' => $privateChunk->id]],
                ['score' => 0.80, 'payload' => ['chunk_id' => $publicChunk->id]],
            ]]]),
        ]);

        $items = app(AiKnowledgeService::class)->retrieve($foreign, 'corretor', 'pergunta semanticamente distante');

        $this->assertCount(1, $items);
        $this->assertSame('Documento público', $items[0]['title']);
        $this->assertSame('hybrid', $items[0]['retrieval_mode']);
    }

    private function document(?int $tenantId, string $title, string $content): AiKnowledgeDocument
    {
        $document = AiKnowledgeDocument::query()->create([
            'tenant_id' => $tenantId, 'title' => $title, 'category' => 'test', 'source_name' => 'Teste',
            'content_hash' => hash('sha256', ($tenantId ?? 'public').$title), 'status' => 'active',
        ]);
        $document->chunks()->create(['ordinal' => 1, 'content' => $content, 'search_text' => $content]);

        return $document;
    }

    private function account(string $slug): array
    {
        $tenant = Tenant::query()->create(['nome' => $slug, 'slug' => $slug]);
        $user = User::query()->create(['tenant_id' => $tenant->id, 'last_product_slug' => 'corretor', 'name' => $slug, 'email' => $slug.'@example.test', 'email_verified_at' => now(), 'password' => 'password']);
        TenantProductSubscription::query()->create(['tenant_id' => $tenant->id, 'product_slug' => 'corretor', 'active' => true, 'status' => SubscriptionStatus::Active]);

        return [$user, $user->createToken('test')->plainTextToken];
    }
}
