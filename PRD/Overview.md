# Atlas Assets

Atlas Assets is a standalone Laravel package that provides a unified system for storing, managing, and retrieving uploaded files. It maintains a single authoritative `assets` table, supports polymorphic associations, and gives consumers full control over where files are stored and how storage paths are resolved. While optimized for AWS S3, it is fully storage-agnostic and works with any Laravel filesystem disk.

## Table of Contents

* [Overview](#overview)
* [Responsibilities](#responsibilities)
* [Non-Responsibilities](#non-responsibilities)
* [Storage Model](#storage-model)
* [Path Resolution](#path-resolution)
* [API Methods](#api-methods)
* [Database Schema](#database-schema)
* [Configuration](#configuration)

## Overview

Atlas Assets acts as a general-purpose asset manager for Laravel applications. It abstracts all file interactions—uploading, replacing, deleting, downloading—through a consistent API while storing metadata in a centralized database record. Assets may optionally belong to any model through a polymorphic relationship and may include user-defined labels or categories.

The package focuses on S3 as the primary storage backend but supports any Laravel driver. Consumers define dynamic or static storage paths and can structure asset organization however they prefer.

## Responsibilities

### Centralized Asset Storage

* Maintain a single `assets` table for all uploaded files.
* Support optional ownership via `user_id`.
* Support polymorphic association through `model_type` and `model_id`.

### File Management

* Upload new files to configured storage.
* Replace existing assets while updating metadata.
* Soft-delete assets and optionally purge physical files.
* Provide secure download URLs or temporary S3 URLs.
* Retrieve asset metadata and file contents.

### Metadata & Organization

* Store file name, type, size, and path.
* Allow consumer-defined `label` and `category`.
* Allow renaming without changing file location.

### Storage-Agnostic File Handling

* Rely entirely on Laravel’s filesystem abstraction.
* Support S3, local, DigitalOcean Spaces, or any custom driver.

### Dynamic Pathing

* Allow path patterns using placeholders such as:

    * `{model_type}`
    * `{model_id}`
    * `{user_id}`
    * `{extension}`
* Allow custom callbacks for full programmatic path resolution.
* Ensure consistent folder structure for related assets.

## Non-Responsibilities

Atlas Assets does not:

* Perform image manipulation or generate thumbnails.
* Provide CDN configuration or caching layers.
* Maintain version history of files.
* Enforce authorization or access policies (left to the consumer).
* Define domain meaning of labels/categories.

The package manages files and metadata only.

## Storage Model

### Primary Storage Concepts

* **Disk:** Configurable filesystem disk, default `s3`.
* **Visibility:** Default `public`, configurable.
* **Path:** Determined by path resolver logic; stored as `file_path`.
* **Lifecycle:** Asset record created on upload; soft deleted on removal.

### Replacement Behavior

* Consumers may choose:

    * delete old file on replace, or
    * keep old file and simply update path and metadata.

### Download Handling

* Download is abstracted behind:

    * raw `Storage::disk()->get()`
    * or `temporaryUrl()` for signed S3 URLs.

## Path Resolution

### Placeholder-Based Resolution

Consumers can define a path pattern such as:

```
{model_type}/{model_id}/{uuid}.{extension}
```

Supported placeholders:

* `{model_type}`
* `{model_id}`
* `{user_id}`
* `{original_name}`
* `{extension}`
* `{date:Y/m}`
* `{random}` or `{uuid}`

### Callback-Based Resolution

Alternatively, a closure can be used:

```php
'path_resolver' => function ($model, $file, $attributes) {
    return sprintf(
        '%s/%s/%s.%s',
        strtolower(class_basename($model)),
        $model->id,
        Str::uuid(),
        $file->getClientOriginalExtension()
    );
};
```

### No Model Use Case

Assets can exist with no model. Path rules must tolerate null model contexts.

## API Methods

### Uploading

* `upload(UploadedFile $file, array $attributes = []): Asset`
* `uploadForModel(Model $model, UploadedFile $file, array $attributes = []): Asset`

### Accessing

* `find(int|string $id): ?Asset`
* `listForModel(Model $model, array $filters = []): Collection`
* `listForUser(int|string $userId, array $filters = []): Collection`
* `temporaryUrl(Asset $asset, int $minutes = 5): string`
* `download(Asset $asset): StreamedResponse|string`

### Updating & Replacing

* `replace(Asset $asset, UploadedFile $file): Asset`
* `rename(Asset $asset, string $name): Asset`
* `updateLabel(Asset $asset, ?string $label): Asset`
* `updateCategory(Asset $asset, ?string $category): Asset`

### Removal

* `delete(Asset $asset, bool $deleteFile = false): void`
* `purge(): int`
  Permanently deletes soft-deleted assets and physical files.

### Utility

* `resolvePath(UploadedFile $file, ?Model $model = null, array $attributes = []): string`
* `disk(): FilesystemAdapter`
* `exists(Asset $asset): bool`

## Database Schema

### `assets`

| Column       | Type               | Description                   |
|--------------|--------------------|-------------------------------|
| `id`         | bigint             | Primary key                   |
| `user_id`    | bigint nullable    | Optional owner                |
| `model_type` | string nullable    | Polymorphic type              |
| `model_id`   | bigint nullable    | Polymorphic id                |
| `file_type`  | string             | MIME or custom                |
| `file_path`  | string             | Stored disk path              |
| `file_size`  | bigint             | In bytes                      |
| `name`       | string             | Display name or uploaded name |
| `original_file_name` | string     | Original client filename      |
| `label`      | string nullable    | Optional label                |
| `category`   | string nullable    | Custom category               |
| `created_at` | timestamp          |                               |
| `updated_at` | timestamp          |                               |
| `deleted_at` | timestamp nullable | Soft deletes                  |

## Configuration

### `config/atlas-assets.php`

Key options include:

```php
return [
    'disk' => env('ATLAS_ASSETS_DISK', 's3'),

    'visibility' => env('ATLAS_ASSETS_VISIBILITY', 'public'),

    'delete_files_on_soft_delete' => env('ATLAS_ASSETS_DELETE_ON_SOFT_DELETE', false),

    'tables' => [
        'assets' => env('ATLAS_ASSETS_TABLE', 'atlas_assets'),
    ],

    'database' => [
        'connection' => env('ATLAS_ASSETS_DATABASE_CONNECTION'),
    ],

    'path' => [
        'pattern' => '{model_type}/{model_id}/{uuid}.{extension}',

        'resolver' => null,
    ],
];
```

Consumers may override:

* disk
* visibility
* default path pattern
* custom callback resolver
* file deletion behavior
* database table names for package models
* database connection overrides
