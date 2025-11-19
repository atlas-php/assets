<?php

declare(strict_types=1);

namespace Atlas\Assets\Services;

use Atlas\Assets\Models\Asset;
use Atlas\Core\Services\ModelService;
use Illuminate\Contracts\Config\Repository;
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

    private function shouldDeleteFilesOnSoftDelete(): bool
    {
        return (bool) $this->config->get('atlas-assets.delete_files_on_soft_delete', false);
    }
}
