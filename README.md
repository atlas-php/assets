# Atlas Assets

**Atlas Assets** is a Laravel package that provides a unified system for uploading, organizing, and retrieving files across any storage backend. It centralizes asset metadata into a single table, supports model associations, and gives you full control over how file paths are generated.

## Table of Contents
- [Overview](#overview)
- [Installation](#installation)
- [Uploading Files](#uploading-files)
- [Restricting File Extensions](#restricting-file-extensions)
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

After installation publish the configuration and migrations:

```bash
php artisan vendor:publish --tag=atlas-assets-config
php artisan vendor:publish --tag=atlas-assets-migrations
```

Full installation steps: [Install Guide](./docs/Install.md)

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

## Restricting File Extensions

Atlas Assets can enforce whitelist and blocklist rules via `config/atlas-assets.php`:

```php
'uploads' => [
    'allowed_extensions' => ['pdf', 'png', 'jpg'], // optional whitelist
    'blocked_extensions' => ['exe', 'bat'],        // optional blocklist
],
```

If the whitelist is populated, uploads must match one of the allowed extensions. Blocklisted extensions are always rejected, even when a whitelist is configured.

You may scope a one-off whitelist by passing `allowed_extensions` to the upload helpers:

```php
$asset = Assets::upload($request->file('file'), [
    'allowed_extensions' => ['csv'], // overrides config whitelist for this call only
]);
```

Blocklisted extensions still apply when overriding the whitelist.

## Retrieving Files

Find an asset:
```php
$asset = Assets::find($id);
```

List assets for a model (returns an Eloquent builder so you control execution):
```php
$images = Assets::listForModel($post)->get();
```

Use fluent aliases for readability or build paginators directly:
```php
$images = Assets::forModel($post, ['label' => 'hero'])->limit(5)->get();
$userAssets = Assets::forUser(auth()->id())->paginate();
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

Update metadata:
```php
Assets::update($asset, [
    'name' => 'NewName.pdf',
    'label' => 'hero',
    'category' => 'images',
]);
```

Replace the underlying file:
```php
Assets::replace($asset, $request->file('new_file'), ['label' => 'updated']);
```

Soft delete and purge:
```php
Assets::delete($asset);
Assets::purge(); // removes all soft deleted assets
```

Enable immediate file deletion during a soft delete by setting `delete_files_on_soft_delete` to `true` in `config/atlas-assets.php`; otherwise, files remain on disk until purged.

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
- [PRD Overview](./docs/PRD/Atlas-Assets.md)
- [Example Usage](./docs/PRD/Example-Usage.md)
- [Full API Reference](./docs/Full-API.md)
- [Install Guide](./docs/Install.md)

## Contributing
See the [Contributing Guide](./CONTRIBUTING.md).  
All work must align with PRDs and agent workflow rules defined in [AGENTS.md](./AGENTS.md).

## License
MIT — see [LICENSE](./LICENSE).
