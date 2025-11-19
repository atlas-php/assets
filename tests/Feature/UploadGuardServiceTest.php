<?php

declare(strict_types=1);

namespace Atlas\Assets\Tests\Feature;

use Atlas\Assets\Exceptions\DisallowedExtensionException;
use Atlas\Assets\Exceptions\UploadSizeLimitException;
use Atlas\Assets\Services\UploadGuardService;
use Atlas\Assets\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Mockery;

/**
 * Class UploadGuardServiceTest
 *
 * Exercises extension normalization and upload size limit handling.
 * PRD Reference: Atlas Assets Overview — Uploading & Updating.
 */
final class UploadGuardServiceTest extends TestCase
{
    public function test_validate_allows_null_extension_configuration(): void
    {
        config()->set('atlas-assets.uploads.allowed_extensions', null);
        config()->set('atlas-assets.uploads.blocked_extensions', null);

        $this->guard()->validate(UploadedFile::fake()->create('photo.png', 10, 'image/png'));

        self::assertTrue(true);
    }

    public function test_validate_supports_string_allowed_extensions_configuration(): void
    {
        config()->set('atlas-assets.uploads.allowed_extensions', ' jpg ');
        config()->set('atlas-assets.uploads.blocked_extensions', []);

        $this->guard()->validate(UploadedFile::fake()->image('cover.JPG'));

        self::assertTrue(true);
    }

    public function test_validate_ignores_non_array_allowed_configuration(): void
    {
        config()->set('atlas-assets.uploads.allowed_extensions', 300);

        $this->guard()->validate(UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream'));

        self::assertTrue(true);
    }

    public function test_validate_processes_stringable_blocklist_entries(): void
    {
        config()->set('atlas-assets.uploads.blocked_extensions', [
            new class implements \Stringable
            {
                public function __toString(): string
                {
                    return ' PNG ';
                }
            },
            '   ',
        ]);

        $this->expectException(DisallowedExtensionException::class);
        $this->expectExceptionMessage('blocked for asset uploads');

        $this->guard()->validate(UploadedFile::fake()->image('avatar.png'));
    }

    public function test_validate_uses_override_error_message_when_extension_missing(): void
    {
        config()->set('atlas-assets.uploads.allowed_extensions', []);

        $this->expectException(DisallowedExtensionException::class);
        $this->expectExceptionMessage('provided allowed extensions list');

        $this->guard()->validate(
            UploadedFile::fake()->image('preview.jpg'),
            ['allowed_extensions' => ['png']]
        );
    }

    public function test_validate_does_not_enforce_size_when_file_size_is_not_integer(): void
    {
        config()->set('atlas-assets.uploads.max_file_size', 1);

        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('getClientOriginalExtension')->andReturn('txt');
        $file->shouldReceive('extension')->andReturn('txt');
        $file->shouldReceive('getSize')->andReturn(false);

        $this->guard()->validate($file);

        self::assertTrue(true);
    }

    public function test_validate_applies_stringable_max_size_override(): void
    {
        config()->set('atlas-assets.uploads.max_file_size', 10 * 1024);

        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('getClientOriginalExtension')->andReturn('txt');
        $file->shouldReceive('extension')->andReturn('txt');
        $file->shouldReceive('getSize')->andReturn(4096);

        $this->expectException(UploadSizeLimitException::class);

        $this->guard()->validate($file, [
            'max_upload_size' => new class implements \Stringable
            {
                public function __toString(): string
                {
                    return ' 2048 ';
                }
            },
        ]);
    }

    public function test_validate_treats_blank_max_override_as_no_limit(): void
    {
        config()->set('atlas-assets.uploads.max_file_size', 512);

        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('getClientOriginalExtension')->andReturn('txt');
        $file->shouldReceive('extension')->andReturn('txt');
        $file->shouldReceive('getSize')->andReturn(2048);

        $this->guard()->validate($file, ['max_upload_size' => '   ']);

        self::assertTrue(true);
    }

    public function test_validate_ignores_non_numeric_size_configuration(): void
    {
        config()->set('atlas-assets.uploads.max_file_size', 'invalid');

        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('getClientOriginalExtension')->andReturn('txt');
        $file->shouldReceive('extension')->andReturn('txt');
        $file->shouldReceive('getSize')->andReturn(10_000);

        $this->guard()->validate($file);

        self::assertTrue(true);
    }

    public function test_validate_respects_null_max_override(): void
    {
        config()->set('atlas-assets.uploads.max_file_size', 512);

        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('getClientOriginalExtension')->andReturn('txt');
        $file->shouldReceive('extension')->andReturn('txt');
        $file->shouldReceive('getSize')->andReturn(10_000);

        $this->guard()->validate($file, ['max_upload_size' => null]);

        self::assertTrue(true);
    }

    public function test_validate_honors_stringable_configured_max_size(): void
    {
        config()->set('atlas-assets.uploads.max_file_size', new class implements \Stringable
        {
            public function __toString(): string
            {
                return ' 1024 ';
            }
        });

        $file = UploadedFile::fake()->create('large.pdf', 5 * 1024, 'application/pdf');

        $this->expectException(UploadSizeLimitException::class);

        $this->guard()->validate($file);
    }

    private function guard(): UploadGuardService
    {
        return $this->app->make(UploadGuardService::class);
    }
}
