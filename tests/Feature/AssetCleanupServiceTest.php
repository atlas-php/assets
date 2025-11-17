<?php

declare(strict_types=1);

namespace Atlas\Assets\Tests\Feature;

use Atlas\Assets\Models\Asset;
use Atlas\Assets\Services\AssetCleanupService;
use Atlas\Assets\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

/**
 * Class AssetCleanupServiceTest
 *
 * Tests soft delete and purge operations for Atlas Assets.
 * PRD Reference: Atlas Assets Overview — Removal & Purging.
 */
final class AssetCleanupServiceTest extends TestCase
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

        $service = $this->app->make(AssetCleanupService::class);

        $service->delete($asset);

        Storage::disk('s3')->assertMissing('files/delete.doc');
        self::assertSoftDeleted($asset);
    }

    public function test_purge_removes_soft_deleted_assets_and_files(): void
    {
        $service = $this->app->make(AssetCleanupService::class);

        $asset = Asset::factory()->create([
            'file_path' => 'files/purge.doc',
        ]);
        Storage::disk('s3')->put('files/purge.doc', 'content');
        $service->delete($asset);

        $purged = $service->purge();

        self::assertEquals(1, $purged);
        self::assertDatabaseCount('atlas_assets', 0);
        Storage::disk('s3')->assertMissing('files/purge.doc');
    }
}
