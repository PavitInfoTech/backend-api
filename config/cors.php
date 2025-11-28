<?php

use Illuminate\Support\Str;

/**
 * Application CORS configuration.
 *
 * This configuration ensures CORS headers are applied for our API when served
 * from either a subdomain (api.example.com) or the `/api` prefixed routes.
 */

return [
    // Match everything so the middleware will run for all routes. You can limit
    // this to a set of paths such as 'api/*' and 'auth/*' if preferred.
    'paths' => ['api/*', 'sanctum/csrf-cookie', '*'],

    'allowed_methods' => ['*'],

    // Use explicit origins instead of wildcard when using `supports_credentials`.
    // Prefer to set `FRONTEND_URL` without a trailing slash in your .env.
    'allowed_origins' => env('CORS_ALLOWED_ORIGINS') ? explode(',', env('CORS_ALLOWED_ORIGINS')) : [rtrim(env('FRONTEND_URL', '*'), '/')],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Set to true if you need to allow cookies (SPA session / sanctum cookie flows).
    'supports_credentials' => env('CORS_SUPPORTS_CREDENTIALS', true),
];
