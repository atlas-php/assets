<?php

declare(strict_types=1);

namespace Atlas\Assets\Tests;

use Atlas\Assets\Providers\AtlasAssetsServiceProvider;
use Illuminate\Foundation\Testing\WithFaker;
use Mockery;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Class TestCase
 *
 * Base testbench case for Atlas Assets package tests.
 * PRD Reference: Atlas Assets Overview — Package requirements.
 */
abstract class TestCase extends Orchestra
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('atlas-assets.tables.assets', 'atlas_assets');
        config()->set('atlas-assets.database.connection', null);
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

    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testbench');

        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
