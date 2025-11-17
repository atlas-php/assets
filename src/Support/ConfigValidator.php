<?php

declare(strict_types=1);

namespace Atlasphp\Assets\Support;

use InvalidArgumentException;

/**
 * Class ConfigValidator
 *
 * Provides guardrails for atlas_assets configuration to ensure disk, visibility,
 * and path resolver settings follow the PRD baseline defaults before usage.
 * PRD Reference: Atlas Assets Overview — Configuration.
 */
class ConfigValidator
{
    /**
     * Validate configuration structure and values.
     *
     * @param  array<string, mixed>  $config
     */
    public function validate(array $config): void
    {
        if (! isset($config['disk']) || $config['disk'] === '') {
            throw new InvalidArgumentException('The assets disk must be defined.');
        }

        if (! isset($config['visibility']) || $config['visibility'] === '') {
            throw new InvalidArgumentException('The assets visibility must be defined.');
        }

        $path = $config['path'] ?? [];
        $pattern = $path['pattern'] ?? null;
        $resolver = $path['resolver'] ?? null;

        $hasPattern = is_string($pattern) && $pattern !== '';
        $hasResolver = is_callable($resolver);

        if (! $hasPattern && ! $hasResolver) {
            throw new InvalidArgumentException('A path pattern or resolver callback must be provided.');
        }
    }
}
