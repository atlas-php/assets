<?php

declare(strict_types=1);

namespace Atlas\Assets\Tests\Feature;

use Atlas\Assets\Models\Asset;
use Atlas\Assets\Services\AssetModelService;
use Atlas\Assets\Services\AssetPurgeService;
use Atlas\Assets\Tests\TestCase;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
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

    public function test_find_returns_asset(): void
    {
        $asset = Asset::factory()->create(['file_path' => 'path.doc']);

        $service = $this->app->make(AssetModelService::class);

        self::assertSame($asset->id, $service->find($asset->id)?->id);
    }

    public function test_for_model_applies_filters(): void
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

        $service = $this->app->make(AssetModelService::class);

        $builder = $service->forModel($model, ['label' => 'hero']);
        self::assertInstanceOf(Builder::class, $builder);
        $results = $builder->get();

        self::assertCount(1, $results);
        self::assertTrue($results->first()->is($matching));
    }

    public function test_for_model_supports_limit(): void
    {
        $model = new RetrievalModel;
        $model->forceFill(['id' => 22]);

        $oldest = Asset::factory()->create([
            'model_type' => $model->getMorphClass(),
            'model_id' => 22,
        ]);

        Asset::factory()->count(3)->create([
            'model_type' => $model->getMorphClass(),
            'model_id' => 22,
        ]);

        $service = $this->app->make(AssetModelService::class);

        $builder = $service->forModel($model, [], 2);
        $results = $builder->get();

        self::assertCount(2, $results);
        self::assertFalse($results->contains($oldest));
    }

    public function test_for_user_filters_by_category(): void
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

        $service = $this->app->make(AssetModelService::class);

        $builder = $service->forUser($userId, ['category' => 'docs']);
        self::assertInstanceOf(Builder::class, $builder);
        $results = $builder->get();

        self::assertCount(1, $results);
        self::assertTrue($results->first()->is($matching));
    }

    public function test_for_model_builder_paginates(): void
    {
        $model = new RetrievalModel;
        $model->forceFill(['id' => 91]);

        $assets = Asset::factory()->count(3)->create([
            'model_type' => $model->getMorphClass(),
            'model_id' => 91,
        ]);

        $service = $this->app->make(AssetModelService::class);

        $paginator = $service->forModel($model)->paginate(2, ['*'], 'page', 1);

        self::assertInstanceOf(LengthAwarePaginator::class, $paginator);
        self::assertCount(2, $paginator->items());
        self::assertSame($assets->last()->id, $paginator->items()[0]->id);
        self::assertSame($assets->get(1)->id, $paginator->items()[1]->id);
    }

    public function test_for_user_builder_cursor_paginates(): void
    {
        $userId = 315;

        $assets = Asset::factory()->count(3)->create([
            'user_id' => $userId,
        ]);

        $service = $this->app->make(AssetModelService::class);

        $paginator = $service->forUser($userId)->cursorPaginate(2);

        self::assertInstanceOf(CursorPaginator::class, $paginator);
        self::assertCount(2, $paginator->items());
        self::assertSame($assets->last()->id, $paginator->items()[0]->id);
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

/**
 * @internal helper for retrieval tests
 */
class RetrievalModel extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'retrieval_models';

    /**
     * @var array<int, string>
     */
    protected $guarded = [];
}
