# Atlas Assets

Atlas Assets provides a unified, storage‑agnostic system for handling all uploaded files in Laravel. It standardizes how assets are stored, associated with models, retrieved, sorted, and managed—removing the need for repetitive, project‑specific file logic.

## Table of Contents
- [Overview](#overview)
- [Asset Data Model](#asset-data-model)
- [Core Responsibilities](#core-responsibilities)
- [What It Does Not Do](#what-it-does-not-do)
- [Storage & Pathing](#storage--pathing)
- [Services](#services)
- [Configuration](#configuration)
- [Also See](#also-see)

## Overview
Atlas Assets centralizes all file behavior behind a single `Asset` model with consistent metadata and a clean API. Every upload becomes a first‑class asset stored in the `atlas_assets` table. Assets may belong to users, other models, or exist standalone, with configurable path generation, sorting rules, and upload validation.

The system is designed to:
- Simplify file handling across large applications
- Offer consistent semantics for associating files with any model
- Provide predictable metadata and sorting behavior
- Support both simple and advanced use cases (multi‑tenant grouping, custom pathing, dynamic sort logic)

## Asset Data Model
Atlas Assets stores all metadata in one table (default: `atlas_assets`).

Primary Eloquent model: `Atlas\Assets\Models\Asset`

### Key Columns
| Field                     | Description                                                                 |
|---------------------------|-----------------------------------------------------------------------------|
| id                        | Primary key                                                                 |
| group_id                  | Optional grouping/tenant identifier (e.g., account, organization)           |
| user_id                   | Optional owner/user relationship                                            |
| model_type/model_id       | Polymorphic relation to any Laravel model                                   |
| type                      | Unsigned tinyint classification (0‑255). Map values via consumer enums      |
| sort_order                | Auto‑generated or manually assigned ordering                                |
| file_mime_type            | MIME type inferred from upload                                              |
| file_ext                  | File extension without dot                                                  |
| file_path                 | Storage path on the configured disk                                         |
| file_size                 | File size in bytes                                                          |
| name                      | Human‑readable display name                                                 |
| original_file_name        | Client‑provided filename                                                    |
| label/category            | Optional classification metadata                                            |
| timestamps + soft deletes | Lifecycle management                                                        |

### Polymorphic Relationship Example
```php
class Post extends Model {
    public function assets() {
        return $this->morphMany(Asset::class, 'model');
    }

    public function heroImage() {
        return $this->morphOne(Asset::class, 'model')->where('label', 'hero');
    }
}
```

### Notes on Metadata Fields
- **group_id** is useful for multi‑tenant architectures (e.g., scoping assets by account).
- **type** is stored as an unsigned tinyint (0‑255); back it with a PHP backed enum to keep values readable. It participates in default sort behavior.
- **sort_order** can be generated automatically or assigned manually per upload/update.

## Core Responsibilities
### 1. Centralized Asset Storage
- Every file is represented by a single, normalized `Asset` record.
- Supports model association, user ownership, or standalone assets.
- Prevents scattered, inconsistent storage patterns across the codebase.

### 2. File Operations
Atlas Assets handles all major file lifecycle actions:
- Upload
- Replace file while retaining metadata
- Soft delete asset records (with optional force delete flag to immediately remove files + rows)
- Purge soft‑deleted assets and optionally delete files

Supports:
- Temporary signed URLs for S3 and compatible disks
- Direct download and existence checking

### 3. Metadata & Classification
- Labels, categories, and types offer flexible classification. Types are unsigned tinyints, so define a PHP enum (`enum AssetType: int { ... }`) to document each value.
- `name` can be updated without moving the underlying file.
- Changing `file_mime_type`, `extension`, or storage path occurs automatically when replacing a file.

### 4. Sorting Behavior
Sorting is fully configurable:
- Automatic incremental sorting per scope (model, type, etc.)
- Optional custom resolver for dynamic sorting rules (e.g., weight by category)
- Manual sort-order override when needed

### 5. Flexible Pathing
Two approaches:

#### Pattern‑based
Uses placeholders such as:
```
{model_type}/{model_id}/{uuid}.{extension}
```

#### Callback‑based
Defined directly in `config/atlas-assets.php`:
```php
'path' => [
    'resolver' => function (?Illuminate\Database\Eloquent\Model $model, Illuminate\Http\UploadedFile $file, array $attributes): string {
        return 'uploads/' . ($attributes['user_id'] ?? 'anon') . '/' . uniqid() . '.' .
            $file->extension();
    },
],
```

Pathing can reflect tenancy, model type, user attributes, or any custom logic.

Need per-context directories? Route on `type`, `category`, or any other attribute supplied with the upload call:
```php
use App\Enums\ProductAssetType;

'path' => [
    'resolver' => function (?Illuminate\Database\Eloquent\Model $model, Illuminate\Http\UploadedFile $file, array $attributes): string {
        $directory = match ($attributes['type'] ?? null) {
            ProductAssetType::ProductImage->value => 'products',
            ProductAssetType::FormImage->value => 'forms',
            default => 'general',
        };

        return $directory . '/' . uniqid() . '.' . $file->extension();
    },
],
```

## Storage & Pathing
### Disk & Visibility
- Works with any Laravel disk (`s3`, `local`, DigitalOcean Spaces, etc.)
- Default visibility: `public`
- Supports signed temporary URLs when the disk allows it

### Pattern‑Based Pathing
Placeholders include:
- `model_type`, `model_id`
- `group_id`, `user_id`
- `uuid`, `random`
- `file_name`, `original_name`
- `extension`
- `date:*` formats (`date:Y/m/d`, etc.)

### Callback Pathing
For full dynamic control, such as multi‑tenant storage buckets:
```php
'path' => [
    'resolver' => function (?Illuminate\Database\Eloquent\Model $model, Illuminate\Http\UploadedFile $file, array $attrs): string {
        return 'accounts/' . ($attrs['group_id'] ?? 'global') . '/' . \Illuminate\Support\Str::uuid() . '.' . $file->extension();
    },
],
```

Reset:
```php
'path' => ['resolver' => null];
```

## Services
### AssetService
Handles all write operations:
- `upload()`
- `uploadForModel()`
- `update()`
- `replace()`

Supports per‑call overrides for:
- allowed extensions
- blocklist
- size limits
- custom sort order

### AssetRetrievalService
Handles reads:
- `find()`
- `forModel()`, `forUser()`
- `listForModel()`, `listForUser()`
- `download()`, `exists()`
- `temporaryUrl()`

Also exposes base query builders for advanced consumers.

### AssetCleanupService
Handles cleanup:
- `delete(Asset $asset, bool $forceDelete = false)` — soft delete by default, or force delete + remove files immediately when `true`
- `purge()` — permanently delete soft-deleted records/files in batches

## Configuration
Located in: `config/atlas-assets.php`

### Upload Rules
- `allowed_extensions`: restrict allowed types
- `blocked_extensions`: extensions always rejected
- `max_file_size`: default 10 MB
- Per‑upload overrides using attributes

### Pathing Rules
- Provide a pattern string
- Or define a callback resolver

### Sort Rules
- `sort.scopes` controls grouping
- `sort.resolver` receives full metadata for custom ordering
- Manual `sort_order` bypasses both

### Environment Variables
```
ATLAS_ASSETS_DISK=
ATLAS_ASSETS_VISIBILITY=
ATLAS_ASSETS_DELETE_ON_SOFT_DELETE=
```

## Also See
- [Example Usage](./Example-Usage.md)
- [Full API Reference](../Full-API.md)
- [Installation Guide](../Install.md)
- [README](../../README.md)
