<?php

declare(strict_types=1);

namespace Atlas\Assets\Tests\Feature;

use Atlas\Assets\Support\ConfigValidator;
use Atlas\Assets\Tests\TestCase;
use InvalidArgumentException;

/**
 * Class ConfigTest
 *
 * Ensures the atlas-assets configuration defaults align with the PRD and
 * validates misconfigurations.
 * PRD Reference: Atlas Assets Overview — Configuration.
 */
final class ConfigTest extends TestCase
{
    public function test_exposes_default_configuration_values(): void
    {
        self::assertSame('s3', config('atlas-assets.disk'));
        self::assertSame('public', config('atlas-assets.visibility'));
        self::assertFalse((bool) config('atlas-assets.delete_files_on_soft_delete'));
        self::assertSame(
            '{model_type}/{model_id}/{uuid}.{extension}',
            config('atlas-assets.path.pattern')
        );
        self::assertNull(config('atlas-assets.path.resolver'));
        self::assertSame('atlas_assets', config('atlas-assets.tables.assets'));
        self::assertNull(config('atlas-assets.database.connection'));
    }

    public function test_validates_configuration_when_defaults_are_used(): void
    {
        $validator = $this->app->make(ConfigValidator::class);
        $validator->validate(config('atlas-assets'));

        $this->addToAssertionCount(1);
    }

    public function test_rejects_configuration_when_disk_is_invalid(): void
    {
        $validator = $this->app->make(ConfigValidator::class);

        $config = config('atlas-assets');
        $config['disk'] = '';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('disk must be defined');

        $validator->validate($config);
    }

    public function test_rejects_configuration_when_path_inputs_are_invalid(): void
    {
        $validator = $this->app->make(ConfigValidator::class);

        $config = config('atlas-assets');
        $config['path']['pattern'] = '';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('path pattern or resolver');

        $validator->validate($config);
    }

    public function test_rejects_configuration_with_invalid_placeholder(): void
    {
        $validator = $this->app->make(ConfigValidator::class);

        $config = config('atlas-assets');
        $config['path']['pattern'] = '{invalid}/path';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported path placeholder');

        $validator->validate($config);
    }

    public function test_rejects_configuration_with_non_callable_resolver(): void
    {
        $validator = $this->app->make(ConfigValidator::class);

        $config = config('atlas-assets');
        $config['path']['resolver'] = 'not-a-callable';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('path resolver must be a callable');

        $validator->validate($config);
    }
}
