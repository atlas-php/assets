<?php

declare(strict_types=1);

namespace Atlas\Assets\Tests\Feature;

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
        $asset = $service->upload($file, ['user_id' => 10, 'label' => 'contract']);

        Storage::disk('s3')->assertExists($asset->file_path);
        self::assertSame('document.pdf', $asset->file_path);
        self::assertSame('application/pdf', $asset->file_type);
        self::assertSame('Document.pdf', $asset->name);
        self::assertSame('Document.pdf', $asset->original_file_name);
        self::assertSame(10, $asset->user_id);
        self::assertSame('contract', $asset->label);
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
            'name' => 'Old',
            'original_file_name' => 'old.pdf',
            'label' => null,
            'category' => null,
        ]);

        $service = $this->app->make(AssetService::class);
        $service->update($asset, [
            'name' => 'New Name',
            'label' => 'hero',
            'category' => 'images',
        ]);

        $asset->refresh();

        self::assertSame('New Name', $asset->name);
        self::assertSame('old.pdf', $asset->original_file_name);
        self::assertSame('hero', $asset->label);
        self::assertSame('images', $asset->category);
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
            'file_type' => 'application/msword',
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
        self::assertSame('application/pdf', $asset->file_type);
        self::assertSame('New.pdf', $asset->original_file_name);
        self::assertSame('updated', $asset->label);
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
