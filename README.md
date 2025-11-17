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

## License
MIT — see [LICENSE](./LICENSE).
