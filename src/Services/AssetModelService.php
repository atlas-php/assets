<?php

declare(strict_types=1);

namespace Atlas\Assets\Services;

use Atlas\Assets\Models\Asset;
use Atlas\Assets\Support\PathResolver;
use Atlas\Assets\Support\SortOrderResolver;
use Atlas\Core\Services\ModelService;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
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
        private readonly PathResolver $pathResolver,
        private readonly UploadGuardService $uploadGuard,
        private readonly SortOrderResolver $sortOrderResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upload(UploadedFile $file, array $attributes = []): Asset
    {
        return $this->persist($file, null, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function uploadForModel(Model $model, UploadedFile $file, array $attributes = []): Asset
    {
        return $this->persist($file, $model, $attributes);
    }

    /**
     * Update asset metadata or replace the stored file.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateAsset(Asset $asset, array $attributes = [], ?UploadedFile $file = null, ?Model $model = null): Asset
    {
        $updates = [];

        if ($model !== null) {
            $updates['model_type'] = $model->getMorphClass();
            $updates['model_id'] = $model->getKey();
        }

        if ($file !== null) {
            /** @var array{path: string, mime: string, extension: string, size: int, original_name: string} $fileData */
            $fileData = $this->storeFile(
                $file,
                $model,
                [
                    'user_id' => $attributes['user_id'] ?? $asset->user_id,
                    'group_id' => $attributes['group_id'] ?? $asset->group_id,
                ] + $attributes
            );

            $oldPath = $asset->file_path;

            $updates['file_path'] = $fileData['path'];
            $updates['file_mime_type'] = $fileData['mime'];
            $updates['file_ext'] = $fileData['extension'];
            $updates['file_size'] = $fileData['size'];
            $updates['name'] = $attributes['name'] ?? $fileData['original_name'];
            $updates['original_file_name'] = $fileData['original_name'];

            if ($fileData['path'] !== $oldPath) {
                $this->assetFileService->delete($oldPath);
            }
        }

        foreach (['name', 'label', 'category'] as $field) {
            if (array_key_exists($field, $attributes)) {
                $updates[$field] = $this->sanitizeString($attributes[$field]);
            }
        }

        if (array_key_exists('type', $attributes)) {
            $updates['type'] = $this->sanitizeType($attributes['type']);
        }

        if (array_key_exists('user_id', $attributes)) {
            $updates['user_id'] = $attributes['user_id'];
        }

        if (array_key_exists('group_id', $attributes)) {
            $updates['group_id'] = $attributes['group_id'];
        }

        if (array_key_exists('sort_order', $attributes)) {
            if ($attributes['sort_order'] === null) {
                $updates['sort_order'] = 0;
            } else {
                $sortOrder = $this->normalizeSortOrder($attributes['sort_order']);

                if ($sortOrder !== null) {
                    $updates['sort_order'] = $sortOrder;
                }
            }
        }

        if ($updates === []) {
            return $asset;
        }

        $this->ensureUniquePath($updates['file_path'] ?? null, $asset->id);

        $asset->update($updates);

        return $asset->refresh();
    }

    /**
     * Replace the existing storage object while allowing metadata overrides.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function replaceAsset(Asset $asset, UploadedFile $file, array $attributes = [], ?Model $model = null): Asset
    {
        return $this->updateAsset($asset, $attributes, $file, $model);
    }

    /**
     * Fluent alias for retrieving assets associated with a model.
     *
     * @param  array{label?: string|null, category?: string|null}  $filters
     * @return Builder<Asset>
     */
    public function forModel(Model $model, array $filters = [], ?int $limit = null): Builder
    {
        return $this->applyLimit(
            $this->buildModelQuery($model, $filters),
            $limit
        );
    }

    /**
     * Fluent alias for retrieving assets associated with a user.
     *
     * @param  array{label?: string|null, category?: string|null}  $filters
     * @return Builder<Asset>
     */
    public function forUser(int|string $userId, array $filters = [], ?int $limit = null): Builder
    {
        return $this->applyLimit(
            $this->buildUserQuery($userId, $filters),
            $limit
        );
    }

    /**
     * Build a model-scoped query with optional filters.
     *
     * @param  array{label?: string|null, category?: string|null}  $filters
     * @return Builder<Asset>
     */
    public function buildModelQuery(Model $model, array $filters = []): Builder
    {
        return $this->applyFilters(
            $this->query()
                ->where('model_type', $model->getMorphClass())
                ->where('model_id', $model->getKey()),
            $filters
        );
    }

    /**
     * Build a user-scoped query with optional filters.
     *
     * @param  array{label?: string|null, category?: string|null}  $filters
     * @return Builder<Asset>
     */
    public function buildUserQuery(int|string $userId, array $filters = []): Builder
    {
        return $this->applyFilters(
            $this->query()->where('user_id', $userId),
            $filters
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function persist(UploadedFile $file, ?Model $model, array $attributes): Asset
    {
        /** @var array{path: string, mime: string, extension: string, size: int, original_name: string} $fileData */
        $fileData = $this->storeFile($file, $model, $attributes);

        $this->ensureUniquePath($fileData['path']);

        $label = $this->sanitizeString($attributes['label'] ?? null);
        $category = $this->sanitizeString($attributes['category'] ?? null);
        $type = $this->sanitizeType($attributes['type'] ?? null);
        $name = $this->sanitizeString($attributes['name'] ?? $fileData['original_name']) ?? $fileData['original_name'];

        $sortOrder = $this->determineInitialSortOrder($model, $attributes, $label, $category, $type);

        $data = [
            'user_id' => $attributes['user_id'] ?? null,
            'group_id' => $attributes['group_id'] ?? null,
            'model_type' => $model?->getMorphClass(),
            'model_id' => $model?->getKey(),
            'file_mime_type' => $fileData['mime'],
            'file_ext' => $fileData['extension'],
            'file_path' => $fileData['path'],
            'file_size' => $fileData['size'],
            'name' => $name,
            'original_file_name' => $fileData['original_name'],
            'label' => $label,
            'category' => $category,
            'type' => $type,
        ];

        if ($sortOrder !== null) {
            $data['sort_order'] = $sortOrder;
        }

        $asset = $this->create($data);

        return $asset->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{path: string, mime: string, extension: string, size: int, original_name: string}
     */
    private function storeFile(UploadedFile $file, ?Model $model, array $attributes): array
    {
        $this->uploadGuard->validate($file, $attributes);

        $visibility = (string) $this->config->get('atlas-assets.visibility', 'public');
        $path = $this->pathResolver->resolve($file, $model, $attributes);

        $this->assetFileService->storeUploadedFile($file, $path, $visibility);

        $mime = $file->getClientMimeType() ?: $file->getMimeType() ?: 'application/octet-stream';
        $extension = $this->resolveExtension($file);
        $size = $file->getSize();

        if (! is_int($size)) {
            $size = 0;
        }

        $originalName = $this->sanitizeString($file->getClientOriginalName()) ?? $this->sanitizeString($file->getFilename()) ?? 'file';

        return [
            'path' => $path,
            'mime' => $mime,
            'extension' => $extension,
            'size' => $size,
            'original_name' => $originalName,
        ];
    }

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

    private function shouldDeleteFilesOnSoftDelete(): bool
    {
        return (bool) $this->config->get('atlas-assets.delete_files_on_soft_delete', false);
    }

    private function resolveExtension(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension() ?: $file->extension() ?: 'bin';

        return Str::lower(ltrim((string) $extension, '.'));
    }

    private function sanitizeString(mixed $value, int $limit = 255): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        if ($string === '') {
            return null;
        }

        return Str::limit($string, $limit, '');
    }

    private function sanitizeType(mixed $value): ?int
    {
        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        }

        if ($value === null) {
            return null;
        }

        if ($value instanceof \Stringable) {
            $value = (string) $value;
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return null;
            }

            if (! is_numeric($value)) {
                throw new InvalidArgumentException('The atlas-assets type must resolve to an integer between 0 and 255 or a backed enum value.');
            }

            $value = (int) $value;
        }

        if (is_float($value)) {
            $value = (int) $value;
        }

        if (! is_int($value)) {
            throw new InvalidArgumentException('The atlas-assets type must resolve to an integer between 0 and 255 or a backed enum value.');
        }

        if ($value < 0 || $value > 255) {
            throw new InvalidArgumentException('The atlas-assets type must resolve to an integer between 0 and 255 or a backed enum value.');
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function determineInitialSortOrder(?Model $model, array $attributes, ?string $label, ?string $category, ?int $type): ?int
    {
        $manualSort = $this->normalizeSortOrder($attributes['sort_order'] ?? null);

        if ($manualSort !== null) {
            return $manualSort;
        }

        $context = $this->buildSortContext($model, $attributes, $label, $category, $type);

        return $this->sortOrderResolver->next($model, $context);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function buildSortContext(?Model $model, array $attributes, ?string $label, ?string $category, ?int $type): array
    {
        return [
            'model_type' => $model?->getMorphClass(),
            'model_id' => $model?->getKey(),
            'group_id' => $attributes['group_id'] ?? null,
            'user_id' => $attributes['user_id'] ?? null,
            'category' => $category,
            'label' => $label,
            'type' => $type,
        ];
    }

    private function normalizeSortOrder(mixed $value): ?int
    {
        if ($value instanceof \Stringable) {
            $value = (string) $value;
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return null;
            }
        }

        if ($value === null || ! is_numeric($value)) {
            return null;
        }

        $order = (int) $value;

        return $order < 0 ? 0 : $order;
    }

    private function ensureUniquePath(?string $path, ?int $ignoreId = null): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $exists = Asset::withTrashed()
            ->when($ignoreId, static fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('file_path', $path)
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException("An asset already uses the path [{$path}].");
        }
    }
}
