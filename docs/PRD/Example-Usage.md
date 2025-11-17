# Atlas Assets — Example Usage

This document provides practical examples demonstrating how to use Atlas Assets for uploading, organizing, retrieving, updating, and deleting files.

## Table of Contents
- [Basic Upload](#basic-upload)
- [Upload for a Model](#upload-for-a-model)
- [Upload with Attributes](#upload-with-attributes)
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
    'user_id' => auth()->id(),
    'label'   => 'invoice',
    'category'=> 'billing',
    'name'    => 'January Invoice.pdf',
]);
```

## Listing Assets
### For a model
```php
$assets = Assets::listForModel($post);
```

With filters:

```php
$images = Assets::listForModel($post, [
    'label' => 'featured',
]);
```

### For a user
```php
$userAssets = Assets::listForUser(auth()->id(), [
    'category' => 'documents',
]);
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
