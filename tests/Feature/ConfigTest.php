<?php

declare(strict_types=1);

namespace Atlas\Assets\Tests\Feature;

use Atlas\Assets\Tests\TestCase;

/**
 * Class ConfigTest
 *
 * Ensures the atlas-assets configuration defaults align with the PRD.
 * PRD Reference: Atlas Assets Overview — Configuration defaults.
 */
final class ConfigTest extends TestCase
{
    public function test_exposes_default_configuration_values(): void
    {
        self::assertSame('public', config('atlas-assets.disk'));
        self::assertSame('public', config('atlas-assets.visibility'));
        self::assertFalse((bool) config('atlas-assets.delete_files_on_soft_delete'));
        self::assertTrue((bool) config('atlas-assets.routes.stream.enabled'));
        self::assertSame('atlas-assets/stream/{asset}', config('atlas-assets.routes.stream.uri'));
        self::assertSame('atlas-assets.stream', config('atlas-assets.routes.stream.name'));
        self::assertSame(
            ['signed', \Illuminate\Routing\Middleware\SubstituteBindings::class],
            config('atlas-assets.routes.stream.middleware')
        );
        self::assertSame(
            '{model_type}/{model_id}/{file_name}.{extension}',
            config('atlas-assets.path.pattern')
        );
        self::assertNull(config('atlas-assets.path.resolver'));
        self::assertSame('atlas_assets', config('atlas-assets.tables.assets'));
        self::assertNull(config('atlas-assets.database.connection'));
        self::assertSame([], config('atlas-assets.uploads.allowed_extensions'));
        self::assertSame([], config('atlas-assets.uploads.blocked_extensions'));
        self::assertSame(10 * 1024 * 1024, config('atlas-assets.uploads.max_file_size'));
    }
}
