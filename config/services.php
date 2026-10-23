<?php

return [
    'http' => ['ca_bundle' => env('HTTP_CA_BUNDLE')],

    'portal_scraper' => [
        'node_binary' => env('NODE_BINARY'),
        'chrome_path' => env('CHROME_PATH'),
        'disable_sandbox' => env('SCRAPER_DISABLE_SANDBOX', false),
    ],

    'apify' => [
        'token' => env('APIFY_API_TOKEN'),
        'request_timeout' => env('APIFY_REQUEST_TIMEOUT', 55),
        'actor_timeout' => env('APIFY_ACTOR_TIMEOUT', 45),
        'max_results_per_provider' => env('APIFY_MAX_RESULTS_PER_PROVIDER', 25),
        'providers' => [
            'zap' => ['label' => 'ZAP Imóveis', 'actor_id' => env('APIFY_ACTOR_ZAP', 'avorio/zap-imoveis-scraper')],
            'vivareal' => ['label' => 'VivaReal', 'actor_id' => env('APIFY_ACTOR_VIVAREAL', 'scrapers_lat/vivareal-scraper')],
            'imovelweb' => ['label' => 'ImovelWeb', 'actor_id' => env('APIFY_ACTOR_IMOVELWEB', 'piotrv1001/imovelweb-listings-scraper')],
            'loft' => ['label' => 'Loft', 'actor_id' => env('APIFY_ACTOR_LOFT', 'gio21/loft-scraper')],
            'quintoandar' => ['label' => 'QuintoAndar', 'actor_id' => env('APIFY_ACTOR_QUINTOANDAR', 'solidcode/quintoandar-scraper')],
            'facebook' => ['label' => 'Facebook Marketplace', 'actor_id' => env('APIFY_ACTOR_FACEBOOK', 'apify/facebook-marketplace-scraper')],
            'casamineira' => ['label' => 'Casa Mineira', 'actor_id' => env('APIFY_ACTOR_CASA_MINEIRA')],
        ],
    ],

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
        'webhook_secret' => env('RESEND_WEBHOOK_SECRET'),
        'native_webhooks_enabled' => env('RESEND_NATIVE_WEBHOOKS_ENABLED', false),
    ],

    'mercadolivre' => [
        'token' => env('MERCADO_LIVRE_ACCESS_TOKEN'),
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

    'open_data' => [
        'ckan_catalogs' => [
            [
                'key' => 'sp',
                'states' => ['SP'],
                'cities' => ['sao paulo'],
                'package_search' => env('SP_DADOS_ABERTOS_CKAN_URL', 'https://dados.prefeitura.sp.gov.br/api/3/action/package_search'),
                'datastore_search' => env('SP_DADOS_ABERTOS_DATASTORE_URL', 'https://dados.prefeitura.sp.gov.br/api/3/action/datastore_search'),
            ],
            [
                'key' => 'fortaleza',
                'states' => ['CE'],
                'cities' => ['fortaleza'],
                'package_search' => env('FORTALEZA_CKAN_URL', 'https://dados.fortaleza.ce.gov.br/api/3/action/package_search'),
                'datastore_search' => env('FORTALEZA_CKAN_DATASTORE_URL', 'https://dados.fortaleza.ce.gov.br/api/3/action/datastore_search'),
            ],
            [
                'key' => 'rj',
                'states' => ['RJ'],
                'cities' => ['rio de janeiro'],
                'package_search' => env('RJ_CKAN_URL', 'https://www.data.rio/api/3/action/package_search'),
                'datastore_search' => env('RJ_CKAN_DATASTORE_URL', 'https://www.data.rio/api/3/action/datastore_search'),
            ],
            [
                'key' => 'nacional',
                'states' => [],
                'cities' => [],
                'package_search' => env('DADOS_GOV_CKAN_URL', 'https://dados.gov.br/api/3/action/package_search'),
                'datastore_search' => env('DADOS_GOV_DATASTORE_URL', 'https://dados.gov.br/api/3/action/datastore_search'),
            ],
        ],
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

    'mercado_pago' => [
        'access_token' => env('MERCADO_PAGO_ACCESS_TOKEN'),
        'webhook_secret' => env('MERCADO_PAGO_WEBHOOK_SECRET'),
    ],

    'stripe' => [
        'native_webhooks_enabled' => env('STRIPE_NATIVE_WEBHOOKS_ENABLED', false),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

];
