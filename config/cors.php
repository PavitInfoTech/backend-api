<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | This file controls how your application responds to cross-origin
    | requests. Adjust `allowed_origins` to include your frontend origin(s).
    |
    */

    'paths' => [
        '/*',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => ['*'],

    // Use your frontend URL here (e.g. https://charadesai.com) or set FRONTEND_URL in .env
    // Defaults to localhost:3000 for development. In production, ensure FRONTEND_URL is set.
    'allowed_origins' => array_filter([env('FRONTEND_URL'), env('API_DOMAIN')]),

    'allowed_origins_patterns' => [],

    // Allow all headers (important for preflight when client sends Content-Type or custom headers)
    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    // Cache preflight response for 0 (seconds) by default — increase if desired
    'max_age' => 0,

    // If you rely on cookies or Authorization header across subdomains, set true
    'supports_credentials' => true,
];
