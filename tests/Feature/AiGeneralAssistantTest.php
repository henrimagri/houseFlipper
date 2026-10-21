<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\User;
use App\Services\Ai\AiAgentCatalog;
use App\Services\Ai\AiConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AiGeneralAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_general_request_does_not_run_property_or_market_tools(): void
    {
        $user = $this->account('general-user', 'corretor');
        config(['ai.provider' => 'deterministic', 'ai.vector.enabled' => false]);
        $service = app(AiConversationService::class);
        $session = $service->createSession($user, 'corretor');

        $reply = $service->reply($user, $session, 'Olá, como você pode me ajudar?');

        $this->assertSame('general', $reply['intent']);
        $this->assertSame('general_assistant', $reply['agent']['key']);
        $this->assertSame([], $reply['listings']);
        $this->assertDatabaseCount('ai_tool_runs', 0);
    }

    public function test_text_creation_continuity_is_kept_without_reusing_property_tools(): void
    {
        $user = $this->account('content-user', 'corretor');
        config(['ai.provider' => 'deterministic', 'ai.vector.enabled' => false]);
        $service = app(AiConversationService::class);
        $session = $service->createSession($user, 'corretor');

        $first = $service->reply($user, $session, 'Escreva um e-mail para apresentar meus serviços');
        $second = $service->reply($user, $session->fresh(), 'Faça outra versão mais curta');

        $this->assertSame('text_creation', $first['intent']);
        $this->assertSame('text_creation', $second['intent']);
        $this->assertSame('continuidade_da_conversa', $second['classification']['reason']);
        $this->assertDatabaseCount('ai_tool_runs', 0);
    }

    public function test_general_agents_cannot_invoke_property_or_arbitrary_tools(): void
    {
        $catalog = app(AiAgentCatalog::class);

        foreach (['search_inventory', 'crm_summary', 'run_sql'] as $tool) {
            try {
                $catalog->assertToolAllowed('corretor', 'marketing_assistant', $tool);
                $this->fail("A ferramenta {$tool} deveria ser bloqueada.");
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
    }

    public function test_product_specific_agent_remains_restricted_to_active_product(): void
    {
        $user = $this->account('broker-only', 'corretor');
        config(['ai.provider' => 'deterministic', 'ai.vector.enabled' => false]);
        $service = app(AiConversationService::class);
        $session = $service->createSession($user, 'corretor');

        $reply = $service->reply($user, $session, 'Mostre o CRM e os leads da imobiliária');

        $this->assertSame('product_specific', $reply['intent']);
        $this->assertNotSame('agency_intelligence', $reply['agent']['key']);
        $this->assertDatabaseMissing('ai_tool_runs', ['tool' => 'crm_summary']);
    }

    private function account(string $slug, string $product): User
    {
        $tenant = Tenant::query()->create(['nome' => $slug, 'slug' => $slug]);
        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'last_product_slug' => $product,
            'name' => $slug,
            'email' => $slug.'@example.test',
            'email_verified_at' => now(),
            'password' => 'password',
        ]);
        TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_slug' => $product,
            'active' => true,
            'status' => SubscriptionStatus::Active,
        ]);

        return $user;
    }
}
