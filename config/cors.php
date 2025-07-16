<?php

// config/cors.php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(array_merge([
        'http://localhost:3000',
        'http://localhost:5173', // Vite dev server
        'http://127.0.0.1:3000',
        'http://127.0.0.1:5173',
        env('FRONTEND_URL', 'http://localhost:3000'),
        env('FRONTEND_URL_PROD', 'https://lamap241.vercel.app'),
        // Domaines de production
        'https://lamap241.vercel.app',
        'https://lamap241-git-main-lamap241.vercel.app', // Branches Vercel
        'https://www.lamap241.com',
        'https://lamap241.com',
    ], explode(',', env('FRONTEND_URLS', '')))),

    'allowed_origins_patterns' => [
        '#^https://lamap241.*\.vercel\.app$#', // Pattern pour toutes les URLs Vercel
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];