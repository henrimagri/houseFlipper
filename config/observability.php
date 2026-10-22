<?php

return [
    'enabled' => env('PLATFORM_OBSERVABILITY_ENABLED', true),
    'retention' => ['api' => 30, 'security' => 90, 'auth' => 90, 'audit' => 730, 'analytics' => 365, 'email' => 30],
    'max_window_days' => 93,
    'sample_limit' => 10000,
    'admin_per_minute' => 60,
    'mfa' => 'not_configured',
];
