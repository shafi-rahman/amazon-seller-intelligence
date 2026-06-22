<?php

return [
    /*
     * Paths CORS applies to. Sanctum's SPA cookie auth needs the CSRF-cookie and
     * the API under the same-origin SPA, so scope to those.
     */
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],

    'allowed_methods' => ['*'],

    /*
     * Restrict to the SPA origin(s). Set CORS_ALLOWED_ORIGINS in .env as a
     * comma-separated list for production (e.g. https://app.your-domain.com).
     * Defaults to the local dev origin.
     */
    'allowed_origins' => array_filter(array_map('trim', explode(
        ',',
        env('CORS_ALLOWED_ORIGINS', 'http://localhost:7801')
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Required for cookie-based SPA auth (Sanctum).
    'supports_credentials' => true,
];
