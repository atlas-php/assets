<?php

declare(strict_types=1);

namespace Atlas\Assets\Tests;

use Atlas\Assets\Providers\AtlasAssetsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Class TestCase
 *
 * Base testbench case for Atlas Assets package tests.
 * PRD Reference: Atlas Assets Overview — Package requirements.
 */
abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            AtlasAssetsServiceProvider::class,
        ];
    }
}
