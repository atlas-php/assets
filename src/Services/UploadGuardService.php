<?php

declare(strict_types=1);

namespace Atlas\Assets\Services;

use Atlas\Assets\Exceptions\DisallowedExtensionException;
use Atlas\Assets\Exceptions\UploadSizeLimitException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Class UploadGuardService
 *
 * Applies upload constraints defined in atlas-assets configuration, including
 * whitelist, blocklist, and per-call overrides for file extensions.
 * PRD Reference: Atlas Assets Overview — Uploading & Updating.
 */
class UploadGuardService
{
    public function __construct(private readonly Repository $config) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function validate(UploadedFile $file, array $attributes = []): void
    {
        $this->assertExtensionAllowed($file, $attributes);
        $this->assertSizeWithinLimit($file, $attributes);
    }

    private function extension(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension() ?: $file->extension() ?: 'bin';

        return Str::lower(ltrim((string) $extension, '.'));
    }

    /**
     * @return array<int, string>|null
     */
    private function normalizeExtensions(mixed $extensions, bool $treatEmptyAsNull = true): ?array
    {
        if ($extensions === null) {
            return null;
        }

        if (is_string($extensions) || $extensions instanceof \Stringable) {
            $extensions = [(string) $extensions];
        }

        if (! is_array($extensions)) {
            return null;
        }

        $normalized = array_values(array_filter(array_map(static function ($extension): ?string {
            if ($extension instanceof \Stringable) {
                $extension = (string) $extension;
            }

            $value = trim((string) $extension);

            if ($value === '') {
                return null;
            }

            return Str::lower(ltrim($value, '.'));
        }, $extensions)));

        if ($normalized === []) {
            return $treatEmptyAsNull ? null : [];
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function assertExtensionAllowed(UploadedFile $file, array $attributes): void
    {
        $extension = $this->extension($file);

        $blocked = $this->normalizeExtensions($this->config->get('atlas-assets.uploads.blocked_extensions'));

        if ($blocked !== null && in_array($extension, $blocked, true)) {
            throw new DisallowedExtensionException(sprintf('The extension [%s] is blocked for asset uploads.', $extension));
        }

        $hasOverride = array_key_exists('allowed_extensions', $attributes);

        $allowed = $hasOverride
            ? $this->normalizeExtensions($attributes['allowed_extensions'], false)
            : $this->normalizeExtensions($this->config->get('atlas-assets.uploads.allowed_extensions'));

        if ($allowed !== null && ! in_array($extension, $allowed, true)) {
            $message = $hasOverride
                ? sprintf('The extension [%s] is not included in the provided allowed extensions list.', $extension)
                : sprintf('The extension [%s] is not allowed by the configured whitelist.', $extension);

            throw new DisallowedExtensionException($message);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function assertSizeWithinLimit(UploadedFile $file, array $attributes): void
    {
        $limit = $this->resolveSizeLimit($attributes);

        if ($limit === null) {
            return;
        }

        $size = $file->getSize();

        if (! is_int($size)) {
            return;
        }

        if ($size > $limit) {
            throw new UploadSizeLimitException(sprintf(
                'The uploaded file exceeds the maximum allowed size of %s bytes.',
                number_format($limit)
            ));
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resolveSizeLimit(array $attributes): ?int
    {
        if (array_key_exists('max_upload_size', $attributes)) {
            $override = $attributes['max_upload_size'];

            if ($override === null) {
                return null;
            }

            return $this->normalizeSizeLimit($override);
        }

        return $this->normalizeSizeLimit($this->config->get('atlas-assets.uploads.max_file_size'));
    }

    private function normalizeSizeLimit(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \Stringable) {
            $value = (string) $value;
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return null;
            }
        }

        if (! is_numeric($value)) {
            return null;
        }

        $limit = (int) $value;

        return $limit > 0 ? $limit : null;
    }
}
