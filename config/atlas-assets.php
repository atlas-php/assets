<?php

declare(strict_types=1);

return [
    'disk' => env('ATLAS_ASSETS_DISK', env('FILESYSTEM_DISK', 'public')),

    'visibility' => env('ATLAS_ASSETS_VISIBILITY', 'public'),

    'delete_files_on_soft_delete' => env('ATLAS_ASSETS_DELETE_ON_SOFT_DELETE', false), // Delete storage files during soft delete when true.

    'routes' => [
        'stream' => [
            'enabled' => env('ATLAS_ASSETS_STREAM_ROUTE_ENABLED', true),
            'uri' => env('ATLAS_ASSETS_STREAM_ROUTE_URI', 'atlas-assets/stream/{asset}'),
            'name' => env('ATLAS_ASSETS_STREAM_ROUTE_NAME', 'atlas-assets.stream'),
            'middleware' => ['signed', \Illuminate\Routing\Middleware\SubstituteBindings::class],
        ],
    ],

    'tables' => [
        'assets' => env('ATLAS_ASSETS_TABLE', 'atlas_assets'),
    ],

    'database' => [
        'connection' => env('ATLAS_ASSETS_DATABASE_CONNECTION'),
    ],

    'path' => [
        'pattern' => '{model_type}/{model_id}/{file_name}.{extension}',

        'resolver' => null,
    ],

    'sort' => [
        'scopes' => ['model_type', 'model_id', 'type'],

        'resolver' => null,
    ],

    'uploads' => [
        'allowed_extensions' => [],

        'blocked_extensions' => [],

        'max_file_size' => 10 * 1024 * 1024,
    ],
];
