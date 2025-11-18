# Atlas Assets

Atlas Assets is a lightweight, unified system for storing and retrieving files in Laravel. It provides a single Asset model, a simple API, and flexible path resolution—all while remaining completely storage‑agnostic.

## Table of Contents
- [Overview](#overview)
- [Asset Data Model](#asset-data-model)
- [Core Responsibilities](#core-responsibilities)
- [What It Does Not Do](#what-it-does-not-do)
- [Storage & Pathing](#storage--pathing)
- [Services](#services)
- [Configuration](#configuration)

## Overview
Atlas Assets centralizes file handling so your application doesn’t need to. Every upload becomes an Asset record with metadata stored in one table. Files can belong to models, users, or nothing at all, and can be stored on any Laravel filesystem disk. Path generation is fully configurable using patterns or callbacks.

## Asset Data Model
Atlas Assets stores all metadata in a single table (default: `atlas_assets`).

Primary Eloquent model: `Atlas\Assets\Models\Asset`

Relationships exposed by the model:
- `model(): MorphTo` — back-reference to any owning model via the `model_type` / `model_id` columns.
- `user(): BelongsTo` — optional relationship to the authenticated user model configured in the consuming app.

| Field                     | Summary                                               |
|---------------------------|-------------------------------------------------------|
| `id`                      | Primary key                                           |
| `group_id`                | Optional external grouping/tenancy key               |
| `user_id`                 | Optional owner                                       |
| `model_type` / `model_id` | Optional polymorphic association                     |
| `file_mime_type`          | MIME type detected from the uploaded file            |
| `file_ext`                | Lowercase file extension (no dot)                    |
| `file_path`               | Storage path                                         |
| `file_size`               | Bytes                                                |
| `name`                    | Display name                                         |
| `original_file_name`      | Client filename                                      |
| `label` / `category`      | Optional classification                              |
| Timestamps + soft deletes | Lifecycle fields                                     |

Assets can be free‑standing or attached to any model. When attached, the owning
model should declare a morph relationship named `model` to match the asset’s
internal `morphTo` call.

`group_id` acts as a lightweight foreign key for multi-tenant or account-based
relationships. Consuming apps may set it to any identifier (e.g., account or
organization ID) and use it when scoping assets or generating custom storage
paths. It is entirely optional and independent from `user_id`.

### Example Polymorphic Setup

```php
use Atlas\Assets\Models\Asset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Post extends Model
{
    public function assets(): MorphMany
    {
        return $this->morphMany(Asset::class, 'model');
    }

    public function heroImage(): MorphOne
    {
        return $this->morphOne(Asset::class, 'model')->where('label', 'hero');
    }
}

class Submission extends Model
{
    public function attachments(): MorphMany
    {
        return $this->morphMany(Asset::class, 'model')
            ->where('category', 'submission');
    }
}
```

Each model defines the `morphMany`/`morphOne` relationship using the shared
`model` morph name. Assets can then be uploaded through the `AssetService` or
`Assets` facade using `uploadForModel($post, $file)` or
`uploadForModel($submission, $file)` to automatically populate the polymorphic
columns.

## Core Responsibilities
### 1. Central Asset Storage
- One unified table for all files
- Optional model and user association

### 2. File Operations
- Upload, replace, soft delete, purge
- Generate temporary URLs (S3 or other disks)
- Validate existence and read file contents

### 3. Metadata Management
- Track size, type, path, and names
- Support labels and categories
- Rename assets without moving files (unless replaced)

### 4. Flexible Pathing
- Pattern placeholders (`{model_id}`, `{uuid}`, `{extension}`, etc.)
- Optional callback resolver for full control
- Automatic cleanup of empty path segments

## What It Does Not Do
Atlas Assets intentionally avoids extra concerns:

- Image processing or thumbnailing
- CDN/caching behavior
- Version history for files
- Authentication or authorization
- Defining meaning for labels/categories

## Storage & Pathing
### Disk & Visibility
- Works with any Laravel disk (S3, local, Spaces, etc.)
- Visibility defaults to `public`

### Pattern-Based Pathing
Example pattern:

```
{model_type}/{model_id}/{file_name}.{extension}
```

### Callback-Based Pathing
For full custom logic:

```php
PathConfigurator::useCallback(fn ($model, $file, $attributes) =>
    'uploads/' . ($attributes['user_id'] ?? 'anon') . '/' . uniqid() . '.' .
    $file->getClientOriginalExtension()
);
```

Use `PathConfigurator::clear()` to revert to the configured pattern.

## Services
### AssetService
Handles creation and updates:
- `upload()`, `uploadForModel()`
- `update()`, `replace()`

### AssetRetrievalService
Read operations:
- `find()`, `forModel()`, `forUser()`, `listForModel()`, `listForUser()`, `buildModelQuery()`, `buildUserQuery()`
- `download()`, `exists()`
- `temporaryUrl()`

### AssetCleanupService
Cleanup operations:
- `delete()`
- `purge()`

## Configuration
Defined in `config/atlas-assets.php`:
- Disk + visibility
- Delete-on-soft-delete flag
- Table name + DB connection
- Path pattern or resolver callback
- Upload filtering via `uploads.allowed_extensions` and `uploads.blocked_extensions`
- Maximum upload size via `uploads.max_file_size` (defaults to 10 MB)

Environment overrides:
```
ATLAS_ASSETS_DISK=
ATLAS_ASSETS_VISIBILITY=
ATLAS_ASSETS_DELETE_ON_SOFT_DELETE=
```

### Extension Filtering Rules
- `uploads.allowed_extensions`: optional whitelist (case-insensitive) restricting uploads to specific extensions.
- `uploads.blocked_extensions`: optional blocklist that always rejects the listed extensions.
- Entries accept values with or without a leading dot; they are normalized to lowercase without dots.
- Blocklisted extensions always win—even when the file also exists in the whitelist or a per-upload override.
- Passing `allowed_extensions` to `AssetService::upload`, `uploadForModel`, or the matching Facade method overrides the configured whitelist for that single call. The override is treated as a strict allowed list; the provided extension must exist in the array.

### File Size Limits
- `uploads.max_file_size` defines the maximum upload size in bytes (default: `10 * 1024 * 1024`, or 10 MB). Set to `null` to disable size enforcement globally.
- Per-upload overrides may set `max_upload_size` to a specific byte limit or `null` to bypass limits for that call.
- Oversized uploads must raise a dedicated exception so consuming apps can gracefully notify users.

## Also See
- [Full API Reference](../Full-API.md)
- [Example Usage](./Example-Usage.md)
- [Installation Guide](../Install.md)
- [Package README](../../README.md)
 
