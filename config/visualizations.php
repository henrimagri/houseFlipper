<?php

return [
    'disk' => env('VISUALIZATION_DISK', 'local'),
    'max_kb' => (int) env('VISUALIZATION_MAX_KB', 25600),
    'extensions' => ['glb', 'gltf', 'jpg', 'jpeg', 'png', 'webp'],
    'model_extensions' => ['glb', 'gltf'],
    'image_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
];
