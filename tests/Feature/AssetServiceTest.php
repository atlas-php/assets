<?php

declare(strict_types=1);

namespace Atlas\Assets\Tests\Feature;

use Atlas\Assets\Exceptions\DisallowedExtensionException;
use Atlas\Assets\Exceptions\UploadSizeLimitException;
use Atlas\Assets\Models\Asset;
use Atlas\Assets\Services\AssetService;
use Atlas\Assets\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;

/**
 * Class AssetServiceTest
 *
 * Exercises upload and update flows for Atlas Assets.
 * PRD Reference: Atlas Assets Overview — Uploading & Updating.
 */
final class AssetServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
        config()->set('atlas-assets.disk', 's3');
    }

    public function test_upload_uses_configured_disk(): void
    {
        Storage::fake('shared-disk');
        config()->set('atlas-assets.disk', 'shared-disk');

        $service = $this->app->make(AssetService::class);

        $asset = $service->upload(UploadedFile::fake()->create('Shared.txt', 1));

        Storage::disk('shared-disk')->assertExists($asset->file_path);
    }

    public function test_upload_stores_file_and_metadata(): void
    {
        config()->set('atlas-assets.path.pattern', '{file_name}.{extension}');

        $file = UploadedFile::fake()->create('Document.pdf', 120, 'application/pdf');

        $service = $this->app->make(AssetService::class);
        $asset = $service->upload($file, ['user_id' => 10, 'group_id' => 44, 'label' => 'contract', 'type' => TestAssetType::Hero]);

        Storage::disk('s3')->assertExists($asset->file_path);
        self::assertSame('document.pdf', $asset->file_path);
        self::assertSame('application/pdf', $asset->file_mime_type);
        self::assertSame('pdf', $asset->file_ext);
        self::assertSame('Document.pdf', $asset->name);
        self::assertSame('Document.pdf', $asset->original_file_name);
        self::assertSame(10, $asset->user_id);
        self::assertSame(44, $asset->group_id);
        self::assertSame(0, $asset->sort_order);
        self::assertSame('contract', $asset->label);
        self::assertSame(TestAssetType::Hero->value, $asset->type);
    }

    public function test_upload_assigns_incremental_sort_order_with_default_scope(): void
    {
        config()->set('atlas-assets.sort.scopes', ['model_type', 'model_id']);

        $model = new UploadableModel;
        $model->forceFill(['id' => 321]);

        $service = $this->app->make(AssetService::class);
        $first = $service->uploadForModel($model, UploadedFile::fake()->create('first.pdf', 5));
        $second = $service->uploadForModel($model, UploadedFile::fake()->create('second.pdf', 5));

        self::assertSame(0, $first->sort_order);
        self::assertSame(1, $second->sort_order);
    }

    public function test_upload_sort_order_scopes_can_target_group_id(): void
    {
        config()->set('atlas-assets.sort.scopes', ['group_id']);

        $service = $this->app->make(AssetService::class);

        $first = $service->upload(UploadedFile::fake()->create('first.pdf', 5), ['group_id' => 50]);
        $second = $service->upload(UploadedFile::fake()->create('second.pdf', 5), ['group_id' => 50]);
        $other = $service->upload(UploadedFile::fake()->create('other.pdf', 5), ['group_id' => 51]);

        self::assertSame(0, $first->sort_order);
        self::assertSame(1, $second->sort_order);
        self::assertSame(0, $other->sort_order);
    }

    public function test_upload_respects_manual_sort_order_attribute(): void
    {
        $service = $this->app->make(AssetService::class);

        $asset = $service->upload(
            UploadedFile::fake()->create('manual.pdf', 5),
            ['sort_order' => 20]
        );

        self::assertSame(20, $asset->sort_order);
    }

    public function test_default_sort_scope_includes_type_column(): void
    {
        $model = new UploadableModel;
        $model->forceFill(['id' => 8]);

        $service = $this->app->make(AssetService::class);

        $hero = $service->uploadForModel($model, UploadedFile::fake()->create('hero.pdf', 5), ['type' => TestAssetType::Hero]);
        $thumb = $service->uploadForModel($model, UploadedFile::fake()->create('thumb.pdf', 5), ['type' => TestAssetType::Thumbnail]);
        $heroTwo = $service->uploadForModel($model, UploadedFile::fake()->create('hero-2.pdf', 5), ['type' => TestAssetType::Hero]);

        self::assertSame(0, $hero->sort_order);
        self::assertSame(0, $thumb->sort_order);
        self::assertSame(1, $heroTwo->sort_order);
    }

    public function test_sort_order_disabled_when_config_scopes_null(): void
    {
        config()->set('atlas-assets.sort.scopes', null);

        $model = new UploadableModel;
        $model->forceFill(['id' => 22]);

        $service = $this->app->make(AssetService::class);

        $first = $service->uploadForModel($model, UploadedFile::fake()->create('first.pdf', 5));
        $second = $service->uploadForModel($model, UploadedFile::fake()->create('second.pdf', 5));

        self::assertSame(0, $first->sort_order);
        self::assertSame(0, $second->sort_order);
    }

    public function test_custom_sort_order_resolver_callback_is_respected(): void
    {
        config()->set('atlas-assets.sort.resolver', static function ($model, array $context): int {
            return (int) (($context['group_id'] ?? 0) * 10);
        });

        try {
            $service = $this->app->make(AssetService::class);

            $asset = $service->upload(
                UploadedFile::fake()->create('callback.pdf', 5),
                ['group_id' => 3]
            );

            self::assertSame(30, $asset->sort_order);
        } finally {
            config()->set('atlas-assets.sort.resolver', null);
        }
    }

    public function test_upload_without_model_collapses_path_placeholders(): void
    {
        config()->set('atlas-assets.path.pattern', '{model_type}/{model_id}/{file_name}.{extension}');

        $file = UploadedFile::fake()->create('Loose.txt', 5, 'text/plain');

        $service = $this->app->make(AssetService::class);
        $asset = $service->upload($file);

        Storage::disk('s3')->assertExists($asset->file_path);
        self::assertSame('loose.txt', $asset->file_path);
    }

    public function test_upload_defaults_name_to_original_and_trims_long_values(): void
    {
        $longName = str_repeat('a', 260).'.pdf';
        $file = UploadedFile::fake()->create($longName, 10, 'application/pdf');

        $service = $this->app->make(AssetService::class);
        $asset = $service->upload($file);

        self::assertSame(255, strlen($asset->original_file_name));
        self::assertSame($asset->original_file_name, $asset->name);
    }

    public function test_upload_throws_when_file_stream_cannot_open(): void
    {
        $service = $this->app->make(AssetService::class);

        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('getRealPath')->andReturn('');
        $file->shouldReceive('getClientMimeType')->andReturn('text/plain');
        $file->shouldReceive('getMimeType')->andReturn('text/plain');
        $file->shouldReceive('getSize')->andReturn(10);
        $file->shouldReceive('getClientOriginalExtension')->andReturn('txt');
        $file->shouldReceive('extension')->andReturn('txt');
        $file->shouldReceive('getClientOriginalName')->andReturn('failed.txt');
        $file->shouldReceive('getFilename')->andReturn('failed.txt');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to open uploaded file stream.');

        $service->upload($file);
    }

    public function test_upload_for_model_persists_model_metadata(): void
    {
        $model = new UploadableModel;
        $model->forceFill(['id' => 77]);

        $service = $this->app->make(AssetService::class);
        $asset = $service->uploadForModel(
            $model,
            UploadedFile::fake()->image('avatar.png', 200, 200),
            ['user_id' => 5, 'category' => 'profile']
        );

        Storage::disk('s3')->assertExists($asset->file_path);
        self::assertStringContainsString('uploadable_model/77/', $asset->file_path);
        self::assertSame($model->getMorphClass(), $asset->model_type);
        self::assertSame(77, $asset->model_id);
        self::assertSame('profile', $asset->category);
    }

    public function test_callback_resolver_path_is_used(): void
    {
        config()->set('atlas-assets.path.resolver', static fn () => 'custom/path/report.txt');

        $service = $this->app->make(AssetService::class);
        $asset = $service->upload(UploadedFile::fake()->create('ignored.txt', 5));

        Storage::disk('s3')->assertExists('custom/path/report.txt');
        self::assertSame('custom/path/report.txt', $asset->file_path);
    }

    public function test_update_allows_metadata_changes_without_file(): void
    {
        $asset = Asset::factory()->create([
            'group_id' => 9,
            'user_id' => 4,
            'name' => 'Old',
            'original_file_name' => 'old.pdf',
            'label' => null,
            'category' => null,
            'sort_order' => 0,
        ]);

        $service = $this->app->make(AssetService::class);
        $service->update($asset, [
            'name' => 'New Name',
            'label' => 'hero',
            'category' => 'images',
            'group_id' => 15,
            'user_id' => 5,
            'type' => TestAssetType::Header,
        ]);

        $asset->refresh();

        self::assertSame('New Name', $asset->name);
        self::assertSame('old.pdf', $asset->original_file_name);
        self::assertSame('hero', $asset->label);
        self::assertSame('images', $asset->category);
        self::assertSame(5, $asset->user_id);
        self::assertSame(15, $asset->group_id);
        self::assertSame(TestAssetType::Header->value, $asset->type);
    }

    public function test_type_accepts_numeric_string_values(): void
    {
        $service = $this->app->make(AssetService::class);

        $asset = $service->upload(
            UploadedFile::fake()->create('numeric.pdf', 5),
            ['type' => ' 42 ']
        );

        self::assertSame(42, $asset->type);
    }

    public function test_update_can_change_sort_order_manually(): void
    {
        $asset = Asset::factory()->create(['sort_order' => 0]);

        $service = $this->app->make(AssetService::class);
        $service->update($asset, ['sort_order' => 42]);

        $asset->refresh();

        self::assertSame(42, $asset->sort_order);
    }

    public function test_update_accepts_group_id_changes_independently(): void
    {
        $asset = Asset::factory()->create([
            'group_id' => null,
        ]);

        $service = $this->app->make(AssetService::class);
        $service->update($asset, ['group_id' => 99]);

        $asset->refresh();

        self::assertSame(99, $asset->group_id);
    }

    public function test_update_trims_overflowing_metadata_inputs(): void
    {
        $asset = Asset::factory()->create([
            'name' => 'Old',
            'original_file_name' => 'old.pdf',
        ]);

        $service = $this->app->make(AssetService::class);

        $longString = str_repeat('b', 300);
        $service->update($asset, [
            'name' => $longString,
            'label' => $longString,
            'category' => $longString,
        ]);

        $asset->refresh();

        self::assertSame(255, strlen($asset->name));
        self::assertSame(255, strlen($asset->label));
        self::assertSame(255, strlen($asset->category));
    }

    public function test_update_with_file_replaces_existing_file(): void
    {
        Storage::fake('s3');

        $asset = Asset::factory()->create([
            'file_path' => 'files/old.doc',
            'name' => 'Old.doc',
            'original_file_name' => 'Old.doc',
            'file_mime_type' => 'application/msword',
            'file_ext' => 'doc',
            'file_size' => 100,
        ]);

        Storage::disk('s3')->put('files/old.doc', 'old');

        $service = $this->app->make(AssetService::class);
        $service->update(
            $asset,
            ['label' => 'updated'],
            UploadedFile::fake()->create('New.pdf', 200, 'application/pdf')
        );

        $asset->refresh();

        Storage::disk('s3')->assertMissing('files/old.doc');
        Storage::disk('s3')->assertExists($asset->file_path);
        self::assertSame('application/pdf', $asset->file_mime_type);
        self::assertSame('pdf', $asset->file_ext);
        self::assertSame('New.pdf', $asset->original_file_name);
        self::assertSame('updated', $asset->label);
    }

    public function test_update_with_file_and_model_refreshes_association_and_path(): void
    {
        config()->set('atlas-assets.path.pattern', '{model_type}/{model_id}/{file_name}.{extension}');

        $initialModel = new UploadableModel;
        $initialModel->forceFill(['id' => 10]);

        $newModel = new UploadableModel;
        $newModel->forceFill(['id' => 25]);

        $service = $this->app->make(AssetService::class);
        $asset = $service->uploadForModel($initialModel, UploadedFile::fake()->create('old.txt', 10));

        $originalPath = $asset->file_path;

        $service->update(
            $asset,
            [],
            UploadedFile::fake()->create('updated.txt', 5),
            $newModel
        );

        $asset->refresh();

        Storage::disk('s3')->assertMissing($originalPath);
        Storage::disk('s3')->assertExists($asset->file_path);
        self::assertStringContainsString('uploadable_model/25/', $asset->file_path);
        self::assertSame($newModel->getMorphClass(), $asset->model_type);
        self::assertSame(25, $asset->model_id);
        self::assertSame('updated.txt', $asset->original_file_name);
    }

    public function test_replace_alias_updates_file_and_metadata(): void
    {
        Storage::fake('s3');

        $asset = Asset::factory()->create([
            'file_path' => 'files/old.doc',
            'name' => 'Old.doc',
            'original_file_name' => 'Old.doc',
        ]);

        Storage::disk('s3')->put('files/old.doc', 'old');

        $service = $this->app->make(AssetService::class);
        $service->replace(
            $asset,
            UploadedFile::fake()->create('Newest.doc', 50, 'application/msword'),
            ['name' => 'Newest.doc']
        );

        $asset->refresh();

        Storage::disk('s3')->assertExists($asset->file_path);
        self::assertSame('Newest.doc', $asset->name);
        self::assertSame('Newest.doc', $asset->original_file_name);
    }

    public function test_replace_updates_model_association(): void
    {
        config()->set('atlas-assets.path.pattern', '{model_type}/{model_id}/{file_name}.{extension}');

        $asset = Asset::factory()->create([
            'file_path' => 'files/old.doc',
            'name' => 'Old.doc',
            'original_file_name' => 'Old.doc',
        ]);

        Storage::disk('s3')->put('files/old.doc', 'old');

        $model = new UploadableModel;
        $model->forceFill(['id' => 99]);

        $service = $this->app->make(AssetService::class);
        $service->replace(
            $asset,
            UploadedFile::fake()->create('Newest.doc', 50, 'application/msword'),
            ['name' => 'Newest.doc'],
            $model
        );

        $asset->refresh();

        Storage::disk('s3')->assertMissing('files/old.doc');
        Storage::disk('s3')->assertExists($asset->file_path);
        self::assertStringContainsString('uploadable_model/99/', $asset->file_path);
        self::assertSame($model->getMorphClass(), $asset->model_type);
        self::assertSame(99, $asset->model_id);
    }

    public function test_update_with_file_overwrites_when_path_matches(): void
    {
        config()->set('atlas-assets.path.resolver', static function (): string {
            return 'files/shared.doc';
        });

        try {
            $asset = Asset::factory()->create([
                'file_path' => 'files/shared.doc',
                'name' => 'Shared.doc',
                'original_file_name' => 'Shared.doc',
            ]);

            Storage::disk('s3')->put('files/shared.doc', 'old');

            $service = $this->app->make(AssetService::class);
            $service->update($asset, [], UploadedFile::fake()->create('Shared.doc', 50));

            $asset->refresh();

            Storage::disk('s3')->assertExists('files/shared.doc');
            self::assertSame('files/shared.doc', $asset->file_path);
        } finally {
            config()->set('atlas-assets.path.resolver', null);
        }
    }

    public function test_upload_rejects_duplicate_file_paths(): void
    {
        config()->set('atlas-assets.path.resolver', static fn () => 'fixed/path.txt');

        $service = $this->app->make(AssetService::class);
        $service->upload(UploadedFile::fake()->create('first.txt', 1));

        $this->expectException(\InvalidArgumentException::class);

        $service->upload(UploadedFile::fake()->create('second.txt', 1));
    }

    public function test_update_rejects_duplicate_file_paths(): void
    {
        config()->set('atlas-assets.path.resolver', null);

        $existing = Asset::factory()->create([
            'file_path' => 'conflict/path.doc',
        ]);

        Storage::disk('s3')->put('conflict/path.doc', 'old');

        $asset = Asset::factory()->create();

        config()->set('atlas-assets.path.resolver', static fn () => 'conflict/path.doc');

        $service = $this->app->make(AssetService::class);

        $this->expectException(\InvalidArgumentException::class);

        $service->update($asset, [], UploadedFile::fake()->create('new.doc', 1));
    }

    public function test_upload_rejects_extension_not_in_whitelist(): void
    {
        config()->set('atlas-assets.uploads.allowed_extensions', ['pdf', 'docx']);

        $service = $this->app->make(AssetService::class);

        $this->expectException(DisallowedExtensionException::class);
        $this->expectExceptionMessage('not allowed by the configured whitelist');

        $service->upload(UploadedFile::fake()->image('photo.png'));
    }

    public function test_upload_rejects_blocklisted_extension(): void
    {
        config()->set('atlas-assets.uploads.blocked_extensions', ['png']);

        $service = $this->app->make(AssetService::class);

        $this->expectException(DisallowedExtensionException::class);
        $this->expectExceptionMessage('blocked for asset uploads');

        $service->upload(UploadedFile::fake()->image('photo.png'));
    }

    public function test_upload_supports_per_upload_allowed_extensions_override(): void
    {
        config()->set('atlas-assets.uploads.allowed_extensions', ['pdf']);

        $service = $this->app->make(AssetService::class);
        $asset = $service->upload(
            UploadedFile::fake()->image('photo.png'),
            ['allowed_extensions' => ['png']]
        );

        Storage::disk('s3')->assertExists($asset->file_path);
        self::assertSame('photo.png', $asset->original_file_name);
    }

    public function test_upload_override_respects_blocklist(): void
    {
        config()->set('atlas-assets.uploads.blocked_extensions', ['png']);

        $service = $this->app->make(AssetService::class);

        $this->expectException(DisallowedExtensionException::class);
        $this->expectExceptionMessage('blocked for asset uploads');

        $service->upload(
            UploadedFile::fake()->image('photo.png'),
            ['allowed_extensions' => ['png']]
        );
    }

    public function test_upload_rejects_files_that_exceed_configured_size_limit(): void
    {
        $service = $this->app->make(AssetService::class);

        $this->expectException(UploadSizeLimitException::class);
        $this->expectExceptionMessage('maximum allowed size');

        $service->upload(UploadedFile::fake()->create('large.pdf', 11 * 1024, 'application/pdf'));
    }

    public function test_upload_allows_increasing_size_limit_per_call(): void
    {
        $service = $this->app->make(AssetService::class);

        $asset = $service->upload(
            UploadedFile::fake()->create('large.pdf', 15 * 1024, 'application/pdf'),
            ['max_upload_size' => 20 * 1024 * 1024]
        );

        Storage::disk('s3')->assertExists($asset->file_path);
        self::assertSame('large.pdf', $asset->original_file_name);
    }

    public function test_upload_allows_disabling_size_limit_with_null_override(): void
    {
        config()->set('atlas-assets.uploads.max_file_size', 1024);

        $service = $this->app->make(AssetService::class);

        $asset = $service->upload(
            UploadedFile::fake()->create('huge.pdf', 20 * 1024, 'application/pdf'),
            ['max_upload_size' => null]
        );

        Storage::disk('s3')->assertExists($asset->file_path);
        self::assertSame('huge.pdf', $asset->original_file_name);
    }
}

enum TestAssetType: int
{
    case Hero = 1;
    case Thumbnail = 2;
    case Header = 3;
}

/**
 * @internal helper model for upload tests
 */
class UploadableModel extends Model
{
    protected $table = 'uploadable_models';

    /**
     * @var array<int, string>
     */
    protected $guarded = [];
}
