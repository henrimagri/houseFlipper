<?php

return [
    'provider' => env('MAP_PROVIDER', 'openfreemap'),
    'openfreemap_style' => env('MAP_OPENFREEMAP_STYLE', 'https://tiles.openfreemap.org/styles/liberty'),
    'mapbox_token' => env('MAPBOX_ACCESS_TOKEN', ''),
    'nominatim_endpoint' => env('OSM_NOMINATIM_URL', 'https://nominatim.openstreetmap.org/search'),
    'user_agent' => env('MAP_USER_AGENT', 'FlipControl-Maps/1.0'),
    'modes' => [
        'properties' => ['feature' => 'map_mode_properties', 'implemented' => true],
        'lead_heat' => ['feature' => 'map_mode_lead_heat', 'implemented' => true],
        'price_m2' => ['feature' => 'map_mode_price_m2', 'implemented' => true],
        'opportunities' => ['feature' => 'map_mode_opportunities', 'implemented' => true],
        'liquidity' => ['feature' => 'map_mode_liquidity', 'implemented' => true],
        'auctions' => ['feature' => 'map_mode_auctions', 'implemented' => true, 'reason' => 'Catálogo proveniente apenas de arquivos oficiais importados e rastreáveis.'],
        'flipper' => ['feature' => 'map_mode_flipper', 'implemented' => true],
        'capture_potential' => ['feature' => 'map_mode_capture', 'implemented' => true],
    ],
];
