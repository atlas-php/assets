<?php

declare(strict_types=1);

namespace Atlas\Assets\Services;

use Atlas\Assets\Models\Asset;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Class AssetRetrievalService
 *
 * Provides querying helpers and download/temporary URL utilities.
 * PRD Reference: Atlas Assets Overview — Retrieval APIs.
 */
class AssetRetrievalService
{
    public function __construct(
        private readonly FilesystemFactory $filesystem,
        private readonly Repository $config
    ) {}

    public function find(int|string $id): ?Asset
    {
        return Asset::query()->find($id);
    }

    /**
     * @param  array{label?: string|null, category?: string|null}  $filters
     * @return Collection<int, Asset>
     */
    public function listForModel(Model $model, array $filters = [], ?int $limit = null): Collection
    {
        $query = $this->modelQuery($model, $filters);

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * @param  array{label?: string|null, category?: string|null}  $filters
     * @return Collection<int, Asset>
     */
    public function listForUser(int|string $userId, array $filters = [], ?int $limit = null): Collection
    {
        $query = $this->userQuery($userId, $filters);

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * @param  array{label?: string|null, category?: string|null}  $filters
     * @return LengthAwarePaginator<Asset>
     */
    public function paginateForModel(Model $model, array $filters = [], int $perPage = 15, string $pageName = 'page', ?int $page = null): LengthAwarePaginator
    {
        return $this->modelQuery($model, $filters)->paginate($perPage, ['*'], $pageName, $page);
    }

    /**
     * @param  array{label?: string|null, category?: string|null}  $filters
     * @return LengthAwarePaginator<Asset>
     */
    public function paginateForUser(int|string $userId, array $filters = [], int $perPage = 15, string $pageName = 'page', ?int $page = null): LengthAwarePaginator
    {
        return $this->userQuery($userId, $filters)->paginate($perPage, ['*'], $pageName, $page);
    }

    /**
     * @param  array{label?: string|null, category?: string|null}  $filters
     * @return CursorPaginator<Asset>
     */
    public function cursorPaginateForModel(Model $model, array $filters = [], int $perPage = 15, string $cursorName = 'cursor', ?Cursor $cursor = null): CursorPaginator
    {
        return $this->modelQuery($model, $filters)->cursorPaginate($perPage, ['*'], $cursorName, $cursor);
    }

    /**
     * @param  array{label?: string|null, category?: string|null}  $filters
     * @return CursorPaginator<Asset>
     */
    public function cursorPaginateForUser(int|string $userId, array $filters = [], int $perPage = 15, string $cursorName = 'cursor', ?Cursor $cursor = null): CursorPaginator
    {
        return $this->userQuery($userId, $filters)->cursorPaginate($perPage, ['*'], $cursorName, $cursor);
    }

    public function download(Asset $asset): string
    {
        $disk = $this->disk();
        $this->guardFileExists($disk, $asset);

        return $disk->get($asset->file_path);
    }

    public function exists(Asset $asset): bool
    {
        return $this->disk()->exists($asset->file_path);
    }

    public function temporaryUrl(Asset $asset, int $minutes = 5): string
    {
        $disk = $this->disk();

        $this->guardFileExists($disk, $asset);

        if ($this->supportsTemporaryUrls($disk)) {
            try {
                return $disk->temporaryUrl($asset->file_path, Carbon::now()->addMinutes($minutes));
            } catch (RuntimeException) {
                // Fall back to inline response below.
            }
        }

        return $this->inlineDataUrl($disk, $asset);
    }

    /**
     * @param  Builder<Asset>  $builder
     * @param  array{label?: string|null, category?: string|null}  $filters
     * @return Builder<Asset>
     */
    private function applyFilters(Builder $builder, array $filters): Builder
    {
        if (array_key_exists('label', $filters) && $filters['label'] !== null) {
            $builder->where('label', $filters['label']);
        }

        if (array_key_exists('category', $filters) && $filters['category'] !== null) {
            $builder->where('category', $filters['category']);
        }

        return $builder->orderByDesc('id');
    }

    /**
     * @param  array{label?: string|null, category?: string|null}  $filters
     * @return Builder<Asset>
     */
    private function modelQuery(Model $model, array $filters): Builder
    {
        return $this->applyFilters(
            Asset::query()
                ->where('model_type', $model->getMorphClass())
                ->where('model_id', $model->getKey()),
            $filters
        );
    }

    /**
     * @param  array{label?: string|null, category?: string|null}  $filters
     * @return Builder<Asset>
     */
    private function userQuery(int|string $userId, array $filters): Builder
    {
        return $this->applyFilters(
            Asset::query()->where('user_id', $userId),
            $filters
        );
    }

    private function disk(): Filesystem
    {
        $disk = $this->config->get('atlas-assets.disk', 's3');

        return $this->filesystem->disk($disk);
    }

    private function supportsTemporaryUrls(Filesystem $disk): bool
    {
        return method_exists($disk, 'temporaryUrl');
    }

    private function inlineDataUrl(Filesystem $disk, Asset $asset): string
    {
        $this->guardFileExists($disk, $asset);

        $contents = $disk->get($asset->file_path);
        $base64 = base64_encode($contents);
        $mime = $asset->file_type ?: 'application/octet-stream';

        return sprintf('data:%s;base64,%s', $mime, $base64);
    }

    private function guardFileExists(Filesystem $disk, Asset $asset): void
    {
        if (! $disk->exists($asset->file_path)) {
            throw new RuntimeException(sprintf('Asset file [%s] not found on disk.', $asset->file_path));
        }
    }
}
