<?php

declare(strict_types=1);

namespace Atlas\Assets\Providers;

use Atlas\Assets\Services\AssetRetrievalService;
use Atlas\Assets\Services\AssetUploadService;
use Atlas\Assets\Support\ConfigValidator;
use Atlas\Assets\Support\PathResolver;
use Illuminate\Support\ServiceProvider;

/**
 * Class AtlasAssetsServiceProvider
 *
 * Boots and registers the Atlas Assets package bindings and publishable assets.
 * PRD Reference: Atlas Assets Overview — Service registration and configuration.
 */
class AtlasAssetsServiceProvider extends ServiceProvider
{
    /**
     * Register bindings and merge publishable configuration.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            $this->configPath(),
            'atlas-assets'
        );

        $this->app->singleton(ConfigValidator::class, static fn () => new ConfigValidator);
        $this->app->singleton(PathResolver::class);
        $this->app->singleton(AssetUploadService::class);
        $this->app->singleton(AssetRetrievalService::class);
    }

    /**
     * Bootstrap package services by publishing configuration for consumers.
     */
    public function boot(): void
    {
        $this->publishes([
            $this->configPath() => config_path('atlas-assets.php'),
        ], 'atlas-assets-config');

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    /**
     * Determine the absolute path to the package config file.
     */
    protected function configPath(): string
    {
        return __DIR__.'/../../config/atlas-assets.php';
    }
}
