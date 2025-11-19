<?php

declare(strict_types=1);

namespace Atlas\Assets\Tests\Feature;

use Atlas\Assets\AssetManager;
use Atlas\Assets\Models\Asset;
use Atlas\Assets\Services\AssetFileService;
use Atlas\Assets\Services\AssetModelService;
use Atlas\Assets\Services\AssetPurgeService;
use Atlas\Assets\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Mockery;

/**
 * Class AssetManagerTest
 *
 * Verifies the facade root delegates to the underlying services.
 * PRD Reference: Atlas Assets Overview — Public APIs.
 */
final class AssetManagerTest extends TestCase
{
    public function test_upload_for_model_delegates_to_model_service(): void
    {
        $modelService = Mockery::mock(AssetModelService::class);
        $fileService = Mockery::mock(AssetFileService::class);
        $purgeService = Mockery::mock(AssetPurgeService::class);

        $model = new class extends Model
        {
            protected $table = 'uploadables';
        };

        $file = UploadedFile::fake()->create('demo.pdf', 5, 'application/pdf');
        $asset = Asset::factory()->make();

        $modelService->shouldReceive('uploadForModel')
            ->once()
            ->with($model, $file, ['label' => 'hero'])
            ->andReturn($asset);

        $manager = new AssetManager($modelService, $fileService, $purgeService);

        self::assertSame($asset, $manager->uploadForModel($model, $file, ['label' => 'hero']));
    }

    public function test_update_delegates_to_model_service(): void
    {
        $modelService = Mockery::mock(AssetModelService::class);
        $fileService = Mockery::mock(AssetFileService::class);
        $purgeService = Mockery::mock(AssetPurgeService::class);

        $asset = Asset::factory()->make();
        $file = UploadedFile::fake()->create('demo.pdf', 5, 'application/pdf');
        $model = new class extends Model
        {
            protected $table = 'uploadables';
        };

        $modelService->shouldReceive('updateAsset')
            ->once()
            ->with($asset, ['label' => 'hero'], $file, $model)
            ->andReturn($asset);

        $manager = new AssetManager($modelService, $fileService, $purgeService);

        self::assertSame($asset, $manager->update($asset, ['label' => 'hero'], $file, $model));
    }

    public function test_for_model_returns_builder_from_service(): void
    {
        $modelService = Mockery::mock(AssetModelService::class);
        $fileService = Mockery::mock(AssetFileService::class);
        $purgeService = Mockery::mock(AssetPurgeService::class);

        $builder = Mockery::mock(Builder::class);
        $model = new class extends Model
        {
            protected $table = 'uploadables';
        };

        $modelService->shouldReceive('forModel')
            ->once()
            ->with($model, ['label' => 'hero'], 5)
            ->andReturn($builder);

        $manager = new AssetManager($modelService, $fileService, $purgeService);

        self::assertSame($builder, $manager->forModel($model, ['label' => 'hero'], 5));
    }

    public function test_temporary_url_delegates_to_file_service(): void
    {
        $modelService = Mockery::mock(AssetModelService::class);
        $fileService = Mockery::mock(AssetFileService::class);
        $purgeService = Mockery::mock(AssetPurgeService::class);

        $asset = Asset::factory()->make();

        $fileService->shouldReceive('temporaryUrl')
            ->once()
            ->with($asset, 10)
            ->andReturn('https://example.test/temp-url');

        $manager = new AssetManager($modelService, $fileService, $purgeService);

        self::assertSame('https://example.test/temp-url', $manager->temporaryUrl($asset, 10));
    }
}
