<?php

declare(strict_types=1);

namespace Atlas\Assets\Services;

use Atlas\Assets\Models\Asset;
use Atlas\Assets\Support\DiskResolver;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Class AssetRetrievalService
 *
 * Provides querying helpers and download/temporary URL utilities.
 * PRD Reference: Atlas Assets Overview — Retrieval APIs.
 */
class AssetRetrievalService
{
    public function __construct(
        private readonly DiskResolver $diskResolver
    ) {}

    public function find(int|string $id): ?Asset
    {
        return Asset::query()->find($id);
    }

    /**
     * Fluent alias for retrieving assets associated with a model.
     *
     * @param  array{label?: string|null, category?: string|null}  $filters
     * @return Builder<Asset>
     */
    public function forModel(Model $model, array $filters = [], ?int $limit = null): Builder
    {
        return $this->listForModel($model, $filters, $limit);
    }

    /**
     * Fluent alias for retrieving assets associated with a user.
     *
     * @param  array{label?: string|null, category?: string|null}  $filters
     * @return Builder<Asset>
     */
    public function forUser(int|string $userId, array $filters = [], ?int $limit = null): Builder
    {
        return $this->listForUser($userId, $filters, $limit);
    }

    /**
     * Build a query constrained to a specific model.
     *
     * @param  array{label?: string|null, category?: string|null}  $filters
     * @return Builder<Asset>
     */
    public function listForModel(Model $model, array $filters = [], ?int $limit = null): Builder
    {
        return $this->applyLimit(
            $this->buildModelQuery($model, $filters),
            $limit
        );
    }

    /**
     * Build a query constrained to a user identifier.
     *
     * @param  array{label?: string|null, category?: string|null}  $filters
     * @return Builder<Asset>
     */
    public function listForUser(int|string $userId, array $filters = [], ?int $limit = null): Builder
    {
        return $this->applyLimit(
            $this->buildUserQuery($userId, $filters),
            $limit
        );
    }

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
                // Fall back to signed streaming response below.
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
     * @param  array{label?: string|null, category?: string|null}  $filters
     * @return Builder<Asset>
     */
    public function buildModelQuery(Model $model, array $filters = []): Builder
    {
        return $this->applyFilters(
            Asset::query()
                ->where('model_type', $model->getMorphClass())
                ->where('model_id', $model->getKey()),
            $filters
        );
    }

    /**
     * @param  array{label?: string|null, category?: string|null}  $filters
     * @return Builder<Asset>
     */
    public function buildUserQuery(int|string $userId, array $filters = []): Builder
    {
        return $this->applyFilters(
            Asset::query()->where('user_id', $userId),
            $filters
        );
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

    private function disk(): Filesystem
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
            [
                'asset' => $asset->getKey(),
            ]
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
            'Content-Type' => $asset->file_type ?: 'application/octet-stream',
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
