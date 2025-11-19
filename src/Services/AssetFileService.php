<?php

declare(strict_types=1);

namespace Atlas\Assets\Services;

use Atlas\Assets\Support\DiskResolver;
use Illuminate\Contracts\Filesystem\Filesystem;

/**
 * Class AssetFileService
 *
 * Handles disk interactions (delete operations, disk resolution) for Atlas Assets so other services remain storage-agnostic.
 * PRD Reference: Atlas Assets Overview — Storage & Removal.
 */
class AssetFileService
{
    public function __construct(private readonly DiskResolver $diskResolver) {}

    public function delete(?string $path): void
    {
        if ($path === null || trim($path) === '') {
            return;
        }

        $disk = $this->disk();

        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }

    public function disk(): Filesystem
    {
        return $this->diskResolver->resolve();
    }
}
