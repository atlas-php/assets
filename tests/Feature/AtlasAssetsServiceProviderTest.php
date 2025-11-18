<?php

declare(strict_types=1);

namespace Atlas\Assets\Tests\Feature;

use Atlas\Assets\Providers\AtlasAssetsServiceProvider;
use Atlas\Assets\Support\ConfigValidator;
use Atlas\Assets\Tests\TestCase;
use InvalidArgumentException;
use Mockery;

/**
 * Class AtlasAssetsServiceProviderTest
 *
 * Ensures the service provider validates configuration during boot to guard
 * against misconfiguration before package services are executed.
 * PRD Reference: Atlas Assets Overview — Configuration.
 */
final class AtlasAssetsServiceProviderTest extends TestCase
{
    public function test_boot_validates_configuration_once_with_overrides(): void
    {
        $provider = new AtlasAssetsServiceProvider($this->app);
        $provider->register();

        config()->set('atlas-assets.disk', 'custom-disk');
        config()->set('atlas-assets.path.pattern', '{model_type}/custom/{file_name}.{extension}');

        $validator = Mockery::spy(ConfigValidator::class);
        $this->app->instance(ConfigValidator::class, $validator);

        $provider->boot();
        $provider->boot();

        $validator->shouldHaveReceived('validate')
            ->once()
            ->with(config('atlas-assets'));

        self::assertSame('custom-disk', config('atlas-assets.disk'));
    }

    public function test_boot_rejects_invalid_configuration(): void
    {
        $provider = new AtlasAssetsServiceProvider($this->app);
        $provider->register();

        config()->set('atlas-assets.disk', '');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The assets disk must be defined.');

        $provider->boot();
    }
}
