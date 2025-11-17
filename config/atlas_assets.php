<?php

declare(strict_types=1);

return [
    'disk' => env('ASSETS_DISK', 's3'),

    'visibility' => env('ASSETS_VISIBILITY', 'public'),

    'delete_files_on_soft_delete' => env('ASSETS_DELETE_ON_SOFT_DELETE', false),

    'path' => [
        'pattern' => '{model_type}/{model_id}/{uuid}.{extension}',

        'resolver' => null,
    ],
];
