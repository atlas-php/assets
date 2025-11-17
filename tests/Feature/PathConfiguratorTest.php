<?php

declare(strict_types=1);

namespace Atlas\Assets\Tests\Feature;

use Atlas\Assets\Support\PathConfigurator;
use Atlas\Assets\Support\PathResolver;
use Atlas\Assets\Tests\TestCase;
use Illuminate\Http\UploadedFile;

/**
 * Class PathConfiguratorTest
 *
 * Ensures helper APIs can override the path resolver.
 * PRD Reference: Atlas Assets Overview — Configuration.
 */
final class PathConfiguratorTest extends TestCase
{
    public function test_use_callback_overrides_resolver(): void
    {
        PathConfigurator::useCallback(static fn () => 'custom/path.txt');

        $resolver = $this->app->make(PathResolver::class);
        $path = $resolver->resolve(UploadedFile::fake()->create('ignored.txt', 1));

        self::assertSame('custom/path.txt', $path);

        PathConfigurator::clear();
    }

    public function test_use_service_registers_invokable_class(): void
    {
        PathConfigurator::useService(TestPathService::class);

        $resolver = $this->app->make(PathResolver::class);
        $path = $resolver->resolve(UploadedFile::fake()->create('Image.png', 1), null, ['user_id' => 5]);

        self::assertSame('users/5/image.png', $path);

        PathConfigurator::clear();
    }
}

class TestPathService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke($model, UploadedFile $file, array $attributes): string
    {
        $user = $attributes['user_id'] ?? 'anon';

        return sprintf('users/%s/%s', $user, strtolower($file->getClientOriginalName()));
    }
}
