# Atlas Assets — Example Usage

This document provides practical examples demonstrating how to use Atlas Assets for uploading, organizing, retrieving, updating, and deleting files.

## Table of Contents
- [Basic Upload](#basic-upload)
- [Upload for a Model](#upload-for-a-model)
- [Upload with Attributes](#upload-with-attributes)
- [Restricting Extensions](#restricting-extensions)
- [Listing Assets](#listing-assets)
- [Retrieving & Downloading](#retrieving--downloading)
- [Temporary URLs](#temporary-urls)
- [Replacing an Asset](#replacing-an-asset)
- [Updating Labels & Categories](#updating-labels--categories)
- [Renaming an Asset](#renaming-an-asset)
- [Deleting Assets](#deleting-assets)
- [Using Custom Path Resolvers](#using-custom-path-resolvers)
- [Purging Soft-Deleted Assets](#purging-soft-deleted-assets)

## Basic Upload
```php
use Atlas\Assets\Facades\Assets;

$asset = Assets::upload($request->file('document'));
```

## Upload for a Model
```php
$asset = Assets::uploadForModel($post, $request->file('image'));
```

## Upload with Attributes
You may pass labels, categories, or ownership:

```php
$asset = Assets::upload($request->file('document'), [
    'group_id' => $request->user()->account_id,
    'user_id' => auth()->id(),
    'label'   => 'invoice',
    'category'=> 'billing',
    'name'    => 'January Invoice.pdf',
    'type'    => 'invoice_pdf',
    'sort_order' => 2,
]);

Use `group_id` for multi-tenant scenarios (accounts, organizations, etc.) where
assets must be grouped independently from `user_id`. The `type` attribute lets
you tag assets with consumer-defined enums (e.g., `hero`, `invoice_pdf`) and is
included in the default sort scope. Provide `sort_order` when you need to
explicitly position the asset; omit it to rely on the configured sort resolver.
```

## Restricting Extensions
Configure whitelists/blocklists in `config/atlas-assets.php`:

```php
'uploads' => [
    'allowed_extensions' => ['pdf', 'png'],
    'blocked_extensions' => ['exe'],
    'max_file_size' => 10 * 1024 * 1024, // bytes
],
```

Blocklists always apply. For single uploads that need a one-off whitelist, pass `allowed_extensions`:

```php
$asset = Assets::upload($request->file('export'), [
    'allowed_extensions' => ['csv'],
]);
```

Increase or disable the size limit per upload:

```php
$asset = Assets::upload($request->file('video'), [
    'max_upload_size' => 50 * 1024 * 1024, // 50 MB limit for this call
]);

$asset = Assets::upload($request->file('archive'), [
    'max_upload_size' => null, // unlimited for this call
]);
```

## Listing Assets
### For a model
```php
$assets = Assets::listForModel($post)->get();
```

Or use fluent aliases that keep the builder fluent:

```php
$assets = Assets::forModel($post)->paginate();
```

With filters and limits:

```php
$images = Assets::listForModel($post, [
    'label' => 'featured',
])->limit(3)->get();
```

### For a user
```php
$userAssets = Assets::listForUser(auth()->id(), [
    'category' => 'documents',
])->get();

$userAssets = Assets::forUser(auth()->id())->cursorPaginate();
```

## Retrieving & Downloading
### Find an asset
```php
$asset = Assets::find($id);
```

### Download file contents
```php
$contents = Assets::download($asset);
```

## Temporary URLs
Useful when using S3 or another remote disk:

```php
$url = Assets::temporaryUrl($asset, minutes: 10);
```

## Replacing an Asset
Replace the physical file and update metadata:

```php
$new = Assets::replace($asset, $request->file('new_document'));
```

## Updating Labels & Categories
Update metadata without touching the stored file:

```php
Assets::update($asset, [
    'label' => 'receipt',
    'category' => 'purchases',
]);
```

## Reordering Assets
```php
Assets::update($asset, ['sort_order' => 5]);
```

Configure the automatic behavior via `atlas-assets.sort.scopes` or register a
custom resolver to increment based on fields like `group_id`, `type`, or
`category`. Setting `sort.scopes` to `null` disables automatic increments (all
records remain at the database default of `0` unless you pass `sort_order`).

## Renaming an Asset
```php
Assets::update($asset, ['name' => 'NewFileName.pdf']);
```

This changes the metadata but **not the storage path** unless a replace occurs.

## Deleting Assets
Soft delete the asset record (file may remain depending on configuration):

```php
Assets::delete($asset);
```

## Using Custom Path Resolvers
### Pattern Override in `config/atlas-assets.php`
```php
'path' => [
    'pattern' => '{user_id}/{model_type}/{uuid}.{extension}',
]
```

### Runtime Callback Override
```php
use Atlas\Assets\Support\PathConfigurator;

PathConfigurator::useCallback(function ($model, $file, $attributes) {
    return 'uploads/' . ($attributes['user_id'] ?? 'anon') . '/' . uniqid() . '.' .
        $file->getClientOriginalExtension();
});
```

### Reset to default pattern
```php
PathConfigurator::clear();
```

## Purging Soft-Deleted Assets
Remove all soft-deleted assets and optionally delete the physical files:

```php
$count = Assets::purge();
```

## Also See
- [PRD Overview](./Atlas-Assets.md)
- [Full API Reference](../Full-API.md)
- [Installation Guide](../Install.md)
- [README](../../README.md)
