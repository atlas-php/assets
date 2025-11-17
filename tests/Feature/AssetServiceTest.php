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

    public function test_upload_stores_file_and_metadata(): void
    {
        $file = UploadedFile::fake()->create('Document.pdf', 120, 'application/pdf');

        $service = $this->app->make(AssetService::class);
        $asset = $service->upload($file, ['user_id' => 10, 'label' => 'contract']);

        Storage::disk('s3')->assertExists($asset->file_path);
        self::assertSame('application/pdf', $asset->file_type);
        self::assertSame('Document.pdf', $asset->name);
        self::assertSame('Document.pdf', $asset->original_file_name);
        self::assertSame(10, $asset->user_id);
        self::assertSame('contract', $asset->label);
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
