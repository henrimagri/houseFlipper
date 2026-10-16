<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PropertyMarketDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyMarketAnalysisController extends Controller
{
    public function __construct(private readonly PropertyMarketDataService $marketData) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'selected' => ['required', 'array'],
            'selected.title' => ['nullable', 'string', 'max:255'],
            'selected.address' => ['nullable', 'string', 'max:255'],
            'selected.neighborhood' => ['nullable', 'string', 'max:120'],
            'selected.city' => ['nullable', 'string', 'max:120'],
            'selected.state' => ['nullable', 'string', 'max:2'],
            'selected.area_m2' => ['nullable', 'numeric', 'min:0'],
            'selected.bedrooms' => ['nullable', 'integer', 'min:0'],
            'selected.bathrooms' => ['nullable', 'integer', 'min:0'],
            'selected.parking_spaces' => ['nullable', 'integer', 'min:0'],
            'selected.asking_price' => ['nullable', 'numeric', 'min:0'],
            'selected.property_type' => ['nullable', 'string', 'in:apartment,house,commercial'],
        ]);

        return response()->json($this->marketData->analyze($data['selected']));
    }
}
