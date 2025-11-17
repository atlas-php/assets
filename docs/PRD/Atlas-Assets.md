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

| Field                     | Summary                          |
|---------------------------|----------------------------------|
| `id`                      | Primary key                      |
| `user_id`                 | Optional owner                   |
| `model_type` / `model_id` | Optional polymorphic association |
| `file_type`               | MIME or custom value             |
| `file_path`               | Storage path                     |
| `file_size`               | Bytes                            |
| `name`                    | Display name                     |
| `original_file_name`      | Client filename                  |
| `label` / `category`      | Optional classification          |
| Timestamps + soft deletes | Lifecycle fields                 |

Assets can be free‑standing or attached to any model.

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
- `find()`, `listForModel()`, `listForUser()`
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

Environment overrides:
```
ATLAS_ASSETS_DISK=
ATLAS_ASSETS_VISIBILITY=
ATLAS_ASSETS_DELETE_ON_SOFT_DELETE=
```

## Also See
- Full API Reference
- Example Usage
- Installation Guide
- Package README
 