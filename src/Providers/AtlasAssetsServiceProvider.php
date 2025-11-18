<?php

declare(strict_types=1);

namespace Atlas\Assets\Providers;

use Atlas\Assets\Services\AssetCleanupService;
use Atlas\Assets\Services\AssetManager;
use Atlas\Assets\Services\AssetRetrievalService;
use Atlas\Assets\Services\AssetService;
use Atlas\Assets\Support\ConfigValidator;
use Atlas\Assets\Support\PathResolver;
use Atlas\Assets\Support\SortOrderResolver;
use Atlas\Core\Providers\PackageServiceProvider;
use Atlas\Core\Publishing\TagBuilder;

/**
 * Class AtlasAssetsServiceProvider
 *
 * Boots and registers the Atlas Assets package bindings and publishable assets.
 * PRD Reference: Atlas Assets Overview — Service registration and configuration.
 */
class AtlasAssetsServiceProvider extends PackageServiceProvider
{
    private ?TagBuilder $publishTags = null;

    /**
     * Register bindings and merge publishable configuration.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            $this->configPath(),
            'atlas-assets'
        );

        $this->app->bind(ConfigValidator::class, static fn () => new ConfigValidator);
        $this->app->bind(PathResolver::class);
        $this->app->bind(SortOrderResolver::class);
        $this->app->bind(AssetService::class);
        $this->app->bind(AssetRetrievalService::class);
        $this->app->bind(AssetCleanupService::class);
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
                $this->configPath() => config_path('atlas-assets.php'),
            ], $this->tags()->config());

            $this->publishes([
                __DIR__.'/../../database/migrations' => database_path('migrations'),
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

    /**
     * Determine the absolute path to the package config file.
     */
    protected function configPath(): string
    {
        return __DIR__.'/../../config/atlas-assets.php';
    }

    private function tags(): TagBuilder
    {
        return $this->publishTags ??= new TagBuilder('atlas assets');
    }
}
