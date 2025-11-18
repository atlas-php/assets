<?php

declare(strict_types=1);

namespace Atlas\Assets\Services;

use Atlas\Assets\Models\Asset;
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
        private readonly AssetService $assetService,
        private readonly AssetRetrievalService $retrievalService,
        private readonly AssetCleanupService $cleanupService,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upload(UploadedFile $file, array $attributes = []): Asset
    {
        return $this->assetService->upload($file, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function uploadForModel(Model $model, UploadedFile $file, array $attributes = []): Asset
    {
        return $this->assetService->uploadForModel($model, $file, $attributes);
    }

    /**
     * Update asset metadata or replace the underlying file.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Asset $asset, array $attributes = [], ?UploadedFile $file = null, ?Model $model = null): Asset
    {
        return $this->assetService->update($asset, $attributes, $file, $model);
    }

    /**
     * Replace the stored file and optionally adjust metadata.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function replace(Asset $asset, UploadedFile $file, array $attributes = [], ?Model $model = null): Asset
    {
        return $this->assetService->replace($asset, $file, $attributes, $model);
    }

    public function find(int|string $id): ?Asset
    {
        return $this->retrievalService->find($id);
    }

    /**
     * Fluent alias for retrieving assets associated with a model.
     *
     * @param  array{label?: string|null, category?: string|null}  $filters
     * @return Builder<Asset>
     */
    public function forModel(Model $model, array $filters = [], ?int $limit = null): Builder
    {
        return $this->retrievalService->forModel($model, $filters, $limit);
    }

    /**
     * Fluent alias for retrieving assets associated with a user.
     *
     * @param  array{label?: string|null, category?: string|null}  $filters
     * @return Builder<Asset>
     */
    public function forUser(int|string $userId, array $filters = [], ?int $limit = null): Builder
    {
        return $this->retrievalService->forUser($userId, $filters, $limit);
    }

    /**
     * @param  array{label?: string|null, category?: string|null}  $filters
     * @return Builder<Asset>
     */
    public function listForModel(Model $model, array $filters = [], ?int $limit = null): Builder
    {
        return $this->retrievalService->listForModel($model, $filters, $limit);
    }

    /**
     * @param  array{label?: string|null, category?: string|null}  $filters
     * @return Builder<Asset>
     */
    public function listForUser(int|string $userId, array $filters = [], ?int $limit = null): Builder
    {
        return $this->retrievalService->listForUser($userId, $filters, $limit);
    }

    public function download(Asset $asset): string
    {
        return $this->retrievalService->download($asset);
    }

    public function exists(Asset $asset): bool
    {
        return $this->retrievalService->exists($asset);
    }

    public function temporaryUrl(Asset $asset, int $minutes = 5): string
    {
        return $this->retrievalService->temporaryUrl($asset, $minutes);
    }

    public function delete(Asset $asset): void
    {
        $this->cleanupService->delete($asset);
    }

    public function purge(): int
    {
        return $this->cleanupService->purge();
    }
}
