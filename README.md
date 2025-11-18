# Atlas Assets

**Atlas Assets** provides a simple, unified API for uploading, organizing, and retrieving files across any Laravel storage disk. Every upload becomes a first‑class `Asset` with consistent metadata, model associations, and configurable pathing.

## Table of Contents
- [Overview](#overview)
- [Installation](#installation)
- [Uploading Files](#uploading-files)
- [Restricting File Extensions](#restricting-file-extensions)
- [Limiting File Size](#limiting-file-size)
- [Retrieving Files](#retrieving-files)
- [Managing Assets](#managing-assets)
- [Custom Pathing](#custom-pathing)
- [Contributing](#contributing)
- [License](#license)

## Overview
Atlas Assets removes the friction of handling files by giving you a single, consistent interface for storing uploads, attaching them to models, retrieving URLs, and managing metadata. It supports any Laravel disk, polymorphic relationships, temporary URLs, and custom path generation.

## Installation
```bash
composer require atlas-php/assets
```

Publish config and migrations:
```bash
php artisan vendor:publish --tag=atlas-assets-config
php artisan vendor:publish --tag=atlas-assets-migrations
```

For full installation steps: [Install Guide](./docs/Install.md)

## Uploading Files

Basic upload:
```php
$asset = Assets::upload($request->file('file'));
```

Attach to a model:
```php
$asset = Assets::uploadForModel($post, $request->file('image'));
```

Upload with attributes:
```php
$asset = Assets::upload($request->file('file'), [
    'label' => 'cover',
    'category' => 'images',
]);
```

## Restricting File Extensions

Define whitelist and blocklist rules in `config/atlas-assets.php`:
```php
'uploads' => [
    'allowed_extensions' => ['pdf', 'png', 'jpg'],
    'blocked_extensions' => ['exe', 'bat'],
],
```

Override per upload:
```php
Assets::upload($file, [
    'allowed_extensions' => ['csv'],
]);
```

## Limiting File Size

Default max upload size is **10 MB**. Configure globally:
```php
'uploads' => [
    'max_file_size' => 20 * 1024 * 1024, // bytes
],
```

Override per upload:
```php
Assets::upload($file, [
    'max_upload_size' => 50 * 1024 * 1024,
]);
```

Disable for a single upload:
```php
Assets::upload($file, [
    'max_upload_size' => null,
]);
```

## Retrieving Files

Find an asset:
```php
$asset = Assets::find($id);
```

List for a model:
```php
$images = Assets::forModel($post)->get();
```

Temporary URL:
```php
$url = Assets::temporaryUrl($asset, 10);
```

Download contents:
```php
$content = Assets::download($asset);
```

## Managing Assets

Update metadata:
```php
Assets::update($asset, ['label' => 'hero']);
```

Replace file:
```php
Assets::replace($asset, $request->file('new'));
```

Soft delete and purge:
```php
Assets::delete($asset);
Assets::purge();
```

## Custom Pathing

Pattern-based pathing:
```php
'pattern' => '{model_type}/{model_id}/{uuid}.{extension}'
```

Callback-based:
```php
PathConfigurator::useCallback(fn ($model, $file, $attrs) =>
    'uploads/' . ($attrs['user_id'] ?? 'anon') . '/' . uniqid() . '.' . $file->extension()
);
```

Reset to default:
```php
PathConfigurator::clear();
```


## Also See
- [PRD Overview](./docs/PRD/Atlas-Assets.md)
- [Example Usage](./docs/PRD/Example-Usage.md)
- [Full API Reference](./docs/Full-API.md)
- [Install Guide](./docs/Install.md)

## Contributing
See the [Contributing Guide](./CONTRIBUTING.md).

## License
MIT — see [LICENSE](./LICENSE).
