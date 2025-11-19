<?php

declare(strict_types=1);

namespace Atlas\Assets;

use Atlas\Assets\Models\Asset;
use Atlas\Assets\Services\AssetFileService;
use Atlas\Assets\Services\AssetModelService;
use Atlas\Assets\Services\AssetPurgeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

/**
 * Class AssetManager
 *
 * Provides a consumer-facing facade root that centralizes upload, retrieval,
 * and cleanup operations exposed via the Assets facade.
 * PRD Reference: Atlas Assets Overview — Public APIs.
 */
class AssetManager
{
    public function __construct(
        private readonly AssetModelService $assetModelService,
        private readonly AssetFileService $assetFileService,
        private readonly AssetPurgeService $assetPurgeService,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upload(UploadedFile $file, array $attributes = []): Asset
    {
        return $this->assetModelService->upload($file, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function uploadForModel(Model $model, UploadedFile $file, array $attributes = []): Asset
    {
        return $this->assetModelService->uploadForModel($model, $file, $attributes);
    }

    /**
     * Update asset metadata or replace the underlying file.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Asset $asset, array $attributes = [], ?UploadedFile $file = null, ?Model $model = null): Asset
    {
        return $this->assetModelService->updateAsset($asset, $attributes, $file, $model);
    }

    /**
     * Replace the stored file and optionally adjust metadata.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function replace(Asset $asset, UploadedFile $file, array $attributes = [], ?Model $model = null): Asset
    {
        return $this->assetModelService->replaceAsset($asset, $file, $attributes, $model);
    }

    public function find(int|string $id): ?Asset
    {
        return $this->assetModelService->find($id);
    }

    /**
     * @param  array{label?: string|null, category?: string|null}  $filters
     * @return Builder<Asset>
     */
    public function forModel(Model $model, array $filters = [], ?int $limit = null): Builder
    {
        return $this->assetModelService->forModel($model, $filters, $limit);
    }

    /**
     * @param  array{label?: string|null, category?: string|null}  $filters
     * @return Builder<Asset>
     */
    public function forUser(int|string $userId, array $filters = [], ?int $limit = null): Builder
    {
        return $this->assetModelService->forUser($userId, $filters, $limit);
    }

    public function download(Asset $asset): string
    {
        return $this->assetFileService->download($asset);
    }

    public function exists(Asset $asset): bool
    {
        return $this->assetFileService->exists($asset);
    }

    public function temporaryUrl(Asset $asset, int $minutes = 5): string
    {
        return $this->assetFileService->temporaryUrl($asset, $minutes);
    }

    public function delete(Asset $asset, bool $forceDelete = false): void
    {
        $this->assetModelService->delete($asset, $forceDelete);
    }

    public function purge(): int
    {
        return $this->assetPurgeService->purge();
    }
}
