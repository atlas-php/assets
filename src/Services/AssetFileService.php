<?php

declare(strict_types=1);

namespace Atlas\Assets\Services;

use Atlas\Assets\Models\Asset;
use Atlas\Assets\Support\DiskResolver;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Class AssetFileService
 *
 * Handles disk interactions (delete operations, downloads, streaming) for Atlas Assets so other services remain storage-agnostic.
 * PRD Reference: Atlas Assets Overview — Storage & Removal.
 */
class AssetFileService
{
    public function __construct(private readonly DiskResolver $diskResolver) {}

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
                // Fallback below.
            }
        }

        return $this->signedStreamUrl($asset, $minutes);
    }

    public function stream(Asset $asset): StreamedResponse
    {
        $disk = $this->disk();
        $this->guardFileExists($disk, $asset);

        return $this->streamResponse($disk, $asset);
    }

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

    private function supportsTemporaryUrls(Filesystem $disk): bool
    {
        return method_exists($disk, 'temporaryUrl');
    }

    private function signedStreamUrl(Asset $asset, int $minutes): string
    {
        return URL::temporarySignedRoute(
            'atlas-assets.stream',
            Carbon::now()->addMinutes($minutes),
            ['asset' => $asset->getKey()]
        );
    }

    private function streamResponse(Filesystem $disk, Asset $asset): StreamedResponse
    {
        /** @var resource|false $stream */
        $stream = $disk->readStream($asset->file_path);

        if ($stream === false) {
            throw new RuntimeException(sprintf('Asset file [%s] could not be read from disk.', $asset->file_path));
        }

        $headers = [
            'Content-Type' => $asset->file_mime_type ?: 'application/octet-stream',
            'Content-Length' => (string) $asset->file_size,
            'Content-Disposition' => sprintf('inline; filename="%s"', $asset->name),
            'Cache-Control' => 'max-age=300, private',
        ];

        return response()->stream(function () use ($stream): void {
            while (! feof($stream)) {
                echo fread($stream, 8192);
            }

            fclose($stream);
        }, 200, $headers);
    }

    private function guardFileExists(Filesystem $disk, Asset $asset): void
    {
        if (! $disk->exists($asset->file_path)) {
            throw new RuntimeException(sprintf('Asset file [%s] not found on disk.', $asset->file_path));
        }
    }
}
