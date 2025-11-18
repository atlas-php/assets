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
| `forModel(Model $model, array $filters = [], ?int $limit = null): Builder`                               | Fluent alias for `AssetRetrievalService::listForModel` that keeps the query builder fluent.                                       |
| `forUser(int|string $userId, array $filters = [], ?int $limit = null): Builder`                          | Fluent alias for `AssetRetrievalService::listForUser` returning an `Illuminate\Database\Eloquent\Builder`.                        |
| `listForModel(Model $model, array $filters = [], ?int $limit = null): Builder`                           | Delegates to `AssetRetrievalService::listForModel` so consumers decide whether to `get`, `paginate`, etc.                         |
| `listForUser(int|string $userId, array $filters = [], ?int $limit = null): Builder`                      | Delegates to `AssetRetrievalService::listForUser` and returns a builder ready for additional constraints.                         |
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
| `forModel(Model $model, array $filters = [], ?int $limit = null): Builder` | Fluent alias for `listForModel()` that returns an `Eloquent\Builder` scoped to the given model with optional filters and limit. |
| `forUser(int|string $userId, array $filters = [], ?int $limit = null): Builder` | Fluent alias for `listForUser()` that returns a builder scoped to a user id with optional filters and limit. |
| `listForModel(Model $model, array $filters = [], ?int $limit = null): Builder` | Returns a builder constrained to the given model, allowing consumers to call `get()`, `paginate()`, or `cursorPaginate()`.                |
| `listForUser(int|string $userId, array $filters = [], ?int $limit = null): Builder` | Returns a builder constrained to a user ID.                                    |
| `buildModelQuery(Model $model, array $filters = []): Builder` | Provides direct access to the base model query used by every retrieval helper. |
| `buildUserQuery(int|string $userId, array $filters = []): Builder` | Provides direct access to the user-scoped builder. |
| `download(Asset $asset): string`                              | Reads the asset file from storage and returns its contents. Throws when the file is missing. |
| `temporaryUrl(Asset $asset, int $minutes = 5): string`        | Generates a temporary URL when the disk supports it or falls back to a signed streaming route.      |

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
