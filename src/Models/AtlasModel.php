<?php

declare(strict_types=1);

namespace Atlas\Assets\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class AtlasModel
 *
 * Provides a base model that automatically resolves table names and database
 * connections from the Atlas Assets configuration.
 * PRD Reference: Atlas Assets Overview — Database Schema.
 */
abstract class AtlasModel extends Model
{
    /**
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->setTable(config($this->tableNameConfigKey(), $this->defaultTableName()));

        $connection = config('atlas-assets.database.connection');

        if ($connection) {
            $this->setConnection($connection);
        }

        parent::__construct($attributes);
    }

    abstract protected function tableNameConfigKey(): string;

    abstract protected function defaultTableName(): string;
}
