<?php

namespace Tests\Feature;

use App\Models\Propriedade;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PropertyPortalScraper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class AiConversationTest extends TestCase
{
    use RefreshDatabase;

    private function account(): User
    {
        $slug = 'ai-talk-'.str_replace('.', '', uniqid('', true));
        $tenant = Tenant::create(['nome' => $slug, 'slug' => $slug]);

        return User::factory()->create(['tenant_id' => $tenant->id]);
    }

    private function listing(array $overrides = []): array
    {
        return array_replace([
            'id' => 'qa-1', 'title' => 'Apartamento 2 vagas Aldeota Fortaleza', 'address' => 'Aldeota',
            'city' => 'Fortaleza', 'neighborhood' => 'Aldeota', 'state' => 'CE',
            'source' => 'OLX', 'source_key' => 'olx', 'source_url' => 'https://example.test/qa-1',
            'thumbnail' => null, 'asking_price' => 520000, 'estimated_market_price' => 600000,
            'discount_percentage' => 0, 'area_m2' => 80, 'bedrooms' => 2,
            'bathrooms' => 2, 'parking_spaces' => 2, 'price_per_m2' => 6500,
            'score' => 70, 'signal' => 'Negociar',
        ], $overrides);
    }

    public function test_conversation_respects_mandatory_price_and_does_not_invent_listings(): void
    {
        $user = $this->account();
        $this->actingAs($user, 'sanctum');
        $property = Propriedade::create(['apelido' => 'Apto Aldeota', 'tipo' => 'apartamento', 'quartos' => 2, 'vagas' => 2, 'valor_aquisicao' => 480000, 'status' => 'prospeccao']);
        $property->endereco()->create(['cidade' => 'Fortaleza', 'estado' => 'CE', 'bairro' => 'Aldeota']);

        $this->mock(PropertyPortalScraper::class, function (MockInterface $mock): void {
            $mock->shouldReceive('scrape')->once()->andReturn([
                'source_name' => 'QA scraper',
                'diagnostics' => [],
                'items' => [
                    $this->listing(),
                    $this->listing(['id' => 'too-expensive', 'title' => 'Cobertura Meireles Fortaleza', 'asking_price' => 890000, 'parking_spaces' => 2]),
                ],
            ]);
        });

        $response = $this->postJson('/api/ai/conversations', [
            'message' => 'Quero um apartamento em Fortaleza, de preferência na Aldeota ou Meireles, até R$ 650 mil, com pelo menos dois quartos e duas vagas.',
        ])->assertOk();

        $ids = collect($response->json('data.listings'))->pluck('id');
        $this->assertTrue($ids->contains('qa-1'));
        $this->assertFalse($ids->contains('too-expensive'));
        $this->assertTrue($ids->contains('property:'.$property->id));
        $this->assertSame(650000, (int) $response->json('data.criteria.required.max_price'));
        $this->assertSame('search', $response->json('data.intent'));
        $market = collect($response->json('data.listings'))->firstWhere('id', 'qa-1');
        $this->assertIsArray($market);
        $this->assertStringContainsString('ia-inteligente/analise/qa-1', (string) $market['visualization_path']);
        $this->assertStringContainsString('scraper', (string) $response->json('data.answer'));
    }

    public function test_recommendation_reuses_previous_results_without_new_scrape(): void
    {
        $this->actingAs($this->account(), 'sanctum');
        $this->mock(PropertyPortalScraper::class, function (MockInterface $mock): void {
            $mock->shouldReceive('scrape')->once()->andReturn([
                'source_name' => 'QA scraper', 'diagnostics' => [], 'items' => [$this->listing()],
            ]);
        });

        $first = $this->postJson('/api/ai/conversations', [
            'message' => 'Quero um apartamento em Fortaleza até R$ 650 mil com dois quartos e duas vagas.',
        ])->assertOk();

        $this->postJson('/api/ai/conversations', [
            'session_id' => $first->json('data.session_id'),
            'message' => 'Qual desses você considera mais interessante?',
        ])->assertOk()
            ->assertJsonPath('data.intent', 'recommend')
            ->assertJsonPath('data.recommendation.title', 'Apartamento 2 vagas Aldeota Fortaleza');
    }

    public function test_other_user_cannot_read_another_conversation_session(): void
    {
        $owner = $this->account();
        $this->actingAs($owner, 'sanctum');
        $this->mock(PropertyPortalScraper::class, function (MockInterface $mock): void {
            $mock->shouldReceive('scrape')->andReturn(['source_name' => 'QA scraper', 'diagnostics' => [], 'items' => []]);
        });
        $sessionId = $this->postJson('/api/ai/conversations', ['message' => 'Quero um apartamento em Fortaleza até 500 mil.'])->json('data.session_id');

        $this->actingAs($this->account(), 'sanctum');
        $this->getJson('/api/ai/conversations?session_id='.$sessionId)
            ->assertOk()
            ->assertJsonMissingPath('data.messages.0.content');
        $this->assertNotSame($sessionId, $this->getJson('/api/ai/conversations?session_id='.$sessionId)->json('data.session_id'));
    }
}
