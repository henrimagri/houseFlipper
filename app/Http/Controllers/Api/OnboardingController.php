<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductOnboarding;
use App\Services\Ai\ProductEntitlement;
use App\Support\OnboardingDefinition;
use App\Support\ProductContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function __construct(
        private readonly ProductEntitlement $entitlement,
        private readonly ProductContext $context,
    ) {}

    public function show(Request $request, string $product): JsonResponse
    {
        $slug = $this->assertProduct($request, $product);
        $row = ProductOnboarding::query()->firstOrCreate(
            ['user_id' => $request->user()->id, 'product_slug' => $slug],
            ['tenant_id' => $request->user()->tenant_id, 'tour_version' => OnboardingDefinition::VERSION, 'current_step' => 0, 'completed_steps' => []],
        );
        if ((int) $row->tour_version !== OnboardingDefinition::VERSION) {
            $row->forceFill([
                'tour_version' => OnboardingDefinition::VERSION,
                'current_step' => 0,
                'completed_steps' => [],
                'dismissed' => false,
                'completed_at' => null,
            ])->save();
        }

        return response()->json(['data' => $this->payload($row)]);
    }

    public function update(Request $request, string $product): JsonResponse
    {
        $slug = $this->assertProduct($request, $product);
        $allowed = array_column(OnboardingDefinition::steps($slug), 'id');
        $data = $request->validate([
            'current_step' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'completed_steps' => ['sometimes', 'array'],
            'completed_steps.*' => ['string', 'max:40'],
            'dismissed' => ['sometimes', 'boolean'],
            'completed' => ['sometimes', 'boolean'],
            'tour_version' => ['sometimes', 'integer', 'in:'.OnboardingDefinition::VERSION],
        ]);
        $completed = array_values(array_intersect($data['completed_steps'] ?? [], $allowed));
        $row = ProductOnboarding::query()->firstOrNew([
            'user_id' => $request->user()->id,
            'product_slug' => $slug,
        ]);
        $row->tenant_id = $request->user()->tenant_id;
        $row->tour_version = OnboardingDefinition::VERSION;
        if (array_key_exists('current_step', $data)) {
            $row->current_step = $data['current_step'];
        }
        if (array_key_exists('completed_steps', $data)) {
            $row->completed_steps = $completed;
        }
        if (array_key_exists('dismissed', $data)) {
            $row->dismissed = $data['dismissed'];
        }
        if (array_key_exists('completed', $data) && $data['completed'] === false) {
            $row->completed_at = null;
            $row->current_step = $data['current_step'] ?? 0;
        } elseif (! empty($data['completed']) || (isset($data['completed_steps']) && count($completed) === count($allowed) && count($allowed) > 0)) {
            $row->completed_at = $row->completed_at ?? now();
            $row->current_step = count($allowed);
        }
        $row->save();

        return response()->json(['data' => $this->payload($row)]);
    }

    private function assertProduct(Request $request, string $product): string
    {
        $slug = $this->context->normalize($product);
        abort_if(! $slug, 422, 'Produto inválido.');
        $this->entitlement->assertProduct($request->user(), $slug);

        return $slug;
    }

    private function payload(ProductOnboarding $row): array
    {
        $steps = OnboardingDefinition::steps($row->product_slug);

        return [
            'product_slug' => $row->product_slug,
            'tour_version' => (int) $row->tour_version,
            'current_step' => (int) $row->current_step,
            'completed_steps' => array_values($row->completed_steps ?? []),
            'dismissed' => (bool) $row->dismissed,
            'completed_at' => $row->completed_at?->toIso8601String(),
            'steps' => $steps,
        ];
    }
}
