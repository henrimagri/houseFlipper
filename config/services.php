<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'mercadolivre' => [
        'token' => env('MERCADO_LIVRE_ACCESS_TOKEN'),
    ],

    'google_maps' => [
        'key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    'openstreetmap' => [
        'nominatim_endpoint' => env('OSM_NOMINATIM_URL', 'https://nominatim.openstreetmap.org/search'),
        'overpass_endpoint' => env('OSM_OVERPASS_URL', 'https://overpass-api.de/api/interpreter'),
        'overpass_fallback_endpoint' => env('OSM_OVERPASS_FALLBACK_URL', 'https://overpass.kumi.systems/api/interpreter'),
    ],

    'cartorios' => [
        'endpoint' => env('CARTORIOS_API_URL'),
        'token' => env('CARTORIOS_API_TOKEN'),
    ],

    'itbi' => [
        'endpoint' => env('ITBI_API_URL'),
        'sp_endpoint' => env('SP_ITBI_API_URL'),
        'sp_catalog' => env('SP_DADOS_ABERTOS_CKAN_URL', 'https://dados.prefeitura.sp.gov.br/api/3/action/package_search'),
        'token' => env('ITBI_API_TOKEN'),
    ],

    'prefeituras' => [
        'endpoint' => env('PREFEITURA_TRANSACOES_API_URL'),
        'sp_catalog' => env('SP_DADOS_ABERTOS_CKAN_URL', 'https://dados.prefeitura.sp.gov.br/api/3/action/package_search'),
        'token' => env('PREFEITURA_TRANSACOES_API_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
