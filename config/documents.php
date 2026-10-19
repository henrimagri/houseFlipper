<?php

return [
    'disk' => env('DOCUMENT_DISK', 'local'),
    'max_kb' => (int) env('DOCUMENT_MAX_KB', 10240),
    'extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'csv', 'xlsx', 'docx'],
    'antivirus' => [
        'enabled' => filter_var(env('DOCUMENT_ANTIVIRUS_ENABLED', false), FILTER_VALIDATE_BOOL),
        'binary' => env('DOCUMENT_ANTIVIRUS_BINARY', ''),
        'timeout' => (int) env('DOCUMENT_ANTIVIRUS_TIMEOUT', 30),
    ],
];
