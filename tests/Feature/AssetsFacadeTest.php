<?php

declare(strict_types=1);

namespace Atlas\Assets\Tests\Feature;

use Atlas\Assets\Facades\Assets;
use Atlas\Assets\Models\Asset;
use Atlas\Assets\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Class AssetsFacadeTest
 *
 * Ensures the Assets facade proxies to the underlying services.
 * PRD Reference: Atlas Assets Overview — Public APIs.
 */
final class AssetsFacadeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
        config()->set('atlas-assets.disk', 's3');
    }

    public function test_facade_proxies_upload_update_and_retrieval(): void
    {
        $asset = Assets::upload(
            UploadedFile::fake()->create('Doc.pdf', 5, 'application/pdf'),
            ['user_id' => 7, 'label' => 'initial']
        );

        self::assertInstanceOf(Asset::class, $asset);
        self::assertTrue(Assets::exists($asset));
        self::assertNotNull(Assets::find($asset->id));

        $query = Assets::listForUser(7);
        self::assertInstanceOf(Builder::class, $query);
        $collection = $query->get();
        self::assertCount(1, $collection);

        Assets::replace(
            $asset,
            UploadedFile::fake()->create('Doc-2.pdf', 10, 'application/pdf'),
            ['label' => 'updated']
        );

        $asset->refresh();
        self::assertSame('updated', $asset->label);

        $contents = Assets::download($asset);
        self::assertSame(Storage::disk('s3')->get($asset->file_path), $contents);
    }

    public function test_facade_handles_deletes_and_purge(): void
    {
        $asset = Assets::upload(UploadedFile::fake()->create('Delete.txt', 1), ['user_id' => 5]);

        Assets::delete($asset);
        self::assertSoftDeleted($asset);

        $force = Assets::upload(UploadedFile::fake()->create('Force.txt', 1));
        $path = $force->file_path;

        Assets::delete($force, true);
        Storage::disk('s3')->assertMissing($path);
        self::assertDatabaseMissing('atlas_assets', ['id' => $force->id]);

        $purged = Assets::purge();
        self::assertSame(1, $purged);
        self::assertDatabaseCount('atlas_assets', 0);
    }
}
