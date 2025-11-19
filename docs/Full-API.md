# Atlas Assets API

Reference for the public Atlas Assets API surface, including the facade, core services, and support utilities.

## Facade

### `Atlas\Assets\Facades\Assets`

Static facade backed by the service container.

| Method                                                                                                   | Description                                                                                                                       |
|----------------------------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------|
| `upload(UploadedFile $file, array $attributes = []): Asset`                                              | Proxy to `AssetModelService::upload`. Stores a new asset without a model context.                                                |
| `uploadForModel(Model $model, UploadedFile $file, array $attributes = []): Asset`                        | Proxy to `AssetModelService::uploadForModel`. Stores an asset bound to a model.                                                  |
| `update(Asset $asset, array $attributes = [], ?UploadedFile $file = null, ?Model $model = null): Asset`  | Proxy to `AssetModelService::updateAsset`. Updates metadata and optionally replaces the stored file and/or model association.    |
| `replace(Asset $asset, UploadedFile $file, array $attributes = [], ?Model $model = null): Asset`         | Proxy to `AssetModelService::replaceAsset`. Convenience wrapper around `update()` with a new file.                               |
| `find(int|string $id): ?Asset`                                                                           | Proxy to `AssetModelService::find`. Fetches an asset by primary key.                                                             |
| `forModel(Model $model, array $filters = [], ?int $limit = null): Builder`                               | Proxy to `AssetModelService::forModel`. Returns a builder scoped to a model with optional filters and limit.                     |
| `forUser(int|string $userId, array $filters = [], ?int $limit = null): Builder`                          | Proxy to `AssetModelService::forUser`. Returns a builder scoped to a user id with optional filters and limit.                    |
| `download(Asset $asset): string`                                                                         | Proxy to `AssetFileService::download`. Reads file contents from storage.                                                         |
| `exists(Asset $asset): bool`                                                                             | Proxy to `AssetFileService::exists`. Checks if the file exists on the configured disk.                                           |
| `temporaryUrl(Asset $asset, int $minutes = 5): string`                                                   | Proxy to `AssetFileService::temporaryUrl`. Returns a temporary URL or signed route URL.                                          |
| `delete(Asset $asset, bool $forceDelete = false): void`                                                  | Proxy to `AssetModelService::delete`. Soft deletes by default; pass `true` to force delete immediately and remove the file.    |
| `purge(): int`                                                                                           | Proxy to `AssetPurgeService::purge`. Permanently deletes all soft-deleted assets and their files, returning the count.         |

## Services

### `Atlas\Assets\Services\AssetPurgeService`

Provides a purge-specific entry point that delegates to `AssetModelService` so the API surface stays stable while deletion logic lives in the model service.

| Method         | Description                                                                                                 |
|----------------|-------------------------------------------------------------------------------------------------------------|
| `purge(int $chunkSize = 100): int` | Permanently deletes all soft-deleted assets and their files (via the model service) and returns the purged count. |

### `Atlas\Assets\Services\AssetModelService`

Shared CRUD layer for the `Asset` model that now also orchestrates file cleanup for delete flows and exposes scoped query builders for reads.

| Method/Property                            | Description                                                                                                                                                |
|--------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `query(): Builder<Asset>`                  | Returns a builder for raw queries.                                                                                                                         |
| `create(array $data): Asset`               | Creates a record with the provided attributes.                                                                                                             |
| `upload(UploadedFile $file, array $attributes = []): Asset` | Persists a new asset without a model context, handling validation, storage, metadata normalization, and sort order selection.                              |
| `uploadForModel(Model $model, UploadedFile $file, array $attributes = []): Asset` | Persists a new asset that is scoped to a model.                                                                                                            |
| `updateAsset(Asset $asset, array $attributes = [], ?UploadedFile $file = null, ?Model $model = null): Asset` | Updates metadata and optionally replaces the stored file, including model reassignment.                                                                    |
| `replaceAsset(Asset $asset, UploadedFile $file, array $attributes = [], ?Model $model = null): Asset` | Convenience wrapper around `updateAsset()` when swapping the file.                                                                                        |
| `forModel(Model $model, array $filters = [], ?int $limit = null): Builder` | Returns a builder scoped to the supplied model, ready for `get()`, `paginate()`, or `cursorPaginate()`.                                                    |
| `forUser(int|string $userId, array $filters = [], ?int $limit = null): Builder` | Returns a builder scoped to a user identifier with optional filters.                                                                                       |
| `buildModelQuery(Model $model, array $filters = []): Builder` | Lower-level helper for custom constraints when building model-scoped queries.                                                                              |
| `buildUserQuery(int|string $userId, array $filters = []): Builder` | Lower-level helper for custom constraints when building user-scoped queries.                                                                               |
| `delete(Asset $asset, bool $force = false): bool` | Deletes the record through soft deletes by default, removing files when configured, or force deletes + removes files immediately when `$force` is `true`. |

### `Atlas\Assets\Services\AssetFileService`

Low-level helper responsible for disk resolution, downloads, temporary URLs, and streaming so higher level services remain storage-agnostic.

| Method/Property              | Description                                                                                 |
|------------------------------|---------------------------------------------------------------------------------------------|
| `download(Asset $asset): string` | Reads the file contents from the configured disk.                                        |
| `exists(Asset $asset): bool`     | Checks if the file exists on disk.                                                       |
| `temporaryUrl(Asset $asset, int $minutes = 5): string` | Returns a disk-issued temporary URL or a signed streaming route fallback. |
| `stream(Asset $asset): StreamedResponse` | Streams the asset via the signed fallback route when necessary.                       |
| `delete(?string $path): void`    | Removes the file from the configured disk when it exists.                               |
| `disk(): Filesystem`             | Returns the resolved disk instance for advanced use.                                     |

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
 
