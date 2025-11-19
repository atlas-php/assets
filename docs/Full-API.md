# Atlas Assets API

Reference for the public Atlas Assets API surface, including the facade, core services, and support utilities.

## Facade

### `Atlas\Assets\Facades\Assets`

Static facade backed by the service container.

| Method                                                                                                   | Description                                                                                                                       |
|----------------------------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------|
| `upload(UploadedFile $file, array $attributes = []): Asset`                                              | Proxy to `AssetService::upload`. Stores a new asset without a model context.                                                     |
| `uploadForModel(Model $model, UploadedFile $file, array $attributes = []): Asset`                        | Proxy to `AssetService::uploadForModel`. Stores an asset bound to a model.                                                       |
| `update(Asset $asset, array $attributes = [], ?UploadedFile $file = null, ?Model $model = null): Asset`  | Proxy to `AssetService::update`. Updates metadata and optionally replaces the stored file and/or model association.              |
| `replace(Asset $asset, UploadedFile $file, array $attributes = [], ?Model $model = null): Asset`         | Proxy to `AssetService::replace`. Convenience wrapper around `update()` with a new file.                                         |
| `find(int|string $id): ?Asset`                                                                           | Proxy to `AssetRetrievalService::find`. Fetches an asset by primary key.                                                         |
| `forModel(Model $model, array $filters = [], ?int $limit = null): Builder`                               | Fluent alias for `listForModel()`. Returns a builder scoped to a model with optional filters and limit.                          |
| `forUser(int|string $userId, array $filters = [], ?int $limit = null): Builder`                          | Fluent alias for `listForUser()`. Returns a builder scoped to a user id with optional filters and limit.                         |
| `listForModel(Model $model, array $filters = [], ?int $limit = null): Builder`                           | Proxy to `AssetRetrievalService::listForModel`. Lets consumers choose `get()`, `paginate()`, `cursorPaginate()`, etc.           |
| `listForUser(int|string $userId, array $filters = [], ?int $limit = null): Builder`                      | Proxy to `AssetRetrievalService::listForUser`. Returns a user-scoped builder for further constraints.                            |
| `download(Asset $asset): string`                                                                         | Proxy to `AssetRetrievalService::download`. Reads file contents from storage.                                                    |
| `exists(Asset $asset): bool`                                                                             | Proxy to `AssetRetrievalService::exists`. Checks if the file exists on the configured disk.                                      |
| `temporaryUrl(Asset $asset, int $minutes = 5): string`                                                   | Proxy to `AssetRetrievalService::temporaryUrl`. Returns a temporary URL or signed route URL.                                     |
| `delete(Asset $asset, bool $forceDelete = false): void`                                                  | Proxy to `AssetModelService::delete`. Soft deletes by default; pass `true` to force delete immediately and remove the file.    |
| `purge(): int`                                                                                           | Proxy to `AssetPurgeService::purge` → `AssetModelService::purge`. Permanently deletes all soft-deleted assets and their files, returning the count.         |

## Services

### `Atlas\Assets\Services\AssetService`

Handles uploads, replacements, and metadata updates.

| Method                                                                                                  | Description                                                                                                                                                                                                                                      |
|---------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `upload(UploadedFile $file, array $attributes = []): Asset`                                             | Stores a new asset without a model context. Attributes may include `group_id`, `user_id`, `label`, `category`, `type` (unsigned tinyint/backed enum value), `sort_order`, optional `name`, plus upload overrides like `allowed_extensions` and `max_upload_size` (bytes or `null`).   |
| `uploadForModel(Model $model, UploadedFile $file, array $attributes = []): Asset`                       | Stores an asset tied to a model with the same attribute support (`group_id`, `user_id`, `label`, `category`, `type`), `sort_order`, `name`, `allowed_extensions`, `max_upload_size`). Polymorphic columns are filled from the model.             |
| `update(Asset $asset, array $attributes = [], ?UploadedFile $file = null, ?Model $model = null): Asset` | Updates metadata and optionally replaces the stored file and/or re-associates the asset to a new model. Maintains `original_file_name`, updates `file_mime_type`, `file_ext`, `file_path`, and can recalculate or respect explicit `sort_order`. |
| `replace(Asset $asset, UploadedFile $file, array $attributes = [], ?Model $model = null): Asset`        | Convenience wrapper around `update()` that always includes a new file. When paths change, displaced files are removed according to configuration.                                                                                               |

### `Atlas\Assets\Services\AssetRetrievalService`

Provides read operations and download helpers.

| Method                                                        | Description                                                                                                                                                      |
|---------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `find(int|string $id): ?Asset`                                | Fetches an asset by primary key or returns `null` when missing.                                                                                                  |
| `forModel(Model $model, array $filters = [], ?int $limit = null): Builder` | Fluent alias for `listForModel()`. Returns an `Eloquent\Builder` scoped to the given model with optional filters and a max result limit.                       |
| `forUser(int|string $userId, array $filters = [], ?int $limit = null): Builder` | Fluent alias for `listForUser()`. Returns a builder scoped to a user id with optional filters and a max result limit.                                           |
| `listForModel(Model $model, array $filters = [], ?int $limit = null): Builder` | Returns a builder constrained to the given model. Callers decide whether to `get()`, `paginate()`, or `cursorPaginate()`.                                       |
| `listForUser(int|string $userId, array $filters = [], ?int $limit = null): Builder` | Returns a builder constrained to a user id.                                                                                                                      |
| `buildModelQuery(Model $model, array $filters = []): Builder` | Exposes the base model-scoped query used by retrieval helpers, useful when building more complex conditions.                                                    |
| `buildUserQuery(int|string $userId, array $filters = []): Builder` | Exposes the base user-scoped query.                                                                                                                              |
| `download(Asset $asset): string`                              | Reads the asset file from storage and returns its contents. Throws when the underlying file is missing.                                                         |
| `temporaryUrl(Asset $asset, int $minutes = 5): string`        | Generates a temporary URL using the disk’s native support when available, or falls back to a signed route that streams the file.                                |
| `exists(Asset $asset): bool`                                  | Returns `true` when the underlying file exists, `false` otherwise.                                                                                               |

