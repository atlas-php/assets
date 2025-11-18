<?php

declare(strict_types=1);

namespace Atlas\Assets\Services;

use Atlas\Assets\Models\Asset;
use Atlas\Assets\Support\DiskResolver;
use Atlas\Assets\Support\PathResolver;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Class AssetService
 *
 * Manages persistence and updates for Atlas Assets, including file uploads,
 * replacements, and metadata changes.
 * PRD Reference: Atlas Assets Overview — Uploading & Updating.
 */
class AssetService
{
    public function __construct(
        private readonly DiskResolver $diskResolver,
        private readonly PathResolver $pathResolver,
        private readonly Repository $config
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upload(UploadedFile $file, array $attributes = []): Asset
    {
        return $this->persist($file, null, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function uploadForModel(Model $model, UploadedFile $file, array $attributes = []): Asset
    {
        return $this->persist($file, $model, $attributes);
    }

    /**
     * Update asset metadata or replace the stored file.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Asset $asset, array $attributes = [], ?UploadedFile $file = null, ?Model $model = null): Asset
    {
        $updates = [];

        if ($model !== null) {
            $updates['model_type'] = $model->getMorphClass();
            $updates['model_id'] = $model->getKey();
        }

        if ($file !== null) {
            /** @var array{path: string, type: string, size: int, original_name: string} $fileData */
            $fileData = $this->storeFile(
                $file,
                $model,
                ['user_id' => $attributes['user_id'] ?? $asset->user_id] + $attributes
            );

            $oldPath = $asset->file_path;

            $updates['file_path'] = $fileData['path'];
            $updates['file_type'] = $fileData['type'];
            $updates['file_size'] = $fileData['size'];
            $updates['name'] = $attributes['name'] ?? $fileData['original_name'];
            $updates['original_file_name'] = $fileData['original_name'];

            if ($fileData['path'] !== $oldPath && $this->disk()->exists($oldPath)) {
                $this->disk()->delete($oldPath);
            }
        }

        foreach (['name', 'label', 'category'] as $field) {
            if (array_key_exists($field, $attributes)) {
                $updates[$field] = $this->sanitizeString($attributes[$field]);
            }
        }

        if (array_key_exists('user_id', $attributes)) {
            $updates['user_id'] = $attributes['user_id'];
        }

        if ($updates === []) {
            return $asset;
        }

        $this->ensureUniquePath($updates['file_path'] ?? null, $asset->id);

        $asset->update($updates);

        return $asset->refresh();
    }

    /**
     * Replace the existing storage object while allowing metadata overrides.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function replace(Asset $asset, UploadedFile $file, array $attributes = [], ?Model $model = null): Asset
    {
        return $this->update($asset, $attributes, $file, $model);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function persist(UploadedFile $file, ?Model $model, array $attributes): Asset
    {
        /** @var array{path: string, type: string, size: int, original_name: string} $fileData */
        $fileData = $this->storeFile($file, $model, $attributes);

        $this->ensureUniquePath($fileData['path']);

        return Asset::query()->create([
            'user_id' => $attributes['user_id'] ?? null,
            'model_type' => $model?->getMorphClass(),
            'model_id' => $model?->getKey(),
            'file_type' => $fileData['type'],
            'file_path' => $fileData['path'],
            'file_size' => $fileData['size'],
            'name' => $this->sanitizeString($attributes['name'] ?? $fileData['original_name']) ?? $fileData['original_name'],
            'original_file_name' => $fileData['original_name'],
            'label' => $this->sanitizeString($attributes['label'] ?? null),
            'category' => $this->sanitizeString($attributes['category'] ?? null),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{path: string, type: string, size: int, original_name: string}
     */
    private function storeFile(UploadedFile $file, ?Model $model, array $attributes): array
    {
        $disk = $this->disk();
        $visibility = (string) $this->config->get('atlas-assets.visibility', 'public');
        $path = $this->pathResolver->resolve($file, $model, $attributes);

        $realPath = $file->getRealPath();

        if (! is_string($realPath) || $realPath === '') {
            throw new RuntimeException('Failed to open uploaded file stream.');
        }

        $stream = fopen($realPath, 'rb');

        if ($stream === false) {
            throw new RuntimeException('Failed to open uploaded file stream.');
        }

        try {
            $disk->put($path, $stream, ['visibility' => $visibility]);
        } finally {
            fclose($stream);
        }

        $mime = $file->getClientMimeType() ?: $file->getMimeType() ?: 'application/octet-stream';
        $size = $file->getSize();

        if (! is_int($size)) {
            $size = 0;
        }

        $originalName = $this->sanitizeString($file->getClientOriginalName()) ?? $this->sanitizeString($file->getFilename()) ?? 'file';

        return [
            'path' => $path,
            'type' => $mime,
            'size' => $size,
            'original_name' => $originalName,
        ];
    }

    private function disk(): Filesystem
    {
        return $this->diskResolver->resolve();
    }

    private function sanitizeString(mixed $value, int $limit = 255): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        if ($string === '') {
            return null;
        }

        return Str::limit($string, $limit, '');
    }

    private function ensureUniquePath(?string $path, ?int $ignoreId = null): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $exists = Asset::withTrashed()
            ->when($ignoreId, static fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('file_path', $path)
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException("An asset already uses the path [{$path}].");
        }
    }
}
