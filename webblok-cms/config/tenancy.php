<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant Storage Root
    |--------------------------------------------------------------------------
    | Each tenant gets storage/tenants/{slug}/ containing:
    |   - database.sqlite
    |   - media/
    |   - exports/
    */
    'storage_root' => storage_path('tenants'),

    /*
    |--------------------------------------------------------------------------
    | Tenant Database Connection Name
    |--------------------------------------------------------------------------
    */
    'connection' => 'tenant',

    /*
    |--------------------------------------------------------------------------
    | Tenant Migrations Path
    |--------------------------------------------------------------------------
    */
    'migrations_path' => database_path('migrations/tenant'),

    /*
    |--------------------------------------------------------------------------
    | Identification
    |--------------------------------------------------------------------------
    | How a request is mapped to a tenant: by subdomain, domain, or path prefix.
    */
    'identification' => [
        'by_subdomain' => true,
        'by_domain' => true,
        'by_path' => false,
    ],
];
