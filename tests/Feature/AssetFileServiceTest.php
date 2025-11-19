<?php

declare(strict_types=1);

namespace Atlas\Assets\Tests\Feature;

use Atlas\Assets\Models\Asset;
use Atlas\Assets\Services\AssetFileService;
use Atlas\Assets\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

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
}
