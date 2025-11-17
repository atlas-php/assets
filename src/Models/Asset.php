<?php

declare(strict_types=1);

namespace Atlas\Assets\Models;

use Atlas\Assets\Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;

/**
 * Class Asset
 *
 * Represents an asset record stored via the atlas-assets configuration and
 * provides relationships to users and polymorphic models.
 * PRD Reference: Atlas Assets Overview — Database Schema.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $model_type
 * @property int|null $model_id
 * @property string $file_type
 * @property string $file_path
 * @property int $file_size
 * @property string $name
 * @property string $original_file_name
 * @property string|null $label
 * @property string|null $category
 */
class Asset extends AtlasModel
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'file_size' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<AuthenticatableUser, self>
     */
    public function user(): BelongsTo
    {
        $providerModel = config('auth.providers.users.model');
        $fallback = config('auth.model', AuthenticatableUser::class);

        /** @var class-string<AuthenticatableUser> $modelClass */
        $modelClass = $providerModel ?? $fallback;

        /** @var BelongsTo<AuthenticatableUser, self> $relation */
        $relation = $this->belongsTo($modelClass);

        return $relation;
    }

    /**
     * @return MorphTo<EloquentModel, self>
     */
    public function model(): MorphTo
    {
        /** @var MorphTo<EloquentModel, self> $relation */
        $relation = $this->morphTo();

        return $relation;
    }

    public function hasLabel(): bool
    {
        return filled($this->label);
    }

    public function hasCategory(): bool
    {
        return filled($this->category);
    }

    public function hasOwner(): bool
    {
        return $this->user_id !== null;
    }

    protected function tableNameConfigKey(): string
    {
        return 'atlas-assets.tables.assets';
    }

    protected function defaultTableName(): string
    {
        return 'atlas_assets';
    }

    protected static function newFactory(): AssetFactory
    {
        return AssetFactory::new();
    }
}
