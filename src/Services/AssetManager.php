<?php

declare(strict_types=1);

namespace Atlas\Assets\Services;

use Atlas\Assets\Models\Asset;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\Cursor;

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
     * @param  array{label?: string|null, category?: string|null}  $filters
     * @return Collection<int, Asset>
     */
    public function listForModel(Model $model, array $filters = [], ?int $limit = null): Collection
    {
        return $this->retrievalService->listForModel($model, $filters, $limit);
    }

    /**
     * @param  array{label?: string|null, category?: string|null}  $filters
     * @return Collection<int, Asset>
     */
    public function listForUser(int|string $userId, array $filters = [], ?int $limit = null): Collection
    {
        return $this->retrievalService->listForUser($userId, $filters, $limit);
    }

    /**
     * @param  array{label?: string|null, category?: string|null}  $filters
     * @return LengthAwarePaginator<Asset>
     */
    public function paginateForModel(Model $model, array $filters = [], int $perPage = 15, string $pageName = 'page', ?int $page = null): LengthAwarePaginator
    {
        return $this->retrievalService->paginateForModel($model, $filters, $perPage, $pageName, $page);
    }

    /**
     * @param  array{label?: string|null, category?: string|null}  $filters
     * @return LengthAwarePaginator<Asset>
     */
    public function paginateForUser(int|string $userId, array $filters = [], int $perPage = 15, string $pageName = 'page', ?int $page = null): LengthAwarePaginator
    {
        return $this->retrievalService->paginateForUser($userId, $filters, $perPage, $pageName, $page);
    }

    /**
     * @param  array{label?: string|null, category?: string|null}  $filters
     * @return CursorPaginator<Asset>
     */
    public function cursorPaginateForModel(Model $model, array $filters = [], int $perPage = 15, string $cursorName = 'cursor', ?Cursor $cursor = null): CursorPaginator
    {
        return $this->retrievalService->cursorPaginateForModel($model, $filters, $perPage, $cursorName, $cursor);
    }

    /**
     * @param  array{label?: string|null, category?: string|null}  $filters
     * @return CursorPaginator<Asset>
     */
    public function cursorPaginateForUser(int|string $userId, array $filters = [], int $perPage = 15, string $cursorName = 'cursor', ?Cursor $cursor = null): CursorPaginator
    {
        return $this->retrievalService->cursorPaginateForUser($userId, $filters, $perPage, $cursorName, $cursor);
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
