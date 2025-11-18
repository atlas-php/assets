<?php

declare(strict_types=1);

namespace Atlas\Assets\Facades;

use Atlas\Assets\Services\AssetManager;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Facade;

/**
 * Class Assets
 *
 * Provides a convenient static API for interacting with Atlas Assets via
 * Laravel's facade system.
 * PRD Reference: Atlas Assets Overview — Public APIs.
 *
 * @method static \Atlas\Assets\Models\Asset upload(\Illuminate\Http\UploadedFile $file, array $attributes = [])
 * @method static \Atlas\Assets\Models\Asset uploadForModel(\Illuminate\Database\Eloquent\Model $model, \Illuminate\Http\UploadedFile $file, array $attributes = [])
 * @method static \Atlas\Assets\Models\Asset update(\Atlas\Assets\Models\Asset $asset, array $attributes = [], ?\Illuminate\Http\UploadedFile $file = null, ?\Illuminate\Database\Eloquent\Model $model = null)
 * @method static \Atlas\Assets\Models\Asset replace(\Atlas\Assets\Models\Asset $asset, \Illuminate\Http\UploadedFile $file, array $attributes = [], ?\Illuminate\Database\Eloquent\Model $model = null)
 * @method static ?\Atlas\Assets\Models\Asset find(int|string $id)
 * @method static EloquentCollection listForModel(\Illuminate\Database\Eloquent\Model $model, array $filters = [], ?int $limit = null)
 * @method static EloquentCollection listForUser(int|string $userId, array $filters = [], ?int $limit = null)
 * @method static \Illuminate\Contracts\Pagination\LengthAwarePaginator paginateForModel(\Illuminate\Database\Eloquent\Model $model, array $filters = [], int $perPage = 15, string $pageName = 'page', ?int $page = null)
 * @method static \Illuminate\Contracts\Pagination\LengthAwarePaginator paginateForUser(int|string $userId, array $filters = [], int $perPage = 15, string $pageName = 'page', ?int $page = null)
 * @method static \Illuminate\Contracts\Pagination\CursorPaginator cursorPaginateForModel(\Illuminate\Database\Eloquent\Model $model, array $filters = [], int $perPage = 15, string $cursorName = 'cursor', ?\Illuminate\Pagination\Cursor $cursor = null)
 * @method static \Illuminate\Contracts\Pagination\CursorPaginator cursorPaginateForUser(int|string $userId, array $filters = [], int $perPage = 15, string $cursorName = 'cursor', ?\Illuminate\Pagination\Cursor $cursor = null)
 * @method static string download(\Atlas\Assets\Models\Asset $asset)
 * @method static bool exists(\Atlas\Assets\Models\Asset $asset)
 * @method static string temporaryUrl(\Atlas\Assets\Models\Asset $asset, int $minutes = 5)
 * @method static void delete(\Atlas\Assets\Models\Asset $asset)
 * @method static int purge()
 */
class Assets extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AssetManager::class;
    }
}
