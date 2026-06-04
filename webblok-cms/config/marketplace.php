<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Remote Marketplace Manifest URL
    |--------------------------------------------------------------------------
    | Set to a JSON endpoint to enable community marketplace items.
    | Leave empty to use only official (bundled) items.
    */
    'remote_manifest_url' => env('MARKETPLACE_REMOTE_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | Allow Custom Uploads
    |--------------------------------------------------------------------------
    | Tenants can upload their own .zip theme/template packages.
    */
    'allow_custom_uploads' => env('MARKETPLACE_ALLOW_UPLOADS', true),

    /*
    |--------------------------------------------------------------------------
    | Max custom upload size (MB)
    |--------------------------------------------------------------------------
    */
    'max_upload_mb' => (int) env('MARKETPLACE_MAX_UPLOAD_MB', 50),

    /*
    |--------------------------------------------------------------------------
    | Official marketplace items path
    |--------------------------------------------------------------------------
    */
    'official_path' => base_path('marketplace/official'),

    /*
    |--------------------------------------------------------------------------
    | Community cache path
    |--------------------------------------------------------------------------
    */
    'community_cache_path' => storage_path('marketplace'),
];
