<?php

$envOrigins = array_filter(array_map(
    static fn (string $origin) => trim($origin),
    explode(',', env('FRONTEND_ORIGINS', env('FRONTEND_URL', '')))
));

$defaultOrigins = [
    'http://localhost:5173',
    'http://127.0.0.1:5173',
    'https://student.crru.ac.th', // ✅ เพิ่มไว้กันพลาด
];

$frontendOrigins = array_values(array_unique(array_filter(array_merge($envOrigins, $defaultOrigins))));

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', '*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        env('FRONTEND_ORIGIN', 'http://localhost:5173'),
        'http://localhost:5173',
        'http://localhost:5174',
        'http://localhost:5175',
        'http://127.0.0.1:5173',
        'http://127.0.0.1:5174',
        'http://127.0.0.1:5175',
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,

    // ✅ ถ้าใช้ Bearer token -> false ได้
    // ✅ ถ้าใช้ Sanctum แบบ cookie/SPA -> ต้อง true
    'supports_credentials' => true,
];
