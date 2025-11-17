<?php

declare(strict_types=1);

namespace Atlas\Assets\Tests\Feature;

use Atlas\Assets\Services\AssetUploadService;
use Atlas\Assets\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Class AssetUploadServiceTest
 *
 * Validates Atlas Assets upload workflows for generic and model-specific files.
 * PRD Reference: Atlas Assets Overview — Uploading.
 */
final class AssetUploadServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_stores_file_and_creates_asset_record(): void
    {
        Storage::fake('s3');
        config()->set('atlas-assets.disk', 's3');
        config()->set('atlas-assets.path.pattern', 'library/{uuid}.{extension}');

        $file = UploadedFile::fake()->create('Document.pdf', 120, 'application/pdf');

        $service = $this->app->make(AssetUploadService::class);
        $asset = $service->upload($file, ['user_id' => 10, 'label' => 'contract']);

        Storage::disk('s3')->assertExists($asset->file_path);
        self::assertSame('application/pdf', $asset->file_type);
        self::assertSame('Document.pdf', $asset->name);
        self::assertSame(10, $asset->user_id);
        self::assertSame('contract', $asset->label);
        self::assertNull($asset->model_type);
        self::assertNotEmpty($asset->file_path);
    }

    public function test_upload_for_model_persists_model_metadata(): void
    {
        Storage::fake('assets-disk');
        config()->set('atlas-assets.disk', 'assets-disk');

        $file = UploadedFile::fake()->image('avatar.png', 200, 200);
        $model = new UploadableModel;
        $model->forceFill([
            'id' => 77,
        ]);

        $service = $this->app->make(AssetUploadService::class);
        $asset = $service->uploadForModel($model, $file, [
            'user_id' => 5,
            'category' => 'profile',
        ]);

        Storage::disk('assets-disk')->assertExists($asset->file_path);
        self::assertSame($model->getMorphClass(), $asset->model_type);
        self::assertSame(77, $asset->model_id);
        self::assertSame('profile', $asset->category);
    }

    public function test_callback_resolver_path_is_used(): void
    {
        Storage::fake('s3');
        config()->set('atlas-assets.disk', 's3');
        config()->set('atlas-assets.path.resolver', static fn () => 'custom/path/report.txt');

        $file = UploadedFile::fake()->create('ignored.txt', 5);

        $service = $this->app->make(AssetUploadService::class);
        $asset = $service->upload($file);

        Storage::disk('s3')->assertExists('custom/path/report.txt');
        self::assertSame('custom/path/report.txt', $asset->file_path);
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
