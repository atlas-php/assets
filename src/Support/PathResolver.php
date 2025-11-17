<?php

declare(strict_types=1);

namespace Atlas\Assets\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Class PathResolver
 *
 * Resolves storage paths using configured patterns or callback overrides.
 * PRD Reference: Atlas Assets Overview — Path Resolution.
 */
class PathResolver
{
    public function __construct(private readonly Repository $config) {}

    /**
     * Resolve a storage path for the provided file and optional model context.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function resolve(UploadedFile $file, ?Model $model = null, array $attributes = []): string
    {
        $callback = $this->config->get('atlas-assets.path.resolver');

        if (is_callable($callback)) {
            $result = $callback($model, $file, $attributes);

            if (! is_string($result) || $result === '') {
                throw new InvalidArgumentException('The atlas-assets path resolver must return a non-empty string.');
            }

            return $this->normalizePath($result);
        }

        $pattern = (string) $this->config->get('atlas-assets.path.pattern', '{uuid}.{extension}');

        $path = preg_replace_callback('/\{([^{}]+)\}/', function ($matches) use ($file, $model, $attributes): string {
            return $this->resolvePlaceholder($matches[1], $file, $model, $attributes);
        }, $pattern);

        return $this->normalizePath($path ?? $pattern);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resolvePlaceholder(string $placeholder, UploadedFile $file, ?Model $model, array $attributes): string
    {
        return match (true) {
            $placeholder === 'model_type' => $this->modelType($model),
            $placeholder === 'model_id' => $this->modelId($model),
            $placeholder === 'user_id' => $this->userId($attributes),
            $placeholder === 'original_name' => $this->originalName($file),
            $placeholder === 'file_name' => $this->fileName($file),
            $placeholder === 'extension' => $this->extension($file),
            $placeholder === 'random' => Str::lower(Str::random(16)),
            $placeholder === 'uuid' => Str::uuid()->toString(),
            str_starts_with($placeholder, 'date:') => $this->datePlaceholder($placeholder),
            default => '',
        };
    }

    private function modelType(?Model $model): string
    {
        return $model === null
            ? ''
            : Str::snake(class_basename($model));
    }

    private function modelId(?Model $model): string
    {
        $key = $model?->getKey();

        return $key === null ? '' : (string) $key;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function userId(array $attributes): string
    {
        $userId = $attributes['user_id'] ?? null;

        return ($userId === null || $userId === '') ? '' : (string) $userId;
    }

    private function originalName(UploadedFile $file): string
    {
        $name = (string) pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        if ($name === '') {
            $name = (string) pathinfo($file->getFilename(), PATHINFO_FILENAME);
        }

        return $name === '' ? '' : Str::slug($name, '_');
    }

    private function fileName(UploadedFile $file): string
    {
        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        if (! is_string($name) || $name === '') {
            $name = pathinfo($file->getFilename(), PATHINFO_FILENAME);
        }

        if (! is_string($name) || $name === '') {
            return '';
        }

        return Str::slug($name, '_');
    }

    private function extension(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension() ?: $file->extension() ?: 'bin';

        return Str::lower($extension);
    }

    private function datePlaceholder(string $placeholder): string
    {
        $format = substr($placeholder, 5);

        if ($format === '') {
            $format = 'YmdHis';
        }

        return Carbon::now()->format($format);
    }

    private function normalizePath(string $path): string
    {
        $normalized = preg_replace('#/{2,}#', '/', str_replace('\\', '/', $path));

        if ($normalized === null) {
            $normalized = $path;
        }

        $segments = array_values(array_filter(
            explode('/', $normalized),
            static fn ($segment) => $segment !== ''
        ));

        $normalizedPath = trim(implode('/', $segments), '/');

        return $normalizedPath === '' ? 'asset' : $normalizedPath;
    }
}
