<?php

declare(strict_types=1);

namespace Atlas\Assets\Tests\Feature;

use Atlas\Assets\Models\Asset;
use Atlas\Assets\Services\AssetModelService;
use Atlas\Assets\Services\AssetPurgeService;
use Atlas\Assets\Tests\TestCase;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

/**
 * Class AssetModelServiceTest
 *
 * Tests the shared AssetModelService deletion flows and the dedicated AssetPurgeService to ensure consumers receive consistent behavior.
 * PRD Reference: Atlas Assets Overview — Removal & Purging.
 */
final class AssetModelServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
        config()->set('atlas-assets.disk', 's3');
    }

    public function test_delete_removes_file_and_soft_deletes(): void
    {
        $asset = Asset::factory()->create([
            'file_path' => 'files/delete.doc',
        ]);

        Storage::disk('s3')->put('files/delete.doc', 'content');

        config()->set('atlas-assets.delete_files_on_soft_delete', true);

        $service = $this->app->make(AssetModelService::class);

        $service->delete($asset);

        Storage::disk('s3')->assertMissing('files/delete.doc');
        self::assertSoftDeleted($asset);
    }

    public function test_delete_preserves_file_when_config_disabled(): void
    {
        $asset = Asset::factory()->create([
            'file_path' => 'files/keep.doc',
        ]);

        Storage::disk('s3')->put('files/keep.doc', 'content');

        config()->set('atlas-assets.delete_files_on_soft_delete', false);

        $service = $this->app->make(AssetModelService::class);

        $service->delete($asset);

        Storage::disk('s3')->assertExists('files/keep.doc');
        self::assertSoftDeleted($asset);
    }

    public function test_delete_respects_configured_disk(): void
    {
        Storage::fake('shared-disk');
        config()->set('atlas-assets.disk', 'shared-disk');
        config()->set('atlas-assets.delete_files_on_soft_delete', true);

        $asset = Asset::factory()->create([
            'file_path' => 'files/delete-from-shared.doc',
        ]);

        Storage::disk('shared-disk')->put('files/delete-from-shared.doc', 'content');

        $service = $this->app->make(AssetModelService::class);

        $service->delete($asset);

        Storage::disk('shared-disk')->assertMissing('files/delete-from-shared.doc');
    }

    public function test_force_delete_removes_file_and_record_immediately(): void
    {
        $asset = Asset::factory()->create([
            'file_path' => 'files/force.doc',
        ]);

        Storage::disk('s3')->put('files/force.doc', 'content');
        config()->set('atlas-assets.delete_files_on_soft_delete', false);

        $service = $this->app->make(AssetModelService::class);
        $service->delete($asset, true);

        Storage::disk('s3')->assertMissing('files/force.doc');
        self::assertDatabaseMissing('atlas_assets', ['id' => $asset->id]);
    }

    public function test_asset_model_service_rejects_non_asset_instance(): void
    {
        $service = $this->app->make(AssetModelService::class);

        $this->expectException(\InvalidArgumentException::class);

        $service->delete(new class extends EloquentModel {});
    }

    public function test_asset_purge_service_removes_soft_deleted_assets(): void
    {
        $purgeService = $this->app->make(AssetPurgeService::class);
        $records = $this->app->make(AssetModelService::class);

        $assets = Asset::factory()->count(3)->sequence(
            ['file_path' => 'files/purge-1.doc'],
            ['file_path' => 'files/purge-2.doc'],
            ['file_path' => 'files/purge-3.doc'],
        )->create();

        foreach ($assets as $asset) {
            Storage::disk('s3')->put($asset->file_path, 'content');
            $records->delete($asset);
        }

        $purged = $purgeService->purge(chunkSize: 1);

        self::assertSame(3, $purged);
        self::assertDatabaseCount('atlas_assets', 0);

        foreach ($assets as $asset) {
            Storage::disk('s3')->assertMissing($asset->file_path);
        }
    }
}
