<?php

declare(strict_types=1);

namespace Atlas\Assets\Services;

use Atlas\Assets\Models\Asset;
use Atlas\Core\Services\ModelService;

/**
 * Class AssetRecordService
 *
 * Provides CRUD helpers for the Asset model so higher-level services can focus on file operations.
 * PRD Reference: Atlas Assets Overview — Database Schema.
 *
 * @extends ModelService<Asset>
 */
class AssetModelService extends ModelService
{
    protected string $model = Asset::class;
}
