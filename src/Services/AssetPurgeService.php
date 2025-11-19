<?php

declare(strict_types=1);

namespace Atlas\Assets\Services;

/**
 * Class AssetPurgeService
 *
 * Provides a dedicated purge entry point backed by AssetModelService so the legacy facade/API surface
 * stays intact while delete flows route through AssetModelService directly.
 * PRD Reference: Atlas Assets Overview — Removal & Purging.
 */
class AssetPurgeService
{
    public const DEFAULT_CHUNK_SIZE = 100;

    public function __construct(
        private readonly AssetModelService $assetModelService,
    ) {}

    public function purge(int $chunkSize = self::DEFAULT_CHUNK_SIZE): int
    {
        $purged = 0;
        $chunkSize = max(1, $chunkSize);

        $this->assetModelService->query()
            ->onlyTrashed()
            ->orderBy('id')
            ->chunkById($chunkSize, function (iterable $assets) use (&$purged): void {
                foreach ($assets as $asset) {
                    $this->assetModelService->delete($asset, true);
                    $purged++;
                }
            });

        return $purged;
    }
}
