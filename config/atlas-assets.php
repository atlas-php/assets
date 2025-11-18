<?php

declare(strict_types=1);

return [
    'disk' => env('ATLAS_ASSETS_DISK', 's3'),

    'visibility' => env('ATLAS_ASSETS_VISIBILITY', 'public'),

    'delete_files_on_soft_delete' => env('ATLAS_ASSETS_DELETE_ON_SOFT_DELETE', false), // Delete storage files during soft delete when true.

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
];
