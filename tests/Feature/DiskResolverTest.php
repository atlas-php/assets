<?php

declare(strict_types=1);

namespace Atlas\Assets\Tests\Feature;

use Atlas\Assets\Support\DiskResolver;
use Atlas\Assets\Tests\TestCase;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Mockery;

/**
 * Class DiskResolverTest
 *
 * Validates disk resolution behavior including configuration fallbacks.
 * PRD Reference: Atlas Assets Overview — Storage Configuration.
 */
final class DiskResolverTest extends TestCase
{
    public function test_resolves_filesystems_default_when_package_disk_is_missing(): void
    {
        $filesystem = Mockery::mock(Filesystem::class);

        $factory = Mockery::mock(FilesystemFactory::class);
        $factory->shouldReceive('disk')
            ->once()
            ->with('assets')
            ->andReturn($filesystem);

        $config = Mockery::mock(ConfigRepository::class);
        $config->shouldReceive('get')
            ->once()
            ->with('atlas-assets.disk')
            ->andReturn(null);
        $config->shouldReceive('get')
            ->once()
            ->with('filesystems.default', 'public')
            ->andReturn('assets');

        $resolver = new DiskResolver($factory, $config);

        self::assertSame($filesystem, $resolver->resolve());
    }

    public function test_resolves_public_disk_when_no_default_configured(): void
    {
        $filesystem = Mockery::mock(Filesystem::class);

        $factory = Mockery::mock(FilesystemFactory::class);
        $factory->shouldReceive('disk')
            ->once()
            ->with('public')
            ->andReturn($filesystem);

        $config = Mockery::mock(ConfigRepository::class);
        $config->shouldReceive('get')
            ->once()
            ->with('atlas-assets.disk')
            ->andReturn('   ');
        $config->shouldReceive('get')
            ->once()
            ->with('filesystems.default', 'public')
            ->andReturn('public');

        $resolver = new DiskResolver($factory, $config);

        self::assertSame($filesystem, $resolver->resolve());
    }
}
