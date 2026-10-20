<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Maps\GeocodeService;
use App\Services\Maps\MapQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MapListingsController extends Controller
{
    public function __construct(
        private readonly MapQueryService $maps,
        private readonly GeocodeService $geocode,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mode' => ['nullable', 'string', 'max:40'],
            'product' => ['nullable', 'string', 'max:40'],
            'geometry' => ['nullable', 'array'],
            'filters' => ['nullable', 'array'],
        ]);

        return response()->json($this->maps->query($request->user(), $data));
    }

    public function geocode(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'max:180']]);

        return response()->json(['data' => $this->geocode->search($data['q'])]);
    }

    public function saveSearch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'mode' => ['nullable', 'string', 'max:40'],
            'product' => ['nullable', 'string', 'max:40'],
            'geometry' => ['nullable', 'array'],
            'filters' => ['nullable', 'array'],
        ]);

        return response()->json(['data' => $this->maps->saveSearch($request->user(), $data)], 201);
    }

    public function config(): JsonResponse
    {
        return response()->json([
            'provider' => config('maps.provider'),
            'style_url' => config('maps.openfreemap_style'),
            'mapbox_configured' => filled(config('maps.mapbox_token')),
            'modes' => config('maps.modes'),
            'buildings_3d' => 'fill-extrusion via OpenFreeMap/OpenMapTiles quando a camada de edificações estiver disponível.',
        ]);
    }
}
