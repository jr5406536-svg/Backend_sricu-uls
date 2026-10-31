<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'https://frontend-sricu-uls.onrender.com',
        'http://localhost:5173',
        'http://localhost:9000',
        'http://127.0.0.1:5173',
        'http://127.0.0.1:9000',
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
