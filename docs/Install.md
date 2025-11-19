# Atlas Assets Installation

This guide outlines the minimal steps required to install and configure **Atlas Assets** in your Laravel application.

## Table of Contents
- [Install the Package](#install-the-package)
- [Publish Configuration](#publish-configuration)
- [Configure Disk & Visibility](#configure-disk--visibility)
- [Publish Migrations](#publish-migrations)
- [Run Migrations](#run-migrations)
- [Usage Entry Point](#usage-entry-point)
- [Also See](#also-see)

## Install the Package
```bash
composer require atlas-php/assets
```

Laravel auto-discovers the service provider, so no manual registration is required.

## Publish Configuration
Generate the configuration file:

```bash
php artisan vendor:publish --tag=atlas-assets-config
```

## Configure Disk & Visibility
Ensure your desired filesystem disk is defined in `config/filesystems.php`.

Environment overrides:

```dotenv
ATLAS_ASSETS_DISK=s3
ATLAS_ASSETS_VISIBILITY=public
ATLAS_ASSETS_DELETE_ON_SOFT_DELETE=false
```

## Publish Migrations
Publish the migrations for the `atlas_assets` table (or your configured table name):

```bash
php artisan vendor:publish --tag=atlas-assets-migrations
```

## Run Migrations
```bash
php artisan migrate
```

## Usage Entry Point
Upload a file:

```php
use Atlas\Assets\Facades\Assets;

$asset = Assets::upload($request->file('document'));
```

Upload with model association:

```php
$asset = Assets::uploadForModel($post, $request->file('image'));
```

Generate a temporary URL:

```php
$url = Assets::temporaryUrl($asset);
```

## Also See
- [Overview](./PRD/Atlas-Assets.md)
- [Example Usage](./PRD/Example-Usage.md)
- [Full API Reference](./Full-API.md)
- [README](../README.md)
