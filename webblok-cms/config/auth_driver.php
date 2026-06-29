<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Auth Driver
    |--------------------------------------------------------------------------
    | 'breeze'  — Laravel Breeze Blade (default, works offline)
    | 'gas'     — Global Authenticator System (external API)
    |
    | Switch by changing AUTH_DRIVER in .env then re-uploading config/ via FTP.
    | No code changes are required to switch.
    |--------------------------------------------------------------------------
    */

    'driver' => env('AUTH_DRIVER', 'breeze'),

    'gas' => [
        'base_url' => env('GAS_BASE_URL', 'https://gas.example.com'),
        'api_key' => env('GAS_API_KEY', ''),
        'app_id' => env('GAS_APP_ID', ''),
        'timeout_seconds' => (int) env('GAS_TIMEOUT', 10),
        'user_sync' => env('GAS_USER_SYNC', true),
        'callback_route' => '/auth/gas/callback',
    ],
];
