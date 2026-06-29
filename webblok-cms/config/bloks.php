<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Blok Definition Schema Directory
    |--------------------------------------------------------------------------
    */
    'schema_path' => base_path('blok_definitions'),

    /*
    |--------------------------------------------------------------------------
    | Blok Categories
    |--------------------------------------------------------------------------
    */
    'categories' => [
        'layout' => 'Layout',
        'content' => 'Content',
        'dashboard' => 'Dashboard',
        'forms' => 'Forms',
        'skeleton' => 'Skeleton',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sections a blok may be placed into
    |--------------------------------------------------------------------------
    */
    'sections' => ['header', 'body', 'footer', 'sidebar'],

    /*
    |--------------------------------------------------------------------------
    | Form Wizard field types (19 supported)
    |--------------------------------------------------------------------------
    */
    'field_types' => [
        'text', 'textarea', 'richtext', 'number', 'toggle', 'select',
        'multiselect', 'radio', 'checkbox', 'color', 'date', 'datetime',
        'image', 'gallery', 'file', 'url', 'icon', 'repeater', 'code',
    ],
];
