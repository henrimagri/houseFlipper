<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Marketplace\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function __construct(private readonly BillingService $billing) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->billing->snapshot($request->user())]);
    }

    public function trial(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product' => ['required', 'string', 'max:40'],
        ]);
        $row = $this->billing->startTrial($request->user(), $data['product']);

        return response()->json([
            'data' => [
                'product_slug' => $row->product_slug,
                'status' => $row->status->value,
                'trial_starts_at' => $row->trial_starts_at?->toIso8601String(),
                'trial_ends_at' => $row->trial_ends_at?->toIso8601String(),
            ],
        ]);
    }

    public function cancel(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product' => ['required', 'string', 'max:40'],
        ]);
        $row = $this->billing->cancel($request->user(), $data['product']);

        return response()->json([
            'data' => [
                'product_slug' => $row->product_slug,
                'status' => $row->status->value,
            ],
        ]);
    }

    public function checkout(Request $request): JsonResponse
    {
        $request->validate([
            'product' => ['required', 'string', 'max:40'],
            'plan' => ['required', 'string', 'max:80'],
            'billing_cycle' => ['required', 'in:monthly,quarterly,annual'],
        ]);

        return response()->json([
            'status' => 'not_wired',
            'message' => 'Checkout Mercado Pago/Stripe não está configurado. Use o teste de 7 dias.',
        ], 503);
    }

    public function webhook(Request $request, string $provider): JsonResponse
    {
        return app(PlatformProviderWebhookController::class)->receive($request, $provider);
    }
}
