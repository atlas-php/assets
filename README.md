# Atlas Assets

[![Build](https://github.com/atlas-php/assets/actions/workflows/tests.yml/badge.svg)](https://github.com/atlas-php/assets/actions/workflows/tests.yml)
[![License](https://img.shields.io/github/license/atlas-php/assets.svg)](LICENSE)

**Atlas Assets** is a unified Laravel system for uploading, organizing, and retrieving files with a consistent API, predictable metadata, and configurable pathing. It removes scattered file-handling logic and replaces it with a clean, reliable Assets service.

## Table of Contents
- [Overview](#overview)
- [Installation](#installation)
- [Uploading Files](#uploading-files)
- [Restricting File Extensions](#restricting-file-extensions)
- [Limiting File Size](#limiting-file-size)
- [Retrieving Files](#retrieving-files)
- [Managing Assets](#managing-assets)
- [Sorting Assets](#sorting-assets)
- [Custom Pathing](#custom-pathing)
- [Also See](#also-see)
- [Contributing](#contributing)
- [License](#license)

## Overview
Atlas Assets provides a single `Asset` model and service layer for all file operations in Laravel. Every file uploaded becomes a fully‑tracked asset with consistent metadata, optional model/user associations, configurable sorting, and customizable storage paths. It works with any Laravel filesystem disk and includes first‑class support for temporary URLs, polymorphic relationships, and per‑upload validation rules.

## Installation
```bash
composer require atlas-php/assets
```

Publish configuration and migrations:
```bash
php artisan vendor:publish --tag=atlas-assets-config
php artisan vendor:publish --tag=atlas-assets-migrations
```

Atlas Assets automatically follows your application's default filesystem disk (via `FILESYSTEM_DISK`/`filesystems.default`), so it works with local storage, S3, Spaces, or any driver Laravel supports. Override it per-environment using `ATLAS_ASSETS_DISK`.

Full steps: [Install Guide](./docs/Install.md)

## Uploading Files

### Basic upload
```php
$asset = Assets::upload($request->file('file'));
```

### Upload and attach to a model
```php
$asset = Assets::uploadForModel($post, $request->file('image'));
```

### Upload with custom attributes
Define your own PHP backed enum to keep `type` values readable while storing compact integers:
```php
enum DocumentAssetType: int
{
    case Hero = 1;
    case Gallery = 2;
    case Invoice = 3;
}
```

```php
$asset = Assets::upload($request->file('file'), [
    'group_id' => $request->input('account_id'),
    'label' => 'cover',
    'category' => 'images',
    'type' => DocumentAssetType::Hero->value,
    'sort_order' => 2,
]);
```

- **group_id**: scope assets to multi‑tenant entities (accounts, teams, organizations).
- **type**: unsigned tinyint (0‑255). Back it with a PHP enum to document meaning and keep values consistent.
- **sort_order**: override auto-sorting when manual control is needed.

## Restricting File Extensions

Global configuration (`config/atlas-assets.php`):
```php
'uploads' => [
    'allowed_extensions' => ['pdf', 'png', 'jpg'],
    'blocked_extensions' => ['exe', 'bat'],
],
```

Per‑upload override:
```php
Assets::upload($file, [
    'allowed_extensions' => ['csv'],
]);
```

Blocklists always take precedence.

## Limiting File Size

Default max size: **10 MB**.

Global override:
```php
'uploads' => [
    'max_file_size' => 20 * 1024 * 1024,
];
```

Per‑upload:
```php
Assets::upload($file, [
    'max_upload_size' => 50 * 1024 * 1024,
]);
```

Disable per upload:
```php
Assets::upload($file, [
    'max_upload_size' => null,
]);
```

## Retrieving Files

### Find an asset
```php
$asset = Assets::find($id);
```

### Get files for a model
```php
$images = Assets::forModel($post)->get();
```

### Temporary URL
```php
$url = Assets::temporaryUrl($asset, 10);
```

### Download file contents
```php
$content = Assets::download($asset);
```

### Stream fallback route
When your storage disk cannot generate temporary URLs, Assets falls back to a signed route named `atlas-assets.stream`. Customize or disable that route through `config/atlas-assets.php`:

```php
'routes' => [
    'stream' => [
        'enabled' => true,
        'uri' => 'atlas-assets/stream/{asset}',
        'name' => 'atlas-assets.stream',
        'middleware' => ['signed', SubstituteBindings::class],
    ],
],
```

Environment overrides:
```
ATLAS_ASSETS_STREAM_ROUTE_ENABLED=true
ATLAS_ASSETS_STREAM_ROUTE_URI=media/assets/{asset}
ATLAS_ASSETS_STREAM_ROUTE_NAME=media.assets.stream
```

Disable the built-in route when you prefer to register your own streaming endpoint (be sure to keep the configured route name in sync so signed URLs resolve correctly).

## Managing Assets

### Update asset metadata
```php
Assets::update($asset, ['label' => 'hero']);
```

### Replace file while keeping metadata
```php
Assets::replace($asset, $request->file('new'));
```

### Delete and purge
```php
Assets::delete($asset); // soft delete + optional file removal via config
Assets::delete($asset, true); // hard delete record + storage file immediately
Assets::purge(); // permanently delete soft-deleted assets + files
```

## Sorting Assets
Atlas Assets automatically assigns `sort_order` values based on configured scopes:

```php
'sort' => [
    'scopes' => ['model_type', 'model_id', 'type'],
]
```

Customize scope or register a custom resolver:

```php
'sort' => [
    'scopes' => ['group_id'],
    'resolver' => function ($model, array $context) {
        return ($context['group_id'] ?? 0) * 10;
    },
];
```

Disable auto-sorting:
```php
'sort' => ['scopes' => null];
```

Manual override:
```php
Assets::update($asset, ['sort_order' => 12]);
```

## Custom Pathing

### Pattern-based example
```php
'path' => [
    'pattern' => '{model_type}/{model_id}/{uuid}.{extension}',
],
```

### Configured callback example
```php
'path' => [
    'resolver' => function (?Illuminate\Database\Eloquent\Model $model, Illuminate\Http\UploadedFile $file, array $attrs): string {
        return 'uploads/' . ($attrs['user_id'] ?? 'anon') . '/' . uniqid() . '.' . $file->extension();
    },
],
```

Reset to pattern behavior:
```php
'path' => ['resolver' => null];
```

## Also See
- [Overview](./docs/PRD/Atlas-Assets.md)
- [Example Usage](./docs/PRD/Example-Usage.md)
- [Full API Reference](./docs/Full-API.md)
- [Install Guide](./docs/Install.md)

## Contributing
See the [Contributing Guide](./CONTRIBUTING.md).

## License
MIT — see [LICENSE](./LICENSE).
