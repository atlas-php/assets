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
- [Reordering Assets](#reordering-assets)
- [Renaming an Asset](#renaming-an-asset)
- [Deleting Assets](#deleting-assets)
- [CRUD Controller Example](#crud-controller-example)
- [Using Custom Sort Resolver](#using-custom-sort-resolver)
- [Using Custom Path Resolvers](#using-custom-path-resolvers)
- [Purging Soft-Deleted Assets](#purging-soft-deleted-assets)
- [Also See](#also-see)

## Basic Upload
```php
use Atlas\Assets\Facades\Assets;

$asset = Assets::upload($request->file('document'));
```

## Upload for a Model
```php
use Atlas\Assets\Facades\Assets;

$asset = Assets::uploadForModel($post, $request->file('image'));
```

## Upload with Attributes
You may pass labels, categories, or ownership:

```php
enum DocumentAssetType: int
{
    case InvoicePdf = 1;
    case ContractPdf = 2;
}

use Atlas\Assets\Facades\Assets;

$asset = Assets::upload($request->file('document'), [
    'group_id'   => $request->user()->account_id,
    'user_id'    => auth()->id(),
    'label'      => 'invoice',
    'category'   => 'billing',
    'name'       => 'January Invoice.pdf',
    'type'       => DocumentAssetType::InvoicePdf->value,
    'sort_order' => 2,
]);
```

Use `group_id` for multi-tenant scenarios (accounts, organizations, etc.) where
assets must be grouped independently from `user_id`. The `type` attribute is an
unsigned tinyint, so back it with a PHP enum whose values fall between `0` and
`255`. `type` participates in the default sort scope. Provide `sort_order` when
you need to explicitly position the asset; omit it to rely on the configured
sort resolver.

## Restricting Extensions
Configure whitelists/blocklists in `config/atlas-assets.php`:

```php
'uploads' => [
    'allowed_extensions' => ['pdf', 'png'],
    'blocked_extensions' => ['exe'],
    'max_file_size'      => 10 * 1024 * 1024, // bytes
],
```

Blocklists always apply. For single uploads that need a one-off whitelist, pass `allowed_extensions`:

```php
use Atlas\Assets\Facades\Assets;

$asset = Assets::upload($request->file('export'), [
    'allowed_extensions' => ['csv'],
]);
```

Increase or disable the size limit per upload:

```php
use Atlas\Assets\Facades\Assets;

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
use Atlas\Assets\Facades\Assets;

$assets = Assets::forModel($post)->get();
```

Or use fluent aliases that keep the builder fluent:

```php
use Atlas\Assets\Facades\Assets;

$assets = Assets::forModel($post)->paginate();
```

With filters and limits:

```php
use Atlas\Assets\Facades\Assets;

$images = Assets::forModel($post, [
    'label' => 'featured',
])->limit(3)->get();
```

### For a user
```php
use Atlas\Assets\Facades\Assets;

$userAssets = Assets::forUser(auth()->id(), [
    'category' => 'documents',
])->get();

$userAssets = Assets::forUser(auth()->id())->cursorPaginate();
```

## Retrieving & Downloading
### Find an asset
```php
use Atlas\Assets\Facades\Assets;

$asset = Assets::find($id);
```

### Download file contents
```php
use Atlas\Assets\Facades\Assets;

$contents = Assets::download($asset);
```

## Temporary URLs
Useful when using S3 or another remote disk:

```php
use Atlas\Assets\Facades\Assets;

$url = Assets::temporaryUrl($asset, minutes: 10);
```

## Replacing an Asset
Replace the physical file and update metadata:

```php
use Atlas\Assets\Facades\Assets;

$new = Assets::replace($asset, $request->file('new_document'));
```

## Updating Labels & Categories
Update metadata without touching the stored file:

```php
use Atlas\Assets\Facades\Assets;

Assets::update($asset, [
    'label'    => 'receipt',
    'category' => 'purchases',
]);
```

## Reordering Assets
```php
use Atlas\Assets\Facades\Assets;

Assets::update($asset, ['sort_order' => 5]);
```

Configure the automatic behavior via `atlas-assets.sort.scopes` or register a
custom resolver to increment based on fields like `group_id`, `type`, or
`category`. Setting `sort.scopes` to `null` disables automatic increments (all
records remain at the database default of `0` unless you pass `sort_order`).

## Renaming an Asset
```php
use Atlas\Assets\Facades\Assets;

Assets::update($asset, ['name' => 'NewFileName.pdf']);
```

This changes the metadata but **not the storage path** unless a replace occurs.

## Deleting Assets
Soft delete the asset record (file may remain depending on configuration), or force delete to immediately remove from storage and the database:

```php
use Atlas\Assets\Facades\Assets;

Assets::delete($asset);
Assets::delete($asset, true); // force delete + remove file immediately
```

## CRUD Controller Example
The following resource-style controller demonstrates how consumers can layer Atlas Assets into their existing Laravel routes. The controller uses implicit route model binding for the parent model (`Post`) and the asset, mirrors the default `store`, `update`, and `destroy` signatures, and only replaces a stored file when a new upload is provided.

```php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Atlas\Assets\Facades\Assets;
use Atlas\Assets\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;

class PostAssetController extends Controller
{
    public function store(Request $request, Post $post): JsonResource
    {
        $validated = $request->validate([
            'file'      => ['required', 'file', 'max:10240'],
            'label'     => ['nullable', 'string', 'max:255'],
            'category'  => ['nullable', 'string', 'max:255'],
            'type'      => ['nullable', 'integer', 'between:0,255'],
            'sort_order'=> ['nullable', 'integer', 'min:0'],
        ]);

        $asset = Assets::uploadForModel(
            $post,
            $validated['file'],
            collect($validated)->except('file')->toArray()
        );

        return JsonResource::make($asset)->additional(['message' => 'Asset created.']);
    }

    public function update(Request $request, Post $post, Asset $asset): JsonResource
    {
        $validated = $request->validate([
            'file'      => ['nullable', 'file', 'max:10240'],
            'label'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'category'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'type'      => ['sometimes', 'nullable', Rule::in(range(0, 255))],
            'sort_order'=> ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);

        $attributes = collect($validated)->except('file')->toArray();

        $asset = isset($validated['file'])
            ? Assets::replace($asset, $validated['file'], $attributes, $post)
            : Assets::update($asset, $attributes, null, $post);

        return JsonResource::make($asset)->additional(['message' => 'Asset updated.']);
    }

    public function destroy(Post $post, Asset $asset): JsonResource
    {
        Assets::delete($asset, forceDelete: request()->boolean('force'));

        return JsonResource::make(['message' => 'Asset deleted.']);
    }
}
```

Register the controller using Laravel's resource routes:

```php
Route::apiResource('posts.assets', PostAssetController::class)->shallow();
```

This provides `POST /posts/{post}/assets`, `PUT/PATCH /assets/{asset}`, and `DELETE /assets/{asset}` endpoints. Metadata-only updates skip file replacement, keeping uploads immutable unless a new file is submitted.

## Using Custom Sort Resolver
Define a resolver in `config/atlas-assets.php` to control how `sort_order` is generated:

```php
'sort' => [
    'scopes' => ['model_type', 'model_id', 'type'],
    'resolver' => function (?Illuminate\Database\Eloquent\Model $model, array $context): int {
        // Example: prioritize category, then weight by group_id
        $categoryWeights = [
            'hero'      => 1000,
            'thumbnail' => 500,
            'document'  => 100,
        ];

        $base = $categoryWeights[$context['category'] ?? 'document'] ?? 0;

        return $base + (int) (($context['group_id'] ?? 0) * 10);
    },
],
```

- `scopes` controls which columns define the auto-incrementing group.
- `resolver` receives the model (if any) plus a metadata array (e.g., `group_id`, `user_id`, `category`, `type`).
- Returning an integer overrides the default sequential behavior; passing `sort_order` on write calls still bypasses this resolver.

## Using Custom Path Resolvers
### Pattern Override in `config/atlas-assets.php`
```php
'path' => [
    'pattern' => '{user_id}/{model_type}/{uuid}.{extension}',
],
```

### Config-Based Callback Override
```php
'path' => [
    'resolver' => function (?Illuminate\Database\Eloquent\Model $model, Illuminate\Http\UploadedFile $file, array $attributes): string {
        return 'uploads/' . ($attributes['user_id'] ?? 'anon') . '/' . uniqid() . '.' .
            $file->extension();
    },
],
```

### Type-specific Routing
Using a backed enum such as `App\Enums\ProductAssetType`:
```php
use App\Enums\ProductAssetType;

'path' => [
    'resolver' => function (?Illuminate\Database\Eloquent\Model $model, Illuminate\Http\UploadedFile $file, array $attributes): string {
        $directory = match ($attributes['type'] ?? null) {
            ProductAssetType::ProductImage->value => 'products',
            ProductAssetType::FormImage->value => 'forms',
            default => 'shared',
        };

        return $directory . '/' . uniqid() . '.' . $file->extension();
    },
],
```

### Reset to default pattern
```php
'path' => ['resolver' => null];
```

## Purging Soft-Deleted Assets
Remove all soft-deleted assets and optionally delete the physical files:

```php
use Atlas\Assets\Facades\Assets;

$count = Assets::purge();
```

## Also See
- [Overview](./Atlas-Assets.md)
- [Full API Reference](../Full-API.md)
- [Installation Guide](../Install.md)
- [README](../../README.md)
