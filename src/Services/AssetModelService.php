<?php

declare(strict_types=1);

namespace Atlas\Assets\Services;

use Atlas\Assets\Models\Asset;
use Atlas\Core\Services\ModelService;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Class AssetModelService
 *
 * Provides CRUD helpers for the Asset model so higher-level services can focus on file operations.
 * Also coordinates file cleanup so consumers can confidently delete records via this shared model service.
 * PRD Reference: Atlas Assets Overview — Database Schema & Removal.
 *
 * @extends ModelService<Asset>
 */
class AssetModelService extends ModelService
{
    protected string $model = Asset::class;

    public function __construct(
        private readonly AssetFileService $assetFileService,
        private readonly Repository $config,
    ) {}

    /**
     * Fluent alias for retrieving assets associated with a model.
     *
     * @param  array{label?: string|null, category?: string|null}  $filters
     * @return Builder<Asset>
     */
    public function forModel(Model $model, array $filters = [], ?int $limit = null): Builder
    {
        return $this->applyLimit(
            $this->buildModelQuery($model, $filters),
            $limit
        );
    }

    /**
     * Fluent alias for retrieving assets associated with a user.
     *
     * @param  array{label?: string|null, category?: string|null}  $filters
     * @return Builder<Asset>
     */
    public function forUser(int|string $userId, array $filters = [], ?int $limit = null): Builder
    {
        return $this->applyLimit(
            $this->buildUserQuery($userId, $filters),
            $limit
        );
    }

    /**
     * Build a model-scoped query with optional filters.
     *
     * @param  array{label?: string|null, category?: string|null}  $filters
     * @return Builder<Asset>
     */
    public function buildModelQuery(Model $model, array $filters = []): Builder
    {
        return $this->applyFilters(
            $this->query()
                ->where('model_type', $model->getMorphClass())
                ->where('model_id', $model->getKey()),
            $filters
        );
    }

    /**
     * Build a user-scoped query with optional filters.
     *
     * @param  array{label?: string|null, category?: string|null}  $filters
     * @return Builder<Asset>
     */
    public function buildUserQuery(int|string $userId, array $filters = []): Builder
    {
        return $this->applyFilters(
            $this->query()->where('user_id', $userId),
            $filters
        );
    }

    public function delete(Model $model, bool $force = false): bool
    {
        $asset = $this->ensureAsset($model);

        if ($force) {
            return $this->forceDeleteAsset($asset);
        }

        $deleted = parent::delete($asset, false);

        if ($deleted && $this->shouldDeleteFilesOnSoftDelete()) {
            $this->assetFileService->delete($asset->file_path);
        }

        return $deleted;
    }

    private function ensureAsset(Model $model): Asset
    {
        if (! $model instanceof Asset) {
            throw new InvalidArgumentException(sprintf(
                'AssetModelService can only operate on %s instances. Received [%s].',
                Asset::class,
                $model::class
            ));
        }

        return $model;
    }

    private function forceDeleteAsset(Asset $asset): bool
    {
        $this->assetFileService->delete($asset->file_path);

        return parent::delete($asset, true);
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
     * @param  Builder<Asset>  $builder
     * @return Builder<Asset>
     */
    private function applyLimit(Builder $builder, ?int $limit): Builder
    {
        if ($limit !== null) {
            $builder->limit($limit);
        }

        return $builder;
    }

    private function shouldDeleteFilesOnSoftDelete(): bool
    {
        return (bool) $this->config->get('atlas-assets.delete_files_on_soft_delete', false);
    }
}