### `Atlas\Assets\Services\AssetPurgeService`

Provides a purge-specific entry point that delegates to `AssetModelService` so the API surface stays stable while deletion logic lives in the model service.

| Method         | Description                                                                                                 |
|----------------|-------------------------------------------------------------------------------------------------------------|
| `purge(int $chunkSize = 100): int` | Permanently deletes all soft-deleted assets and their files (via the model service) and returns the purged count. |

### `Atlas\Assets\Services\AssetModelService`

Shared CRUD layer for the `Asset` model that now also orchestrates file cleanup for delete flows. Use this service whenever you need
direct data access without bypassing the shared behaviors used throughout the package.

| Method/Property                            | Description                                                                                                                                                |
|--------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `query(): Builder<Asset>`                  | Returns a builder for raw queries.                                                                                                                         |
| `create(array $data): Asset`               | Creates a record with the provided attributes.                                                                                                             |
| `delete(Asset $asset, bool $force = false): bool` | Deletes the record through soft deletes by default, removing files when configured, or force deletes + removes files immediately when `$force` is `true`. |

### `Atlas\Assets\Services\AssetFileService`

Low-level helper responsible for disk resolution and file deletion so higher level services remain storage-agnostic.

| Method/Property              | Description                                              |
|------------------------------|----------------------------------------------------------|
| `delete(?string $path): void`| Removes the file from the configured disk when it exists.|
| `disk(): Filesystem`         | Returns the resolved disk instance for advanced use.     |

## Support Utilities

### `Atlas\Assets\Support\PathResolver`

Service for computing storage paths using `config('atlas-assets.path.pattern')` or the optional
`config('atlas-assets.path.resolver')` callback.
Callbacks receive the uploaded file plus the metadata array passed to the upload helpers
(`group_id`, `user_id`, `type`, `category`, etc.), allowing per-context directories such as
`products/*` vs `forms/*`.

- `resolve(UploadedFile $file, ?Model $model = null, array $attributes = []): string`

Supported placeholders in pattern-based paths include:

- `group_id`, `user_id`
- `model_type`, `model_id`
- `file_name`, `original_name`
- `extension`
- `random`, `uuid`
- `date:*` (e.g. `date:Y/m/d`)

### `Atlas\Assets\Support\SortOrderResolver`

Generates sequential `sort_order` values using `config('atlas-assets.sort.scopes')` (defaults to `model_type`, `model_id`, `type`).

- Honors any configured `sort.resolver` callback `(?Model $model, array $context): int`.
- `context` includes metadata such as `group_id`, `user_id`, `category`, `type` (unsigned tinyint/backed enum value), and other attributes.
- Passing a `sort_order` attribute to write methods bypasses this resolver for manual control.

Example `sort` configuration:

```php
'sort' => [
    'scopes' => ['model_type', 'model_id', 'type'],
    'resolver' => function (?Illuminate\Database\Eloquent\Model $model, array $context): int {
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

## Extension Filtering

Uploads honor configuration stored under `atlas-assets.uploads`:

- `allowed_extensions`: optional whitelist of extensions (case-insensitive, without dots) that files must match.
- `blocked_extensions`: optional blocklist of extensions that are always rejected.
- Blocklisted extensions always win, even if an extension is also listed in `allowed_extensions`.
- Passing `allowed_extensions` to `upload()` / `uploadForModel()` overrides the configured whitelist for that single call; blocklists still apply.

Invalid uploads throw:

- `Atlas\Assets\Exceptions\DisallowedExtensionException` when the extension is not permitted.

## File Size Limits

File size rules are also configured under `atlas-assets.uploads`:

- `max_file_size`: default limit in bytes (defaults to 10 MB). Set to `null` to disable global size checks.
- Per-call overrides via `max_upload_size` on upload helpers:
    - integer: specific max size in bytes for that call
    - `null`: disables the size limit for that call

Oversized uploads throw:

- `Atlas\Assets\Exceptions\UploadSizeLimitException`.

## Sort Ordering

Sort order behavior is controlled via `atlas-assets.sort`:

- `sort.scopes`: ordered list of columns used to scope sequential sort assignment (defaults to `model_type`, `model_id`, `type`). Set to `null` to disable automatic increments (records keep the DB default of `0` unless explicitly set).
- `sort.resolver`: optional callable `(?Model $model, array $context): int` that can fully control `sort_order` based on metadata.
- Supplying `sort_order` in attributes for `upload`, `uploadForModel`, or `update` bypasses the resolver and stored procedure, enabling manual reordering.

## Tests & QA

All services are covered by PHPUnit tests (via Orchestra Testbench).

Before releasing or opening PRs, run:

```bash
./vendor/bin/pint
composer test
composer analyse
```
 
