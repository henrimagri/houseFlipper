<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Ai\AiConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiConversationController extends Controller
{
    public function __construct(private readonly AiConversationService $conversations) {}

    public function show(Request $request): JsonResponse
    {
        $session = $this->conversations->session($request->user(), $request->integer('session_id') ?: null);

        return response()->json([
            'data' => [
                'session_id' => $session->id,
                'criteria' => $session->criteria,
                'listings' => $session->last_listings ?? [],
                'recommendation' => $session->last_recommendation,
                'messages' => $session->messages()->latest('id')->limit(20)->get()->reverse()->values(),
            ],
        ]);
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
}
