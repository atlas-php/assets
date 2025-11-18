<?php

declare(strict_types=1);

namespace Atlas\Assets\Services;

use Atlas\Assets\Models\Asset;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;

/**
 * Class AssetCleanupService
 *
 * Handles soft deletion, optional file removal, and purging of assets.
 * PRD Reference: Atlas Assets Overview — Removal & Purging.
 */
class AssetCleanupService
{
    public function __construct(
        private readonly FilesystemFactory $filesystem,
        private readonly Repository $config,
    ) {}

    public function delete(Asset $asset): void
    {
        $asset->delete();

        $this->deleteFile($asset->file_path);
    }

    private const PURGE_CHUNK_SIZE = 100;

    public function purge(int $chunkSize = self::PURGE_CHUNK_SIZE): int
    {
        $purgedCount = 0;

        $chunkSize = max(1, $chunkSize);

        Asset::onlyTrashed()
            ->orderBy('id')
            ->chunkById($chunkSize, function (iterable $assets) use (&$purgedCount): void {
                foreach ($assets as $asset) {
                    $this->deleteFile($asset->file_path);

                    $asset->forceDelete();
                    $purgedCount++;
                }
            });

        return $purgedCount;
    }

    private function deleteFile(string $path): void
    {
        if ($path === '') {
            return;
        }

        $disk = $this->disk();

        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }

    private function disk(): Filesystem
    {
        $disk = $this->config->get('atlas-assets.disk', 's3');

        return $this->filesystem->disk($disk);
    }
}
