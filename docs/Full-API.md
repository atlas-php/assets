# Atlas Assets API

This reference documents the public services shipped with the Atlas Assets package. Each service is container-resolvable and follows the PRD requirements.

## Facade

### `Atlas\Assets\Facades\Assets`

Static interface backed by the service container.

| Method                                                                                                   | Description                                                                                                                       |
|----------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------|
| `upload(UploadedFile $file, array $attributes = []): Asset`                                              | Delegates to `AssetService::upload`.                                                                                              |
| `uploadForModel(Model $model, UploadedFile $file, array $attributes = []): Asset`                        | Delegates to `AssetService::uploadForModel`.                                                                                      |
| `update(Asset $asset, array $attributes = [], ?UploadedFile $file = null, ?Model $model = null): Asset`  | Delegates to `AssetService::update`.                                                                                              |
| `replace(Asset $asset, UploadedFile $file, array $attributes = [], ?Model $model = null): Asset`         | Delegates to `AssetService::replace`.                                                                                             |
| `find(int|string $id): ?Asset`                                                                          | Delegates to `AssetRetrievalService::find`.                                                                                       |
| `forModel(Model $model, array $filters = [], ?int $limit = null): Collection`                            | Fluent alias for `AssetRetrievalService::listForModel`.                                                                           |
| `forUser(int|string $userId, array $filters = [], ?int $limit = null): Collection`                       | Fluent alias for `AssetRetrievalService::listForUser`.                                                                            |
| `listForModel(Model $model, array $filters = [], ?int $limit = null): Collection`                        | Delegates to `AssetRetrievalService::listForModel`.                                                                               |
| `listForUser(int|string $userId, array $filters = [], ?int $limit = null): Collection`                   | Delegates to `AssetRetrievalService::listForUser`.                                                                                |
| `paginateForModel(Model $model, array $filters = [], int $perPage = 15, string $pageName = 'page', ?int $page = null): LengthAwarePaginator` | Delegates to `AssetRetrievalService::paginateForModel`. |
| `paginateForUser(int|string $userId, array $filters = [], int $perPage = 15, string $pageName = 'page', ?int $page = null): LengthAwarePaginator` | Delegates to `AssetRetrievalService::paginateForUser`. |
| `cursorPaginateForModel(Model $model, array $filters = [], int $perPage = 15, string $cursorName = 'cursor', ?Cursor $cursor = null): CursorPaginator` | Delegates to `AssetRetrievalService::cursorPaginateForModel`. |
| `cursorPaginateForUser(int|string $userId, array $filters = [], int $perPage = 15, string $cursorName = 'cursor', ?Cursor $cursor = null): CursorPaginator` | Delegates to `AssetRetrievalService::cursorPaginateForUser`. |
| `download(Asset $asset): string`                                                                         | Delegates to `AssetRetrievalService::download`.                                                                                   |
| `exists(Asset $asset): bool`                                                                             | Delegates to `AssetRetrievalService::exists`.                                                                                     |
| `temporaryUrl(Asset $asset, int $minutes = 5): string`                                                   | Delegates to `AssetRetrievalService::temporaryUrl`.                                                                               |
| `delete(Asset $asset): void`                                                                             | Delegates to `AssetCleanupService::delete`.                                                                                       |
| `purge(): int`                                                                                           | Delegates to `AssetCleanupService::purge`.                                                                                        |

## Services

### `Atlas\Assets\Services\AssetService`

Handles uploads, replacements, and metadata updates.

| Method                                                                                                  | Description                                                                                                                                               |
|---------------------------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------|
| `upload(UploadedFile $file, array $attributes = []): Asset`                                             | Stores a new asset without a model context. Attributes may include `user_id`, `label`, `category`, and optional `name`.                                   |
| `uploadForModel(Model $model, UploadedFile $file, array $attributes = []): Asset`                       | Stores an asset tied to a model.                                                                                                                          |
| `update(Asset $asset, array $attributes = [], ?UploadedFile $file = null, ?Model $model = null): Asset` | Updates metadata and optionally replaces the stored file. Automatically maintains `original_file_name` and deletes displaced files when the path changes. |
| `replace(Asset $asset, UploadedFile $file, array $attributes = [], ?Model $model = null): Asset`        | Convenience method that delegates to `update()` with a new file reference.                                                                                |

### `Atlas\Assets\Services\AssetRetrievalService`

Provides read operations and download helpers.

| Method                                                        | Description                                                                                  |
|---------------------------------------------------------------|--------------------------------------------------------------------------------|
| `find(int|string $id): ?Asset`                                | Fetches an asset by primary key.             |
| `forModel(Model $model, array $filters = [], ?int $limit = null): Collection` | Fluent alias for `listForModel()` that returns assets for the given model with optional filters and limit. |
| `forUser(int|string $userId, array $filters = [], ?int $limit = null): Collection` | Fluent alias for `listForUser()` that returns assets for a given user with optional filters and limit. |
| `listForModel(Model $model, array $filters = [], ?int $limit = null): Collection` | Returns assets for the given model, with optional `label`/`category` filters and an optional limit.                |
| `listForUser(int|string $userId, array $filters = [], ?int $limit = null): Collection` | Returns assets for a user ID with optional filters and an optional limit.                                    |
| `paginateForModel(Model $model, array $filters = [], int $perPage = 15, string $pageName = 'page', ?int $page = null): LengthAwarePaginator` | Provides page-based pagination for model assets.                               |
| `paginateForUser(int|string $userId, array $filters = [], int $perPage = 15, string $pageName = 'page', ?int $page = null): LengthAwarePaginator` | Provides page-based pagination for user assets.                               |
| `cursorPaginateForModel(Model $model, array $filters = [], int $perPage = 15, string $cursorName = 'cursor', ?Cursor $cursor = null): CursorPaginator` | Streams model assets via cursor-based pagination.                               |
| `cursorPaginateForUser(int|string $userId, array $filters = [], int $perPage = 15, string $cursorName = 'cursor', ?Cursor $cursor = null): CursorPaginator` | Streams user assets via cursor-based pagination.                               |
| `download(Asset $asset): string`                              | Reads the asset file from storage and returns its contents. Throws when the file is missing. |
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
