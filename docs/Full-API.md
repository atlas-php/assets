# Atlas Assets API

This reference documents the public services shipped with the Atlas Assets package. Each service is container-resolvable and follows the PRD requirements.

## Services

### `Atlas\Assets\Services\AssetService`

Handles uploads, replacements, and metadata updates.

| Method                                                                                                  | Description                                                                                                                                               |
|---------------------------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------|
| `upload(UploadedFile $file, array $attributes = []): Asset`                                             | Stores a new asset without a model context. Attributes may include `user_id`, `label`, `category`, and optional `name`.                                   |
| `uploadForModel(Model $model, UploadedFile $file, array $attributes = []): Asset`                       | Stores an asset tied to a model.                                                                                                                          |
| `update(Asset $asset, array $attributes = [], ?UploadedFile $file = null, ?Model $model = null): Asset` | Updates metadata and optionally replaces the stored file. Automatically maintains `original_file_name` and deletes displaced files when the path changes. |

### `Atlas\Assets\Services\AssetRetrievalService`

Provides read operations and download helpers.

| Method                                                        | Description                                                                                  |
|---------------------------------------------------------------|----------------------------------------------------------------------------------------------|
| `find(int                                                     | string $id): ?Asset`                                                                         | Fetches an asset by primary key. |
| `listForModel(Model $model, array $filters = []): Collection` | Returns assets for the given model, with optional `label`/`category` filters.                |
| `listForUser(int                                              | string $userId, array $filters = []): Collection`                                            | Returns assets for a user ID with optional filters. |
| `download(Asset $asset): string`                              | Reads the asset file from storage and returns its contents. Throws when the file is missing. |
| `exists(Asset $asset): bool`                                  | Checks if the asset file exists on the configured disk.                                      |
| `temporaryUrl(Asset $asset, int $minutes = 5): string`        | Generates a temporary URL when the disk supports it or falls back to a `data:` payload.      |

### `Atlas\Assets\Services\AssetCleanupService`

Manages deletions and purging of assets.

| Method                       | Description                                                                                          |
|------------------------------|------------------------------------------------------------------------------------------------------|
| `delete(Asset $asset): void` | Soft deletes the asset and removes the file from storage.                                            |
| `purge(): int`               | Permanently deletes all soft-deleted assets and their files, returning the number of purged records. |

### `Atlas\Assets\Support\PathConfigurator`

Runtime helpers for overriding path resolution.

| Method                                                         | Description                                                                                                              |
|----------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------|
| `useCallback(callable $resolver): void`                        | Registers a callback `( ?Model $model, UploadedFile $file, array $attributes ) => string` to override path construction. |
| `useService(string $class, string $method = '__invoke'): void` | Registers a service class/method as the resolver.                                                                        |
| `clear(): void`                                                | Restores the pattern-based resolver defined in config.                                                                   |

### `Atlas\Assets\Support\PathResolver`

While generally resolved internally, this service can be injected to manually compute storage paths using the configured pattern/callback via `resolve(UploadedFile $file, ?Model $model = null, array $attributes = []): string`.

## Tests & QA

All services are covered by PHPUnit tests (via Orchestra Testbench). Before releasing or opening PRs, run:

```bash
./vendor/bin/pint
composer test
composer analyse
```
