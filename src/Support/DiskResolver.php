<?php

declare(strict_types=1);

namespace Atlas\Assets\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;

/**
 * Class DiskResolver
 *
 * Centralizes resolution of the configured storage disk used by Atlas Assets.
 * PRD Reference: Atlas Assets Overview — Storage Configuration.
 */
class DiskResolver
{
    public function __construct(
        private readonly FilesystemFactory $filesystem,
        private readonly Repository $config,
    ) {}

    public function resolve(): Filesystem
    {
        $disk = $this->config->get('atlas-assets.disk');

        if (! is_string($disk) || trim($disk) === '') {
            $disk = (string) $this->config->get('filesystems.default', 'public');
        }

        return $this->filesystem->disk($disk);
    }
}
