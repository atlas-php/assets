<?php

declare(strict_types=1);

namespace Atlas\Assets\Tests;

use Atlas\Assets\Providers\AtlasAssetsServiceProvider;
use Atlas\Core\Testing\PackageTestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Mockery;

/**
 * Class TestCase
 *
 * Base testbench case for Atlas Assets package tests.
 * PRD Reference: Atlas Assets Overview — Package requirements.
 */
abstract class TestCase extends PackageTestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('atlas-assets.tables.assets', 'atlas_assets');
        config()->set('atlas-assets.database.connection', null);
    }

    /**
     * Define package migrations for RefreshDatabase usage.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadPackageMigrations(__DIR__.'/../database/migrations');
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            AtlasAssetsServiceProvider::class,
        ];
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
