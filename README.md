# Atlas Assets
**Atlas Assets** is a Laravel package that centralizes file management into a unified system for uploading, organizing, storing, and retrieving assets across any storage backend. It provides dynamic pathing, polymorphic model associations, and a consistent API for working with files—while remaining fully storage-agnostic and optimized for S3-based workflows.

---

## Contributing
See the [Contributing Guide](./CONTRIBUTING.md).

All work must align with PRDs and agent workflow rules defined in [AGENTS.md](./AGENTS.md).

## Installation
Install the package via Composer:

```bash
composer require atlas-php/assets
```

Laravel auto-discovers the service provider, so no manual registration is required. To customize disk, visibility, path resolution, table names, or database connections, publish the configuration file:

```bash
php artisan vendor:publish --tag=atlas-assets-config
```

The publish command creates `config/atlas-assets.php`, which contains all default options described in the PRD.

### Configuration overview

- **Disk** (`atlas-assets.disk`): defaults to `s3`. Point this to any disk configured in `config/filesystems.php`.
- **Visibility** (`atlas-assets.visibility`): defaults to `public`. Set to `private` when uploads should be hidden.
- **Delete on soft delete** (`atlas-assets.delete_files_on_soft_delete`): defaults to `false`. Cleanup services remove files automatically, but this flag is exposed for consumers implementing custom flows.

### Path patterns & resolvers

By default files are stored under `{model_type}/{model_id}/{file_name}.{extension}`. When no model is provided, the placeholders collapse automatically and the file is placed at the disk root (e.g., `document.pdf`). You can change this behavior in `config/atlas-assets.php`:

```php
'path' => [
    'pattern' => '{model_type}/{model_id}/{uuid}.{extension}',
    'resolver' => null,
],
```

- **Pattern:** Uses placeholders such as `{file_name}`, `{original_name}`, `{model_type}`, `{model_id}`, `{user_id}`, `{uuid}`, `{random}`, `{date:Y/m}`, and `{extension}` to build deterministic paths.
- **Resolver:** Provide a closure for full programmatic control. When set, the resolver output takes precedence over the pattern.

Example resolver:

```php
'path' => [
    'pattern' => '{model_type}/{model_id}/{uuid}.{extension}',
    'resolver' => static function (?Model $model, UploadedFile $file, array $attributes): string {
        $owner = $attributes['user_id'] ?? 'anonymous';

        return sprintf(
            'users/%s/%s/%s.%s',
            $owner,
            strtolower(class_basename($model)) ?: 'loose',
            Str::uuid(),
            $file->getClientOriginalExtension()
        );
    },
],
```

If you leave `model_type`/`model_id` unused (the default), files without an associated model are stored at the disk root automatically.

You can also register a resolver programmatically at runtime:

```php
use Atlas\Assets\Support\PathConfigurator;

PathConfigurator::useCallback(static fn (?Model $model, UploadedFile $file, array $attributes) => sprintf(
    'users/%s/%s.%s',
    $attributes['user_id'] ?? 'anon',
    strtolower(class_basename($model)) ?: 'loose',
    $file->getClientOriginalExtension()
));

// Or point to a dedicated service class (invokable by default)
PathConfigurator::useService(CustomResolver::class);

// Revert to the pattern-based resolver
PathConfigurator::clear();
```

## License
MIT — see [LICENSE](./LICENSE).
