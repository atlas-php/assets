<?php

declare(strict_types=1);

namespace Atlas\Assets\Providers;

use Atlas\Assets\Services\AssetFileService;
use Atlas\Assets\Services\AssetManager;
use Atlas\Assets\Services\AssetModelService;
use Atlas\Assets\Services\AssetPurgeService;
use Atlas\Assets\Services\AssetRetrievalService;
use Atlas\Assets\Services\AssetService;
use Atlas\Assets\Support\ConfigValidator;
use Atlas\Assets\Support\PathResolver;
use Atlas\Assets\Support\SortOrderResolver;
use Atlas\Core\Providers\PackageServiceProvider;

/**
 * Class AtlasAssetsServiceProvider
 *
 * Boots and registers the Atlas Assets package bindings and publishable assets.
 * PRD Reference: Atlas Assets Overview — Service registration and configuration.
 */
class AtlasAssetsServiceProvider extends PackageServiceProvider
{
    protected string $packageBasePath = __DIR__.'/../..';

    /**
     * Register bindings and merge publishable configuration.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            $this->packageConfigPath('atlas-assets.php'),
            'atlas-assets'
        );

        $this->app->bind(ConfigValidator::class, static fn () => new ConfigValidator);
        $this->app->bind(PathResolver::class);
        $this->app->bind(SortOrderResolver::class);
        $this->app->bind(AssetFileService::class);
        $this->app->singleton(AssetModelService::class, AssetModelService::class);
        $this->app->bind(AssetService::class);
        $this->app->bind(AssetRetrievalService::class);
        $this->app->bind(AssetPurgeService::class);
        $this->app->singleton(AssetManager::class);
    }

    /**
     * Bootstrap package services by publishing configuration for consumers.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/atlas-assets.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                $this->packageConfigPath('atlas-assets.php') => config_path('atlas-assets.php'),
            ], $this->tags()->config());

            $this->publishes([
                $this->packageDatabasePath('migrations') => database_path('migrations'),
            ], $this->tags()->migrations());

            $this->notifyPendingInstallSteps(
                'Atlas Assets',
                'atlas-assets.php',
                $this->tags()->config(),
                '*atlas_assets*',
                $this->tags()->migrations()
            );
        }
    }

    protected function packageSlug(): string
    {
        return 'atlas assets';
    }
}
