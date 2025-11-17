<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('atlas-assets.tables.assets', 'atlas_assets');

        $this->schema()->create($tableName, function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->string('file_type');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size');
            $table->string('name');
            $table->string('original_file_name');
            $table->string('label')->nullable();
            $table->string('category')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['model_type', 'model_id']);
            $table->index('category');
            $table->index('label');
            $table->unique('file_path');
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists(config('atlas-assets.tables.assets', 'atlas_assets'));
    }

    protected function schema(): Builder
    {
        $connection = config('atlas-assets.database.connection') ?: config('database.default');

        return Schema::connection($connection);
    }
};
