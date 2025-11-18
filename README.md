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
    'group_id' => $request->input('account_id'),
    'label' => 'cover',
    'category' => 'images',
    'type' => 'hero',
    'sort_order' => 2,
]);
```

`group_id` is an optional unsigned big integer column you can use to scope
assets to accounts, teams, or any additional relationship independent of
`user_id`. Use the `type` attribute to tag assets with consumer-defined enums
such as `hero`, `thumbnail`, or `invoice`; it participates in the default sort
scope. Pass `sort_order` to control ordering manually; omit it to let Atlas
Assets calculate the next position automatically within the configured scope.

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

## Sorting Assets

Assets include a `sort_order` column that defaults to an auto-incremented value
within the scope defined by `config('atlas-assets.sort.scopes')`
(`model_type`, `model_id`, `type` by default). Customize the scope or register a
resolver callback:

```php
'sort' => [
    'scopes' => ['group_id'],
    'resolver' => fn ($model, array $context) => ($context['group_id'] ?? 0) * 10,
],
```

Set `scopes` to `null` to disable automatic increments entirely (assets will use
the database default of `0` unless you provide a value).

Pass `sort_order` to `upload`, `uploadForModel`, or `update` whenever you need
manual control:

```php
Assets::update($asset, ['sort_order' => 12]);
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
