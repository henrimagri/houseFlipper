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
            'selected' => ['required', 'array:title,address,neighborhood,city,state,area_m2,bedrooms,bathrooms,parking_spaces,asking_price,property_type'],
            'selected.title' => ['nullable', 'string', 'max:255'],
            'selected.address' => ['nullable', 'string', 'max:255'],
            'selected.neighborhood' => ['nullable', 'string', 'max:120'],
            'selected.city' => ['nullable', 'string', 'max:120'],
            'selected.state' => ['nullable', 'string', 'size:2'],
            'selected.area_m2' => ['nullable', 'numeric', 'between:0,99999999.99'],
            'selected.bedrooms' => ['nullable', 'integer', 'between:0,100'],
            'selected.bathrooms' => ['nullable', 'integer', 'between:0,100'],
            'selected.parking_spaces' => ['nullable', 'integer', 'between:0,100'],
            'selected.asking_price' => ['nullable', 'numeric', 'between:0,999999999999'],
            'selected.property_type' => ['nullable', 'string', 'in:apartment,house,commercial'],
        ]);

        return response()->json($this->marketData->analyze($data['selected']));
    }
}
