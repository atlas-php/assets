<?php

declare(strict_types=1);

namespace Atlas\Assets\Providers;

use Atlas\Assets\Services\AssetCleanupService;
use Atlas\Assets\Services\AssetManager;
use Atlas\Assets\Services\AssetRetrievalService;
use Atlas\Assets\Services\AssetService;
use Atlas\Assets\Support\ConfigValidator;
use Atlas\Assets\Support\PathResolver;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Console\Output\ConsoleOutput;

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

        $this->app->bind(ConfigValidator::class, static fn () => new ConfigValidator);
        $this->app->bind(PathResolver::class);
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
        if ($this->app->runningInConsole()) {

            $this->publishes([
                $this->configPath() => config_path('atlas-assets.php'),
            ], 'atlas-assets-config');

            $this->publishes([
                __DIR__ . '/../../database/migrations' => database_path('migrations'),
            ], 'atlas-assets-migrations');

            $this->notifyPendingInstallSteps();
        }
    }

    private function notifyPendingInstallSteps(): void
    {
        if ($this->app->runningUnitTests()) {
            return;
        }

        $missingConfig = ! $this->configPublished();
        $missingMigrations = ! $this->migrationsPublished();

        if (! $missingConfig && ! $missingMigrations) {
            return;
        }

        $output = $this->consoleOutput();
        $output->writeln('');
        $output->writeln('<comment>[Atlas Assets]</comment> Publish configuration and migrations, then run migrations:');

        if ($missingConfig) {
            $output->writeln('  php artisan vendor:publish --tag=atlas-assets-config');
        }

        if ($missingMigrations) {
            $output->writeln('  php artisan vendor:publish --tag=atlas-assets-migrations');
        }

        $output->writeln('  php artisan migrate');
        $output->writeln('');
    }

    private function consoleOutput(): ConsoleOutput
    {
        if ($this->app->bound(ConsoleOutput::class)) {
            return $this->app->make(ConsoleOutput::class);
        }

        return new ConsoleOutput;
    }

    private function configPublished(): bool
    {
        if (! function_exists('config_path')) {
            return false;
        }

        return file_exists(config_path('atlas-assets.php'));
    }

    private function migrationsPublished(): bool
    {
        if (! function_exists('database_path')) {
            return false;
        }

        $pattern = database_path('migrations/*atlas_assets*');
        $matches = glob($pattern);

        if ($matches === false) {
            return false;
        }

        return $matches !== [];
    }

    /**
     * Determine the absolute path to the package config file.
     */
    protected function configPath(): string
    {
        return __DIR__.'/../../config/atlas-assets.php';
    }
}
