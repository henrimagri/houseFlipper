<?php

namespace App\Services\Ai;

use App\Http\Controllers\Api\AIPropertySearchController;
use App\Models\AiSearchSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AiConversationService
{
    public function __construct(
        private readonly PropertyQueryInterpreter $interpreter,
        private readonly PropertyMatchRanker $ranker,
        private readonly InventoryListingSearch $inventory,
        private readonly ProductEntitlement $entitlement,
    ) {}

    public function session(User $user, ?int $id = null): AiSearchSession
    {
        if ($id) {
            $session = AiSearchSession::query()->where('user_id', $user->id)->whereKey($id)->first();
            if ($session) {
                return $session;
            }
        }

        return AiSearchSession::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'tenant_id' => (int) $user->tenant_id,
                'product_slug' => app(\App\Support\ProductContext::class)->current()
                    ?? $this->entitlement->productsFor($user)[0]
                    ?? 'house_flipper',
                'criteria' => ['required' => [], 'preferred' => []],
            ],
        );
    }

    public function reply(User $user, AiSearchSession $session, string $message): array
    {
        $this->entitlement->assert($user, 'conversational_search', $session->product_slug);
        $message = Str::of($message)->replaceMatches('/[\x00-\x1F\x7F]+/u', ' ')->squish()->limit(2000, '')->toString();
        $criteria = $this->interpreter->interpret($message, $session->criteria ?? []);
        $filters = $this->interpreter->toSearchFilters($criteria);
        $intent = $criteria['intent'];

        $listings = $session->last_listings ?? [];
        $marketMeta = ['used' => false, 'status' => 'skipped'];

        if (in_array($intent, ['search', 'refine'], true)) {
            $inventory = $this->inventory->search($user, $filters);
            $market = $this->marketSearch($user, $filters);
            $marketMeta = $market['meta'];
            $listings = array_values([...$inventory, ...$market['items']]);
        }

        $ranked = $this->ranker->rank($listings, $criteria);
        $eligible = collect($ranked)->where('eligible', true)->values();
        $recommendation = in_array($intent, ['recommend', 'compare', 'search', 'refine'], true)
            ? $this->ranker->recommend($ranked)
            : ($session->last_recommendation ?? null);
        $comparison = $intent === 'compare' ? $this->ranker->compare($ranked) : [];
        $gap = $eligible->isEmpty() ? $this->ranker->missingExplanation($criteria, $ranked) : null;

        $answer = $this->compose($intent, $criteria, $eligible->all(), $recommendation, $comparison, $gap, $marketMeta);

        $session->fill([
            'criteria' => $criteria,
            'last_listings' => collect($ranked)->take(50)->all(),
            'last_recommendation' => $recommendation,
        ])->save();

        $session->messages()->create([
            'role' => 'user',
            'content' => $message,
            'criteria_snapshot' => $criteria,
        ]);
        $session->messages()->create([
            'role' => 'assistant',
            'content' => $answer,
            'criteria_snapshot' => $criteria,
            'listing_ids' => $eligible->pluck('id')->take(20)->values()->all(),
        ]);

        return [
            'session_id' => $session->id,
            'intent' => $intent,
            'answer' => $answer,
            'criteria' => $criteria,
            'filters' => $filters,
            'listings' => $eligible->take(20)->values()->all(),
            'recommendation' => $recommendation,
            'comparison' => $comparison,
            'no_match' => $gap,
            'market' => $marketMeta,
            'messages' => $session->messages()->latest('id')->limit(12)->get()->reverse()->values(),
        ];
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
