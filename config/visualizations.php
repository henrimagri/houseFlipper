<?php

return [
    'disk' => env('VISUALIZATION_DISK', 'local'),
    'max_image_kb' => (int) env('VISUALIZATION_MAX_IMAGE_KB', env('VISUALIZATION_MAX_KB', 25600)),
    'max_model_kb' => (int) env('VISUALIZATION_MAX_MODEL_KB', 65536),
    'extensions' => ['glb', 'gltf', 'jpg', 'jpeg', 'png', 'webp', 'ply', 'json'],
    'model_extensions' => ['glb', 'gltf'],
    'image_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
    'max_capture_images' => (int) env('VISUALIZATION_MAX_CAPTURE_IMAGES', 120),
    'max_processing_storage_mb' => (int) env('VISUALIZATION_MAX_PROCESSING_STORAGE_MB', 512),
];
