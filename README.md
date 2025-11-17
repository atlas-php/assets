# Atlas Assets

**Atlas Assets** is a Laravel package that provides a unified system for uploading, organizing, and retrieving files across any storage backend. It centralizes asset metadata into a single table, supports model associations, and gives you full control over how file paths are generated.

## Table of Contents
- [Overview](#overview)
- [Installation](#installation)
- [Uploading Files](#uploading-files)
- [Retrieving Files](#retrieving-files)
- [Managing Assets](#managing-assets)
- [Custom Pathing](#custom-pathing)
- [Also See](#also-see)
- [Contributing](#contributing)
- [License](#license)

## Overview
Atlas Assets removes the complexity of file storage by offering one consistent API for every type of upload. Files can be stored on S3, local disks, or any Laravel-supported driver. Each file becomes an `Asset` record containing metadata such as size, path, type, and optional labels or categories.  
It also supports polymorphic relationships, allowing assets to be tied to any model in your application.

## Installation
```bash
composer require atlas-php/assets
```

To publish configuration:

```bash
php artisan vendor:publish --tag=atlas-assets-config
```

Full installation steps: [Install Guide](./Install-Assets.md)

## Uploading Files

Basic upload:
```php
use Atlas\Assets\Facades\Assets;

$asset = Assets::upload($request->file('document'));
```

Attach an asset to a model:
```php
$asset = Assets::uploadForModel($post, $request->file('image'));
```

Upload with attributes:
```php
$asset = Assets::upload($request->file('file'), [
    'user_id' => auth()->id(),
    'label'   => 'cover',
    'category'=> 'images',
]);
```

## Retrieving Files

Find an asset:
```php
$asset = Assets::find($id);
```

List assets for a model:
```php
$images = Assets::listForModel($post);
```

Temporary URL (S3, Spaces, etc.):
```php
$url = Assets::temporaryUrl($asset, 10);
```

Download file contents:
```php
$content = Assets::download($asset);
```

## Managing Assets

Replace the underlying file:
```php
$newAsset = Assets::replace($asset, $request->file('new_file'));
```

Rename metadata:
```php
Assets::rename($asset, 'NewName.pdf');
```

Soft delete:
```php
Assets::delete($asset);
```

Purge soft-deleted assets:
```php
Assets::purge();
```

## Custom Pathing

Atlas Assets supports both pattern-based pathing and fully custom resolvers.

### Pattern Example
```php
'pattern' => '{model_type}/{model_id}/{uuid}.{extension}'
```

### Callback Example
```php
use Atlas\Assets\Support\PathConfigurator;

PathConfigurator::useCallback(fn ($model, $file, $attributes) =>
    'uploads/' . ($attributes['user_id'] ?? 'anon') . '/' . uniqid() . '.' .
    $file->getClientOriginalExtension()
);
```

Revert to default:
```php
PathConfigurator::clear();
```

## Also See
- [Overview](./Overview.md)
- [Example Usage](./Example-Usage-Assets.md)
- [Full API Reference](./Full-API.md)
- [Install Guide](./Install-Assets.md)

## Contributing
See the [Contributing Guide](./CONTRIBUTING.md).  
All work must align with PRDs and agent workflow rules defined in [AGENTS.md](./AGENTS.md).

## License
MIT — see [LICENSE](./LICENSE).
