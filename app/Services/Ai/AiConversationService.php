<?php

namespace App\Services\Ai;

use App\Http\Controllers\Api\AIPropertySearchController;
use App\Models\AiSearchSession;
use App\Models\AiToolRun;
use App\Models\AiUserPreference;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Services\Platform\ResourceQuota;
use App\Support\ProductContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AiConversationService
{
    public function __construct(
        private readonly PropertyQueryInterpreter $interpreter,
        private readonly AiIntentClassifier $intentClassifier,
        private readonly PropertyMatchRanker $ranker,
        private readonly InventoryListingSearch $inventory,
        private readonly ProductEntitlement $entitlement,
        private readonly AiAgentCatalog $agents,
        private readonly AiKnowledgeService $knowledge,
        private readonly AiToolTelemetry $telemetry,
        private readonly AiResponseSynthesizer $synthesizer,
        private readonly AiOperationalTools $operations,
    ) {}

    public function session(User $user, ?int $id = null): AiSearchSession
    {
        if ($id) {
            $session = AiSearchSession::query()->where('user_id', $user->id)->whereKey($id)->first();
            if ($session) {
                $currentProduct = app(ProductContext::class)->current();
                abort_if($currentProduct && $session->product_slug !== $currentProduct, 404, 'Conversa não encontrada.');
                $this->entitlement->assert($user, 'conversational_search', $session->product_slug);

                return $session;
            }
            abort(404, 'Conversa não encontrada.');
        }

        $product = app(ProductContext::class)->current()
            ?? $user->last_product_slug
            ?? $this->entitlement->productsFor($user)[0]
            ?? 'house_flipper';

        return AiSearchSession::query()
            ->where('user_id', $user->id)
            ->where('product_slug', $product)
            ->where('status', 'active')
            ->latest('updated_at')
            ->first() ?? $this->createSession($user, $product);
    }

    public function createSession(User $user, ?string $product = null): AiSearchSession
    {
        $product = app(ProductContext::class)->normalize($product)
            ?? app(ProductContext::class)->current()
            ?? $user->last_product_slug
            ?? $this->entitlement->productsFor($user)[0]
            ?? 'house_flipper';
        $this->entitlement->assert($user, 'conversational_search', $product);
        $membership = OrganizationMembership::query()->where('user_id', $user->id)->where('status', 'active')->first();

        return AiSearchSession::query()->create([
            'user_id' => $user->id,
            'tenant_id' => (int) $user->tenant_id,
            'organization_id' => $membership?->organization_id,
            'product_slug' => $product,
            'title' => 'Nova conversa',
            'status' => 'active',
            'prompt_version' => config('ai.prompt_version'),
            'expires_at' => now()->addDays((int) config('ai.session_retention_days', 90)),
            'criteria' => ['required' => [], 'preferred' => []],
        ]);
    }

    public function reply(User $user, AiSearchSession $session, string $message): array
    {
        $this->entitlement->assert($user, 'conversational_search', $session->product_slug);
        abort_unless((int) $session->user_id === (int) $user->id && (int) $session->tenant_id === (int) $user->tenant_id, 404);
        app(ResourceQuota::class)->admit($user, $session->product_slug, 'ai_requests', (string) Str::uuid());
        $message = Str::of($message)->replaceMatches('/[\x00-\x1F\x7F]+/u', ' ')->squish()->limit(2000, '')->toString();
        $previousCriteria = $session->criteria ?? [];
        $classification = $this->intentClassifier->classify(
            $message,
            $session->product_slug,
            $previousCriteria['conversation_intent'] ?? null,
        );
        $intent = $classification['intent'];
        if ($intent === 'property_search') {
            $criteria = $this->interpreter->interpret($message, $previousCriteria);
            $preference = AiUserPreference::query()->where('user_id', $user->id)->where('product_slug', $session->product_slug)->first();
            $defaults = $preference?->preferences['search_defaults'] ?? [];
            if (is_array($defaults)) {
                $criteria['required'] = [...($defaults['required'] ?? []), ...($criteria['required'] ?? [])];
                $criteria['preferred'] = [...($defaults['preferred'] ?? []), ...($criteria['preferred'] ?? [])];
            }
        } else {
            $criteria = [
                'intent' => $intent,
                'required' => $previousCriteria['required'] ?? [],
                'preferred' => $previousCriteria['preferred'] ?? [],
                'unknowns' => [],
                'warnings' => [],
            ];
        }
        $criteria['conversation_intent'] = $intent;
        $filters = $intent === 'property_search' ? $this->interpreter->toSearchFilters($criteria) : [];
        $agent = $this->agents->select($user, $session->product_slug, $message, $intent);
        $session->fill([
            'active_agent' => $agent['key'],
            'prompt_version' => config('ai.prompt_version'),
            'title' => $session->title === 'Nova conversa' ? Str::limit($message, 72, '') : $session->title,
        ])->save();

        $listings = $session->last_listings ?? [];
        $marketMeta = ['used' => false, 'status' => 'skipped'];
        $toolEvents = [];
        $toolCalls = 0;
        $citations = [];
        $operationalSummary = null;

        if (($agent['workflow'] ?? 'property_search') === 'crm_summary') {
            $this->agents->assertToolAllowed($session->product_slug, $agent['key'], 'crm_summary');
            $operationalSummary = $this->runTool($toolCalls, $user, $session, $agent['key'], 'crm_summary', [], fn () => $this->operations->crmSummary($user));
            $toolEvents[] = ['tool' => 'crm_summary', 'label' => 'Indicadores do CRM consultados', 'status' => 'success'];
        } elseif (($agent['workflow'] ?? 'property_search') === 'investment_summary') {
            $this->agents->assertToolAllowed($session->product_slug, $agent['key'], 'calculate_investment');
            $operationalSummary = $this->runTool($toolCalls, $user, $session, $agent['key'], 'calculate_investment', [], fn () => $this->operations->investmentSummary($user));
            $toolEvents[] = ['tool' => 'calculate_investment', 'label' => 'Carteira de investimento analisada', 'status' => 'success'];
        } elseif (($agent['workflow'] ?? 'property_search') === 'property_search' && $intent === 'property_search' && in_array($criteria['intent'] ?? null, ['search', 'refine'], true)) {
            $this->agents->assertToolAllowed($session->product_slug, $agent['key'], 'search_inventory');
            $inventory = $this->runTool($toolCalls, $user, $session, $agent['key'], 'search_inventory', $filters, fn () => $this->inventory->search($user, $filters));
            $toolEvents[] = ['tool' => 'search_inventory', 'label' => 'Carteira consultada', 'status' => 'success', 'count' => count($inventory)];
            $this->agents->assertToolAllowed($session->product_slug, $agent['key'], 'search_market');
            $market = $this->runTool($toolCalls, $user, $session, $agent['key'], 'search_market', $filters, fn () => $this->marketSearch($user, $filters));
            $toolEvents[] = ['tool' => 'search_market', 'label' => 'Fontes externas consultadas', 'status' => $market['meta']['status'], 'count' => count($market['items'])];
            $marketMeta = $market['meta'];
            $listings = array_values([...$inventory, ...$market['items']]);
        }

        if ($this->knowledge->shouldRetrieve($message)) {
            $this->agents->assertToolAllowed($session->product_slug, $agent['key'], 'retrieve_knowledge');
            $citations = $this->runTool($toolCalls, $user, $session, $agent['key'], 'retrieve_knowledge', ['query_hash' => hash('sha256', $message)], fn () => $this->knowledge->retrieve($user, $session->product_slug, $message));
            $toolEvents[] = ['tool' => 'retrieve_knowledge', 'label' => 'Base autorizada consultada', 'status' => 'success', 'count' => count($citations)];
        }

        $ranked = $intent === 'property_search' ? $this->ranker->rank($listings, $criteria) : [];
        $eligible = collect($ranked)->where('eligible', true)->values();
        $propertyIntent = $criteria['intent'] ?? null;
        $recommendation = $intent === 'property_search' && in_array($propertyIntent, ['recommend', 'compare', 'search', 'refine'], true)
            ? $this->ranker->recommend($ranked)
            : null;
        $comparison = [];
        if ($intent === 'property_search' && $propertyIntent === 'compare') {
            $this->agents->assertToolAllowed($session->product_slug, $agent['key'], 'compare_properties');
            $comparison = $this->runTool($toolCalls, $user, $session, $agent['key'], 'compare_properties', ['listing_count' => count($ranked)], fn () => $this->ranker->compare($ranked));
            $toolEvents[] = ['tool' => 'compare_properties', 'label' => 'Imóveis comparados', 'status' => 'success', 'count' => count($comparison)];
        }
        $gap = $intent === 'property_search' && ($agent['workflow'] ?? 'property_search') === 'property_search' && $eligible->isEmpty()
            ? $this->ranker->missingExplanation($criteria, $ranked)
            : null;

        $verifiedAnswer = $operationalSummary !== null
            ? $this->composeOperational($agent['workflow'], $operationalSummary)
            : ($citations !== [] && $eligible->isEmpty()
            ? $this->composeKnowledge($citations)
            : ($intent !== 'property_search'
                ? $this->composeAssistance($intent, $session->product_slug)
                : $this->compose((string) ($propertyIntent ?? 'search'), $criteria, $eligible->all(), $recommendation, $comparison, $gap, $marketMeta)));
        $answer = $verifiedAnswer;
        $provider = 'deterministic';
        if ($this->synthesizer->available()) {
            $this->agents->assertToolAllowed($session->product_slug, $agent['key'], 'synthesize_response');
            $synthesis = $this->runTool($toolCalls, $user, $session, $agent['key'], 'synthesize_response', ['context_items' => $eligible->count() + count($citations), 'intent' => $intent], fn () => $this->synthesizer->synthesize($message, $verifiedAnswer, [
                'classification' => $classification,
                'product' => $session->product_slug,
                'criteria' => $criteria,
                'results' => $eligible->take(8)->map(fn (array $item) => collect($item)->only(['id', 'title', 'origin', 'asking_price', 'neighborhood', 'city', 'state', 'bedrooms', 'bathrooms', 'parking_spaces', 'area_m2', 'match_score', 'match_reasons'])->all())->values()->all(),
                'citations' => $citations,
                'recent_context' => $this->recentContext($session),
            ], $intent));
            $answer = $synthesis['text'];
            $provider = $synthesis['provider'];
            $usage = $synthesis['usage'] ?? null;
            if (is_array($usage)) {
                AiToolRun::query()->where('ai_search_session_id', $session->id)->where('tool', 'synthesize_response')->latest('id')->limit(1)->update([
                    'input_tokens' => $usage['input_tokens'] ?? null,
                    'output_tokens' => $usage['output_tokens'] ?? null,
                ]);
            }
            $toolEvents[] = ['tool' => 'synthesize_response', 'label' => in_array($provider, ['openai', 'local'], true) ? 'Resposta redigida pelo modelo configurado' : 'Resposta determinística preservada', 'status' => 'success'];
        }
        $uiBlocks = array_values(array_filter([
            $eligible->isNotEmpty() ? ['type' => 'property_cards', 'count' => min(20, $eligible->count())] : null,
            $comparison !== [] ? ['type' => 'comparison_table', 'rows' => $comparison] : null,
            $citations !== [] ? ['type' => 'knowledge_sources', 'count' => count($citations)] : null,
            $operationalSummary !== null ? ['type' => 'operational_summary', 'workflow' => $agent['workflow'], 'data' => $operationalSummary] : null,
        ]));

        $sessionState = [
            'criteria' => $criteria,
        ];
        if ($intent === 'property_search') {
            $sessionState['last_listings'] = collect($ranked)->take(50)->all();
            $sessionState['last_recommendation'] = $recommendation;
        }
        $session->fill($sessionState)->save();

        $session->messages()->create([
            'role' => 'user',
            'content' => $message,
            'criteria_snapshot' => $criteria,
        ]);
        $assistantMessage = $session->messages()->create([
            'role' => 'assistant',
            'content' => $answer,
            'criteria_snapshot' => $criteria,
            'listing_ids' => $eligible->pluck('id')->take(20)->values()->all(),
            'citations' => $citations,
            'ui_blocks' => $uiBlocks,
            'tool_events' => $toolEvents,
        ]);

        return [
            'session_id' => $session->id,
            'intent' => $intent,
            'classification' => $classification,
            'answer' => $answer,
            'criteria' => $criteria,
            'filters' => $filters,
            'listings' => $eligible->take(20)->values()->all(),
            'recommendation' => $recommendation,
            'comparison' => $comparison,
            'no_match' => $gap,
            'market' => $marketMeta,
            'agent' => $agent,
            'citations' => $citations,
            'tool_events' => $toolEvents,
            'ui_blocks' => $uiBlocks,
            'assistant_message_id' => $assistantMessage->id,
            'provider' => $provider,
            'messages' => $session->messages()->latest('id')->limit(12)->get()->reverse()->values(),
        ];
    }

    private function runTool(
        int &$used,
        User $user,
        AiSearchSession $session,
        string $agent,
        string $tool,
        array $input,
        callable $callback,
    ): mixed {
        $used++;
        abort_if($used > max(1, (int) config('ai.max_tool_calls', 6)), 429, 'O limite de ferramentas desta solicitação foi atingido.');

        return $this->telemetry->run($user, $session, $agent, $tool, $input, $callback);
    }

    private function composeKnowledge(array $citations): string
    {
        $facts = collect($citations)->take(3)->pluck('excerpt')->map(fn (string $text) => Str::finish($text, '.'))->implode(' ');

        return $facts.' Confira as fontes abaixo. Para decisões jurídicas, fiscais ou financeiras, valide o caso com o órgão e o profissional responsáveis.';
    }

    private function composeAssistance(string $intent, string $product): string
    {
        return match ($intent) {
            'text_creation' => 'Posso preparar o rascunho solicitado usando somente as informações fornecidas. Revise nomes, valores, características e condições antes de publicar ou enviar.',
            'marketing' => 'Posso sugerir uma estratégia de marketing adequada ao objetivo informado. As ideias serão apresentadas como recomendações, sem promessas de resultado ou métricas inventadas.',
            'business' => 'Posso estruturar opções empresariais, premissas e próximos passos. Dados, cálculos e indicadores específicos dependem das informações verificadas que você fornecer.',
            'product_specific' => "Posso orientar sobre os recursos autorizados do produto {$product}. Ações que alterem dados, publiquem conteúdo ou contatem terceiros exigem uma ferramenta permitida e confirmação.",
            default => 'Posso ajudar com dúvidas gerais e explicar como avançar. Quando a resposta depender de dados atuais ou da sua conta, consultarei somente fontes e ferramentas autorizadas.',
        };
    }

    private function recentContext(AiSearchSession $session): array
    {
        return $session->messages()->latest('id')->limit(6)->get()->reverse()->map(fn ($message) => [
            'role' => in_array($message->role, ['user', 'assistant'], true) ? $message->role : 'user',
            'content' => Str::of($message->content)
                ->replaceMatches('/[\w.+-]+@[\w.-]+\.[a-z]{2,}/iu', '[email removido]')
                ->replaceMatches('/\b(?:\+?55\s*)?(?:\(?\d{2}\)?\s*)?\d{4,5}[-\s]?\d{4}\b/', '[telefone removido]')
                ->replaceMatches('/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/', '[documento removido]')
                ->limit(800, '')
                ->toString(),
        ])->values()->all();
    }

    private function composeOperational(string $workflow, array $summary): string
    {
        if ($workflow === 'crm_summary') {
            $value = number_format(((int) $summary['pipeline_value_cents']) / 100, 2, ',', '.');

            return "O CRM da imobiliária tem {$summary['contacts']} contatos acessíveis, {$summary['deals']} negócios e R$ {$value} em valor estimado no funil. Há {$summary['overdue_next_actions']} próximas ações vencidas. Os números respeitam a organização ativa e não expõem dados pessoais.";
        }
        $spread = number_format((float) $summary['gross_spread_total'], 2, ',', '.');

        return "Sua carteira tem {$summary['opportunities']} oportunidades; {$summary['with_market_estimate']} possuem preço pedido e estimativa de mercado suficientes para o comparativo. O spread bruto agregado é R$ {$spread}. {$summary['warning']}";
    }

    private function marketSearch(User $user, array $filters): array
    {
        $query = array_filter([
            'city' => $filters['city'] ?? null,
            'neighborhood' => $filters['neighborhood'] ?? null,
            'state' => $filters['state'] ?? null,
            'property_type' => $filters['property_type'] ?? 'apartment',
            'max_price' => $filters['max_price'] ?? null,
            'min_bedrooms' => $filters['min_bedrooms'] ?? null,
            'min_bathrooms' => $filters['min_bathrooms'] ?? null,
            'min_parking' => $filters['min_parking'] ?? null,
            'min_area_m2' => $filters['min_area_m2'] ?? null,
            'limit' => 50,
        ], fn ($value) => $value !== null && $value !== '');

        $request = Request::create('/api/ai-property-search', 'GET', $query);
        $request->setUserResolver(fn () => $user);
        $request->headers->set('Accept', 'application/json');

        $response = app(AIPropertySearchController::class)($request);
        $payload = json_decode($response->getContent(), true) ?: [];
        if ($response->getStatusCode() >= 400) {
            return ['items' => [], 'meta' => ['used' => true, 'status' => 'unavailable', 'message' => $payload['message'] ?? 'Fonte de anúncios indisponível.']];
        }

        $items = collect($payload['data'] ?? [])->map(function (array $item): array {
            $item['origin'] = 'market';
            $item['detail_path'] = '/ia-inteligente/analise/'.$item['id'];
            $item['visualization_path'] = '/ia-inteligente/analise/'.$item['id'].'#section-3d';
            $item['model_note'] = 'Maquete ilustrativa gerada dos dados deste anúncio. Não é modelo oficial nem fotogrametria.';
            $item['source_kind'] = 'portal';

            return $item;
        })->all();

        return [
            'items' => $items,
            'meta' => [
                'used' => true,
                'status' => 'ok',
                'source' => $payload['source']['name'] ?? null,
                'mode' => $payload['source']['mode'] ?? null,
                'count' => count($items),
            ],
        ];
    }

    private function compose(
        string $intent,
        array $criteria,
        array $eligible,
        ?array $recommendation,
        array $comparison,
        ?array $gap,
        array $marketMeta,
    ): string {
        if ($intent === 'visualize') {
            return 'Abra Ver 3D no card: a maquete tem fachada (Ver exterior) e ambientes nomeados (Ver interior). Sem GLB enviado, o selo permanece paramétrico ou ilustrativo. Planta 2D, se existir, não é extrusão cotada. Tour 360 só aparece com panorama. Isto não é fotogrametria.';
        }
        if ($gap && $eligible === []) {
            return $gap['message'].' '.$this->criteriaLine($criteria).($marketMeta['status'] === 'unavailable' ? ' A coleta de portais não respondeu nesta rodada; a carteira da conta foi consultada mesmo assim.' : '');
        }
        if ($intent === 'compare') {
            $lines = collect($comparison)->map(fn (array $item) => $item['title'].': '.implode(' ', $item['advantages'] ?? []))->take(3)->implode(' ');

            return $lines !== '' ? $lines : 'Não há itens elegíveis o suficiente para comparar com os critérios atuais.';
        }
        if ($intent === 'recommend' && $recommendation) {
            return $recommendation['explanation'].' Imóvel: '.$recommendation['title'].'.';
        }

        $count = count($eligible);
        $head = $count === 1 ? 'Encontrei 1 imóvel compatível com os critérios obrigatórios.' : "Encontrei {$count} imóveis compatíveis com os critérios obrigatórios.";
        $sourceLine = $marketMeta['status'] === 'ok'
            ? ' Fontes desta rodada: carteira da conta e coleta de portais (scraper). ONR, DataZAP e leilão não foram consultados.'
            : ($marketMeta['status'] === 'unavailable'
                ? ' A coleta de portais não respondeu; a carteira da conta foi consultada.'
                : ' Fontes desta rodada: carteira da conta.');
        $visual = ' A visualização 3D desses cards é maquete do próprio anúncio, não um modelo de outro imóvel.';

        return $head.' '.$this->criteriaLine($criteria).($recommendation ? ' Destaque: '.$recommendation['title'].'.' : '').$sourceLine.$visual;
    }

    private function criteriaLine(array $criteria): string
    {
        $required = $criteria['required'] ?? [];
        $parts = [];
        if (isset($required['city'])) {
            $parts[] = $required['city'];
        }
        if (isset($required['max_price'])) {
            $parts[] = 'teto R$ '.number_format((float) $required['max_price'], 0, ',', '.');
        }
        if (isset($required['min_bedrooms'])) {
            $parts[] = $required['min_bedrooms'].' quartos';
        }
        if (isset($required['min_parking'])) {
            $parts[] = $required['min_parking'].' vagas';
        }

        return $parts === [] ? '' : 'Filtros obrigatórios: '.implode(', ', $parts).'.';
    }
}
