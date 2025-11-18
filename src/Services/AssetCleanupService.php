<?php

declare(strict_types=1);

namespace Atlas\Assets\Services;

use Atlas\Assets\Models\Asset;
use Atlas\Assets\Support\DiskResolver;
use Illuminate\Contracts\Config\Repository;
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
        private readonly DiskResolver $diskResolver,
        private readonly Repository $config,
    ) {}

    public function delete(Asset $asset): void
    {
        $asset->delete();

        if (! $this->shouldDeleteFilesOnSoftDelete()) {
            return;
        }

        $this->deleteFile($asset->file_path);
    }

    private const PURGE_CHUNK_SIZE = 100;

    public function purge(int $chunkSize = self::PURGE_CHUNK_SIZE): int
    {
        $purgedCount = 0;
        $chunkSize = max(1, $chunkSize);
        $disk = $this->disk();

        Asset::onlyTrashed()
            ->orderBy('id')
            ->chunkById($chunkSize, function (iterable $assets) use (&$purgedCount, $disk): void {
                foreach ($assets as $asset) {
                    $this->deleteFile($asset->file_path, $disk);

                    $asset->forceDelete();
                    $purgedCount++;
                }
            });

        return $purgedCount;
    }

    private function deleteFile(string $path, ?Filesystem $disk = null): void
    {
        if ($path === '') {
            return;
        }

        $filesystem = $disk ?? $this->disk();

        if ($filesystem->exists($path)) {
            $filesystem->delete($path);
        }
    }

    private function shouldDeleteFilesOnSoftDelete(): bool
    {
        return (bool) $this->config->get('atlas-assets.delete_files_on_soft_delete', false);
    }

    private function disk(): Filesystem
    {
        return $this->diskResolver->resolve();
    }
}
