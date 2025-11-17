<?php

declare(strict_types=1);

namespace Atlasphp\Assets\Tests\Feature;

use Atlasphp\Assets\Support\ConfigValidator;
use Atlasphp\Assets\Tests\TestCase;
use InvalidArgumentException;

/**
 * Class ConfigTest
 *
 * Ensures the atlas_assets configuration defaults align with the PRD and
 * validates misconfigurations.
 * PRD Reference: Atlas Assets Overview — Configuration.
 */
final class ConfigTest extends TestCase
{
    public function test_exposes_default_configuration_values(): void
    {
        self::assertSame('s3', config('atlas_assets.disk'));
        self::assertSame('public', config('atlas_assets.visibility'));
        self::assertFalse((bool) config('atlas_assets.delete_files_on_soft_delete'));
        self::assertSame(
            '{model_type}/{model_id}/{uuid}.{extension}',
            config('atlas_assets.path.pattern')
        );
        self::assertNull(config('atlas_assets.path.resolver'));
    }

    public function test_validates_configuration_when_defaults_are_used(): void
    {
        $validator = $this->app->make(ConfigValidator::class);
        $validator->validate(config('atlas_assets'));

        $this->addToAssertionCount(1);
    }

    public function test_rejects_configuration_when_disk_is_invalid(): void
    {
        $validator = $this->app->make(ConfigValidator::class);

        $config = config('atlas_assets');
        $config['disk'] = '';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('disk must be defined');

        $validator->validate($config);
    }

    public function test_rejects_configuration_when_path_inputs_are_invalid(): void
    {
        $validator = $this->app->make(ConfigValidator::class);

        $config = config('atlas_assets');
        $config['path']['pattern'] = '';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('path pattern or resolver');

        $validator->validate($config);
    }
}
