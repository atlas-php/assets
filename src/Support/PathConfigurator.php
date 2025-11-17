<?php

declare(strict_types=1);

namespace Atlas\Assets\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

/**
 * Class PathConfigurator
 *
 * Provides runtime helpers for customizing atlas-assets path resolution.
 * PRD Reference: Atlas Assets Overview — Configuration.
 */
class PathConfigurator
{
    /**
     * Register a callback to override the configured path pattern.
     */
    public static function useCallback(callable $resolver): void
    {
        config(['atlas-assets.path.resolver' => $resolver]);
    }

    /**
     * Register a service class/method as the path resolver.
     *
     * @param  class-string  $class
     */
    public static function useService(string $class, string $method = '__invoke'): void
    {
        self::useCallback(static function (?Model $model, UploadedFile $file, array $attributes) use ($class, $method): string {
            $service = app($class);

            return $service->{$method}($model, $file, $attributes);
        });
    }

    /**
     * Restore the default pattern-based resolver.
     */
    public static function clear(): void
    {
        config(['atlas-assets.path.resolver' => null]);
    }
}
