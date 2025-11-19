<?php

declare(strict_types=1);

namespace Atlas\Assets\Tests\Feature;

use Atlas\Assets\Models\Asset;
use Atlas\Assets\Services\AssetFileService;
use Atlas\Assets\Support\DiskResolver;
use Atlas\Assets\Tests\TestCase;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Mockery;
use RuntimeException;

/**
 * Class AssetFileServiceTest
 *
 * Validates download, existence, temporary URL, and streaming helpers that wrap disk access.
 * PRD Reference: Atlas Assets Overview — Retrieval APIs.
 */
final class AssetFileServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
        config()->set('atlas-assets.disk', 's3');
    }

    public function test_download_reads_file_contents(): void
    {
        $asset = Asset::factory()->create([
            'file_path' => 'files/doc.txt',
            'file_mime_type' => 'text/plain',
        ]);

        Storage::disk('s3')->put('files/doc.txt', 'content');

        $service = $this->app->make(AssetFileService::class);

        self::assertSame('content', $service->download($asset));
    }

    public function test_download_uses_configured_disk(): void
    {
        Storage::fake('shared-disk');
        config()->set('atlas-assets.disk', 'shared-disk');

        $asset = Asset::factory()->create([
            'file_path' => 'files/configured.doc',
            'file_mime_type' => 'application/msword',
        ]);

        Storage::disk('shared-disk')->put('files/configured.doc', 'configured-content');

        $service = $this->app->make(AssetFileService::class);

        self::assertSame('configured-content', $service->download($asset));
    }

    public function test_download_throws_when_file_missing(): void
    {
        $asset = Asset::factory()->create([
            'file_path' => 'files/missing.txt',
        ]);

        $service = $this->app->make(AssetFileService::class);

        $this->expectException(\RuntimeException::class);

        $service->download($asset);
    }

    public function test_exists_checks_disk(): void
    {
        $asset = Asset::factory()->create([
            'file_path' => 'files/poster.jpg',
        ]);

        Storage::disk('s3')->put('files/poster.jpg', 'binary');

        $service = $this->app->make(AssetFileService::class);

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

        $service = $this->app->make(AssetFileService::class);

        $url = $service->temporaryUrl($asset);

        self::assertSame('https://temp.example/files/report.pdf?signature=abc', $url);
    }

    public function test_temporary_url_falls_back_to_signed_stream(): void
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
            'file_mime_type' => 'application/zip',
        ]);

        $service = $this->app->make(AssetFileService::class);

        $url = $service->temporaryUrl($asset);

        $request = Request::create($url);

        self::assertTrue(URL::hasValidSignature($request));

        $response = $this->get($url);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/zip');
        $response->assertHeader('Content-Disposition', sprintf('inline; filename="%s"', $asset->name));
        $response->assertHeader('Content-Length', (string) $asset->file_size);
        $response->assertHeader('Cache-Control', 'max-age=300, private');
        self::assertSame('archive-content', $response->streamedContent());
    }

    public function test_store_uploaded_file_throws_when_stream_cannot_open(): void
    {
        $missingPath = sys_get_temp_dir().'/atlas-assets-missing-'.uniqid('', true);
        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('getRealPath')->andReturn($missingPath);

        $service = $this->app->make(AssetFileService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to open uploaded file stream.');

        set_error_handler(static fn (): bool => true);

        try {
            $service->storeUploadedFile($file, 'files/test.txt', 'public');
        } finally {
            restore_error_handler();
        }
    }

    public function test_delete_ignores_empty_paths(): void
    {
        $disk = Storage::disk('s3');
        $disk->put('files/delete-me.txt', 'content');

        $service = $this->app->make(AssetFileService::class);

        $service->delete('   ');

        $disk->assertExists('files/delete-me.txt');
    }

    public function test_stream_throws_when_disk_stream_is_unreadable(): void
    {
        $asset = Asset::factory()->create([
            'file_path' => 'files/broken.bin',
            'file_mime_type' => 'application/octet-stream',
            'file_size' => 10,
        ]);

        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('exists')
            ->once()
            ->with('files/broken.bin')
            ->andReturn(true);
        $disk->shouldReceive('readStream')
            ->once()
            ->with('files/broken.bin')
            ->andReturn(false);

        $diskResolver = Mockery::mock(DiskResolver::class);
        $diskResolver->shouldReceive('resolve')
            ->andReturn($disk);

        $service = new AssetFileService($diskResolver, $this->app->make(ConfigRepository::class));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('could not be read from disk');

        $service->stream($asset);
    }
}
