<?php

declare(strict_types=1);

namespace Atlas\Assets\Support;

use InvalidArgumentException;

/**
 * Class ConfigValidator
 *
 * Provides guardrails for atlas-assets configuration to ensure disk, visibility,
 * and path resolver settings follow the PRD baseline defaults before usage.
 * PRD Reference: Atlas Assets Overview — Configuration.
 */
class ConfigValidator
{
    private const SUPPORTED_PLACEHOLDERS = [
        'model_type',
        'model_id',
        'user_id',
        'original_name',
        'extension',
        'random',
        'uuid',
        'file_name',
    ];

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

        if (isset($path['resolver']) && $path['resolver'] !== null && ! $hasResolver) {
            throw new InvalidArgumentException('The path resolver must be a callable.');
        }

        if ($hasPattern) {
            $this->validatePlaceholders($pattern);
        }

        $uploads = $config['uploads'] ?? [];
        $this->validateUploadsConfig($uploads);
    }

    private function validatePlaceholders(string $pattern): void
    {
        preg_match_all('/\{([^{}]+)\}/', $pattern, $matches);

        foreach ($matches[1] as $match) {
            if ($this->isValidPlaceholder($match)) {
                continue;
            }

            throw new InvalidArgumentException(sprintf('Unsupported path placeholder [%s] in atlas-assets configuration.', $match));
        }
    }

    private function isValidPlaceholder(string $placeholder): bool
    {
        if ($placeholder === '') {
            return false;
        }

        if (str_starts_with($placeholder, 'date:')) {
            return strlen(substr($placeholder, 5)) > 0;
        }

        return in_array($placeholder, self::SUPPORTED_PLACEHOLDERS, true);
    }

    /**
     * @param  array<string, mixed>  $uploads
     */
    private function validateUploadsConfig(array $uploads): void
    {
        foreach (['allowed_extensions', 'blocked_extensions'] as $key) {
            if (! array_key_exists($key, $uploads) || $uploads[$key] === null) {
                continue;
            }

            if (! is_array($uploads[$key])) {
                throw new InvalidArgumentException(sprintf('The uploads.%s value must be an array of extensions.', $key));
            }

            foreach ($uploads[$key] as $extension) {
                if ($extension instanceof \Stringable) {
                    $extension = (string) $extension;
                }

                if (! is_string($extension) || trim($extension) === '') {
                    throw new InvalidArgumentException(sprintf('Each uploads.%s entry must be a non-empty string.', $key));
                }
            }
        }
    }
}
