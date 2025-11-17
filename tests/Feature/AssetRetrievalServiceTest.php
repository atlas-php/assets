<?php

declare(strict_types=1);

namespace Atlas\Assets\Tests\Feature;

use Atlas\Assets\Models\Asset;
use Atlas\Assets\Services\AssetRetrievalService;
use Atlas\Assets\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Class AssetRetrievalServiceTest
 *
 * Validates retrieval operations for Atlas Assets.
 * PRD Reference: Atlas Assets Overview — Retrieval APIs.
 */
final class AssetRetrievalServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
        config()->set('atlas-assets.disk', 's3');
    }

    public function test_find_returns_asset(): void
    {
        $asset = Asset::factory()->create(['file_path' => 'path.doc']);

        $service = $this->app->make(AssetRetrievalService::class);

        self::assertSame($asset->id, $service->find($asset->id)?->id);
    }

    public function test_list_for_model_applies_filters(): void
    {
        $model = new RetrievalModel;
        $model->forceFill(['id' => 10]);

        $matching = Asset::factory()->create([
            'model_type' => $model->getMorphClass(),
            'model_id' => 10,
            'label' => 'hero',
        ]);

        Asset::factory()->create([
            'model_type' => $model->getMorphClass(),
            'model_id' => 10,
            'label' => 'thumb',
        ]);

        $service = $this->app->make(AssetRetrievalService::class);

        $results = $service->listForModel($model, ['label' => 'hero']);

        self::assertCount(1, $results);
        self::assertTrue($results->first()->is($matching));
    }

    public function test_list_for_user_filters_by_category(): void
    {
        $userId = 55;

        $matching = Asset::factory()->create([
            'user_id' => $userId,
            'category' => 'docs',
        ]);

        Asset::factory()->create([
            'user_id' => $userId,
            'category' => 'images',
        ]);

        $service = $this->app->make(AssetRetrievalService::class);

        $results = $service->listForUser($userId, ['category' => 'docs']);

        self::assertCount(1, $results);
        self::assertTrue($results->first()->is($matching));
    }

    public function test_download_reads_file_contents(): void
    {
        $asset = Asset::factory()->create([
            'file_path' => 'files/doc.txt',
            'file_type' => 'text/plain',
        ]);

        Storage::disk('s3')->put('files/doc.txt', 'content');

        $service = $this->app->make(AssetRetrievalService::class);

        self::assertSame('content', $service->download($asset));
    }

    public function test_download_throws_when_file_missing(): void
    {
        $asset = Asset::factory()->create([
            'file_path' => 'files/missing.txt',
        ]);

        $service = $this->app->make(AssetRetrievalService::class);

        $this->expectException(RuntimeException::class);

        $service->download($asset);
    }

    public function test_exists_checks_disk(): void
    {
        $asset = Asset::factory()->create([
            'file_path' => 'files/poster.jpg',
        ]);

        Storage::disk('s3')->put('files/poster.jpg', 'binary');

        $service = $this->app->make(AssetRetrievalService::class);

        self::assertTrue($service->exists($asset));
    }

    public function test_temporary_url_uses_disk_support_when_available(): void
    {
        Storage::fake('temp-disk');
        config()->set('atlas-assets.disk', 'temp-disk');

        $disk = Storage::disk('temp-disk');
        $disk->put('files/report.pdf', 'pdf');
        $disk->buildTemporaryUrlsUsing(fn ($path) => "https://temp.example/{$path}?signature=abc");

        $asset = Asset::factory()->create([
            'file_path' => 'files/report.pdf',
        ]);

        $service = $this->app->make(AssetRetrievalService::class);

        $url = $service->temporaryUrl($asset);

        self::assertSame('https://temp.example/files/report.pdf?signature=abc', $url);
    }

    public function test_temporary_url_falls_back_to_inline_download(): void
    {
        Storage::fake('inline');
        config()->set('atlas-assets.disk', 'inline');

        $disk = Storage::disk('inline');
        $disk->put('files/archive.zip', 'archive-content');
        $disk->buildTemporaryUrlsUsing(function (): void {
            throw new \RuntimeException('not supported');
        });

        $asset = Asset::factory()->create([
            'file_path' => 'files/archive.zip',
            'file_type' => 'application/zip',
        ]);

        $service = $this->app->make(AssetRetrievalService::class);

        $url = $service->temporaryUrl($asset);

        self::assertStringStartsWith('data:application/zip;base64,', $url);
    }
}

/**
 * @internal helper for retrieval tests
 */
class RetrievalModel extends Model
{
    protected $table = 'retrieval_models';

    /**
     * @var array<int, string>
     */
    protected $guarded = [];
}
