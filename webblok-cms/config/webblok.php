<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Platform Identity
    |--------------------------------------------------------------------------
    */
    'name' => env('WEBBLOK_NAME', 'WebBlok CMS'),
    'base_domain' => env('WEBBLOK_BASE_DOMAIN', 'webblok.io'),

    /*
    |--------------------------------------------------------------------------
    | Super Admin bootstrap credentials (seeded once)
    |--------------------------------------------------------------------------
    */
    'super_admin' => [
        'email' => env('WEBBLOK_SUPERADMIN_EMAIL', 'admin@webblok.test'),
        'password' => env('WEBBLOK_SUPERADMIN_PASSWORD', 'password'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Web-triggered provisioning / cron secret
    |--------------------------------------------------------------------------
    */
    'provision_secret' => env('WEBBLOK_PROVISION_SECRET', 'change-me'),
    'cron_secret' => env('WEBBLOK_CRON_SECRET', 'change-me'),

    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    | code => [direction, font]
    */
    'locales' => [
        'en' => ['dir' => 'ltr', 'font' => 'Inter', 'label' => 'English'],
        'ar' => ['dir' => 'rtl', 'font' => 'Cairo', 'label' => 'العربية'],
        'fr' => ['dir' => 'ltr', 'font' => 'Inter', 'label' => 'Français'],
        'es' => ['dir' => 'ltr', 'font' => 'Inter', 'label' => 'Español'],
        'de' => ['dir' => 'ltr', 'font' => 'Inter', 'label' => 'Deutsch'],
        'tr' => ['dir' => 'ltr', 'font' => 'Inter', 'label' => 'Türkçe'],
        'ur' => ['dir' => 'rtl', 'font' => 'Cairo', 'label' => 'اردو'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Blok Nesting Limits
    |--------------------------------------------------------------------------
    */
    'max_nesting_depth' => (int) env('WEBBLOK_MAX_DEPTH', 6),

    /*
    |--------------------------------------------------------------------------
    | Render Cache TTL (seconds)
    |--------------------------------------------------------------------------
    */
    'render_cache_ttl' => (int) env('WEBBLOK_RENDER_CACHE_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | Static Export
    |--------------------------------------------------------------------------
    */
    'export' => [
        'ttl_hours' => (int) env('WEBBLOK_EXPORT_TTL_HOURS', 48),
        'include_api_bridge' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Site Generation (V3) — offline-safe stub by default
    |--------------------------------------------------------------------------
    */
    'ai_driver' => env('WEBBLOK_AI_DRIVER', 'stub'),   // 'stub' | 'openai' | 'anthropic'
    'edge_publish' => env('WEBBLOK_EDGE_PUBLISH', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Dormant Tenants (V3) — lazy provisioning lever for zero-cost-at-scale
    |--------------------------------------------------------------------------
    */
    'dormant_tenants' => [
        'enabled' => env('WEBBLOK_DORMANT', true),
        'materialize_on_views' => (int) env('WEBBLOK_MATERIALIZE_VIEWS', 50),
    ],
];
