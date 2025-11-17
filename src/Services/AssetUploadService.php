<?php

declare(strict_types=1);

namespace Atlas\Assets\Services;

use Atlas\Assets\Models\Asset;
use Atlas\Assets\Support\PathResolver;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Class AssetUploadService
 *
 * Handles uploading files to the configured disk and persisting asset metadata.
 * PRD Reference: Atlas Assets Overview — Uploading.
 */
class AssetUploadService
{
    public function __construct(
        private readonly FilesystemFactory $filesystem,
        private readonly PathResolver $pathResolver,
        private readonly Repository $config
    ) {}

    /**
     * Upload a file without any associated model context.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function upload(UploadedFile $file, array $attributes = []): Asset
    {
        return $this->store($file, null, $attributes);
    }

    /**
     * Upload a file that belongs to a specific model instance.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function uploadForModel(Model $model, UploadedFile $file, array $attributes = []): Asset
    {
        return $this->store($file, $model, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function store(UploadedFile $file, ?Model $model, array $attributes): Asset
    {
        $diskName = (string) $this->config->get('atlas-assets.disk', 's3');
        $visibility = (string) $this->config->get('atlas-assets.visibility', 'public');
        $filesystem = $this->filesystem->disk($diskName);

        $path = $this->pathResolver->resolve($file, $model, $attributes);

        $stream = fopen($file->getRealPath(), 'rb');

        if ($stream === false) {
            throw new RuntimeException('Failed to open uploaded file stream.');
        }

        try {
            $filesystem->put($path, $stream, ['visibility' => $visibility]);
        } finally {
            fclose($stream);
        }

        return Asset::query()->create($this->buildPayload($file, $model, $path, $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function buildPayload(UploadedFile $file, ?Model $model, string $path, array $attributes): array
    {
        $mime = $file->getClientMimeType();
        if (! is_string($mime) || $mime === '') {
            $mime = $file->getMimeType() ?: 'application/octet-stream';
        }

        $size = $file->getSize();
        if (! is_int($size)) {
            $size = 0;
        }

        $name = $attributes['name'] ?? $file->getClientOriginalName();
        if (! is_string($name) || $name === '') {
            $name = $file->getFilename();
        }

        return [
            'user_id' => $attributes['user_id'] ?? null,
            'model_type' => $model?->getMorphClass(),
            'model_id' => $model?->getKey(),
            'file_type' => $mime,
            'file_path' => $path,
            'file_size' => $size,
            'name' => $name,
            'label' => $attributes['label'] ?? null,
            'category' => $attributes['category'] ?? null,
        ];
    }
}
