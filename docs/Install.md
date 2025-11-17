# Atlas Assets Installation

This guide outlines the minimal steps required to install and configure **Atlas Assets** in your Laravel application.

## Table of Contents
- [Install the Package](#install-the-package)
- [Publish Configuration](#publish-configuration)
- [Configure Disk & Visibility](#configure-disk--visibility)
- [Publish Migrations](#publish-migrations)
- [Run Migrations](#run-migrations)
- [Usage Entry Point](#usage-entry-point)

## Install the Package
```bash
composer require atlas-php/assets
```

Laravel auto-discovers the service provider, so no manual registration is needed.

## Publish Configuration
Generate `config/atlas-assets.php`:

```bash
php artisan vendor:publish --tag=atlas-assets-config
```

This file controls disk selection, visibility, table names, path patterns, and resolver callbacks.

## Configure Disk & Visibility
Ensure your desired filesystem disk is defined in `config/filesystems.php`.

`atlas-assets.php` options include:
- `disk` — defaults to `s3`
- `visibility` — `public` (default) or `private`
- `delete_files_on_soft_delete` — determines cleanup behavior

Example `.env` overrides:

```dotenv
ATLAS_ASSETS_DISK=s3
ATLAS_ASSETS_VISIBILITY=public
ATLAS_ASSETS_DELETE_ON_SOFT_DELETE=false
```

## Publish Migrations
Atlas Assets requires an `assets` table (or custom table name defined in config).

```bash
php artisan vendor:publish --tag=atlas-assets-migrations
```

## Run Migrations
```bash
php artisan migrate
```

## Usage Entry Point
Basic asset upload:

```php
use Atlas\Assets\Facades\Assets;

$asset = Assets::upload($request->file('document'));
```

Upload with model association:

```php
$asset = Assets::uploadForModel($post, $request->file('image'));
```

Temporary URL:

```php
$url = Assets::temporaryUrl($asset);
```

## Also See
- Overview
- Full API Reference
- README
