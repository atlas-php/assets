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

    public function purge(): int
    {
        $count = 0;

        Asset::onlyTrashed()->each(function (Asset $asset) use (&$count): void {
            $this->deleteFile($asset->file_path);

            $asset->forceDelete();
            $count++;
        });

        return $count;
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
