<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiResponseFeedback;
use App\Models\AiSearchMessage;
use App\Models\AiSearchSession;
use App\Models\AiUserPreference;
use App\Models\OrganizationMembership;
use App\Services\Ai\AiConversationService;
use App\Support\ProductContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiConversationController extends Controller
{
    public function __construct(private readonly AiConversationService $conversations) {}

    public function index(Request $request): JsonResponse
    {
        $product = app(ProductContext::class)->current($request);
        $sessions = AiSearchSession::query()
            ->where('user_id', $request->user()->id)
            ->when($product, fn ($query) => $query->where('product_slug', $product))
            ->where('status', 'active')
            ->latest('updated_at')
            ->limit(30)
            ->get(['id', 'title', 'product_slug', 'active_agent', 'updated_at']);

        return response()->json(['data' => $sessions]);
    }

    public function create(Request $request): JsonResponse
    {
        $data = $request->validate(['product' => ['nullable', 'string', 'max:40']]);
        $session = $this->conversations->createSession($request->user(), $data['product'] ?? null);

        return response()->json(['data' => $this->present($session)], 201);
    }

    public function show(Request $request): JsonResponse
    {
        $request->validate(['session_id' => ['nullable', 'integer', 'min:1']]);
        $sessionId = $request->filled('session_id') ? (int) $request->query('session_id') : null;
        $session = $this->conversations->session($request->user(), $sessionId);

        return response()->json(['data' => $this->present($session)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'min:3', 'max:2000'],
            'session_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $session = $this->conversations->session($request->user(), $data['session_id'] ?? null);

        return response()->json(['data' => $this->conversations->reply($request->user(), $session, $data['message'])]);
    }

    public function destroy(Request $request, AiSearchSession $session): JsonResponse
    {
        abort_unless((int) $session->user_id === (int) $request->user()->id, 404);
        $session->update(['status' => 'archived']);

        return response()->json(['message' => 'Conversa arquivada.']);
    }

    public function feedback(Request $request, AiSearchSession $session): JsonResponse
    {
        abort_unless((int) $session->user_id === (int) $request->user()->id, 404);
        $data = $request->validate([
            'message_id' => ['nullable', 'integer'],
            'rating' => ['required', 'in:helpful,not_helpful'],
            'reason' => ['nullable', 'string', 'max:80'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);
        $message = isset($data['message_id'])
            ? AiSearchMessage::query()->where('ai_search_session_id', $session->id)->where('role', 'assistant')->find($data['message_id'])
            : null;
        abort_if(isset($data['message_id']) && ! $message, 422, 'Mensagem não pertence à conversa.');
        $membership = OrganizationMembership::query()->where('user_id', $request->user()->id)->where('status', 'active')->first();
        $feedback = AiResponseFeedback::query()->create([
            'user_id' => $request->user()->id,
            'tenant_id' => $request->user()->tenant_id,
            'organization_id' => $membership?->organization_id,
            'ai_search_session_id' => $session->id,
            'ai_search_message_id' => $message?->id,
            'product_slug' => $session->product_slug,
            ...$data,
        ]);

        return response()->json(['data' => ['id' => $feedback->id, 'rating' => $feedback->rating]], 201);
    }

    public function preferences(Request $request): JsonResponse
    {
        $product = app(ProductContext::class)->current($request) ?? $request->user()->last_product_slug ?? 'house_flipper';
        $row = AiUserPreference::query()->where('user_id', $request->user()->id)->where('product_slug', $product)->first();

        return response()->json(['data' => ['enabled' => (bool) $row, 'preferences' => $row?->preferences ?? []]]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'preferences' => ['nullable', 'array'],
            'preferences.search_defaults' => ['nullable', 'array'],
            'preferences.search_defaults.required' => ['nullable', 'array'],
            'preferences.search_defaults.required.city' => ['nullable', 'string', 'max:120'],
            'preferences.search_defaults.required.state' => ['nullable', 'string', 'size:2'],
            'preferences.search_defaults.required.property_type' => ['nullable', 'in:apartment,house,commercial,land,other'],
            'preferences.search_defaults.required.max_price' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'preferences.search_defaults.required.min_bedrooms' => ['nullable', 'integer', 'min:0', 'max:100'],
            'preferences.search_defaults.required.min_bathrooms' => ['nullable', 'integer', 'min:0', 'max:100'],
            'preferences.search_defaults.required.min_parking' => ['nullable', 'integer', 'min:0', 'max:100'],
            'preferences.search_defaults.required.min_area_m2' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'preferences.search_defaults.preferred' => ['nullable', 'array'],
            'preferences.search_defaults.preferred.neighborhoods' => ['nullable', 'array', 'max:15'],
            'preferences.search_defaults.preferred.neighborhoods.*' => ['string', 'max:120'],
            'preferences.search_defaults.preferred.features' => ['nullable', 'array', 'max:15'],
            'preferences.search_defaults.preferred.features.*' => ['string', 'max:60'],
        ]);
        $product = app(ProductContext::class)->current($request) ?? $request->user()->last_product_slug ?? 'house_flipper';
        if (! $data['enabled']) {
            AiUserPreference::query()->where('user_id', $request->user()->id)->where('product_slug', $product)->delete();

            return response()->json(['data' => ['enabled' => false, 'preferences' => []]]);
        }
        $defaults = $data['preferences']['search_defaults'] ?? [];
        $preferences = [
            'search_defaults' => [
                'required' => collect($defaults['required'] ?? [])->only(['city', 'state', 'property_type', 'max_price', 'min_bedrooms', 'min_bathrooms', 'min_parking', 'min_area_m2'])->all(),
                'preferred' => collect($defaults['preferred'] ?? [])->only(['neighborhoods', 'features'])->all(),
            ],
        ];
        $membership = OrganizationMembership::query()->where('user_id', $request->user()->id)->where('status', 'active')->first();
        $row = AiUserPreference::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'product_slug' => $product],
            ['tenant_id' => $request->user()->tenant_id, 'organization_id' => $membership?->organization_id, 'preferences' => $preferences, 'consented_at' => now()],
        );

        return response()->json(['data' => ['enabled' => true, 'preferences' => $row->preferences]]);
    }

    private function present(AiSearchSession $session): array
    {
        $messages = $session->messages()->latest('id')->limit(30)->get()->reverse()->values();
        $lastAssistant = $messages->where('role', 'assistant')->last();

        return [
            'session_id' => $session->id,
            'title' => $session->title,
            'product_slug' => $session->product_slug,
            'agent' => $session->active_agent ? ['key' => $session->active_agent, 'label' => config('ai.agents.'.$session->active_agent.'.label')] : null,
            'criteria' => $session->criteria,
            'listings' => $session->last_listings ?? [],
            'recommendation' => $session->last_recommendation,
            'citations' => $lastAssistant?->citations ?? [],
            'tool_events' => $lastAssistant?->tool_events ?? [],
            'ui_blocks' => $lastAssistant?->ui_blocks ?? [],
            'assistant_message_id' => $lastAssistant?->id,
            'messages' => $messages,
        ];
    }
}
