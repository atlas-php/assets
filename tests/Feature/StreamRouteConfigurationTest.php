<?php

declare(strict_types=1);

namespace Atlas\Assets\Tests\Feature;

use Atlas\Assets\Models\Asset;
use Atlas\Assets\Services\AssetFileService;
use Atlas\Assets\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Orchestra\Testbench\Attributes\DefineEnvironment;

/**
 * Class StreamRouteConfigurationTest
 *
 * Verifies the configurable stream route can be customized or disabled to keep the package application-agnostic.
 * PRD Reference: Atlas Assets Overview — Retrieval APIs.
 */
final class StreamRouteConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_stream_route_registered_with_default_configuration(): void
    {
        self::assertTrue(Route::has('atlas-assets.stream'));

        $route = Route::getRoutes()->getByName('atlas-assets.stream');

        self::assertNotNull($route);
        self::assertSame('atlas-assets/stream/{asset}', $route?->uri());
    }

    #[DefineEnvironment('disableStreamRoute')]
    public function test_stream_route_can_be_disabled(): void
    {
        self::assertFalse(Route::has('atlas-assets.stream'));
    }

    #[DefineEnvironment('customStreamRoute')]
    public function test_stream_route_configuration_applies_to_signed_urls(): void
    {
        self::assertTrue(Route::has('media.assets.stream'));

        $route = Route::getRoutes()->getByName('media.assets.stream');

        self::assertNotNull($route);
        self::assertSame('media/assets/{asset}', $route?->uri());

        Storage::fake('inline');
        config()->set('atlas-assets.disk', 'inline');

        $disk = Storage::disk('inline');
        $disk->put('files/custom.bin', 'contents');
        $disk->buildTemporaryUrlsUsing(function (): void {
            throw new \RuntimeException('no temporary URLs');
        });

        $asset = Asset::factory()->create([
            'file_path' => 'files/custom.bin',
            'file_mime_type' => 'application/octet-stream',
        ]);

        $service = $this->app->make(AssetFileService::class);

        $url = $service->temporaryUrl($asset, 10);
        self::assertStringContainsString('/media/assets/'.$asset->getKey(), $url);

        $request = Request::create($url);
        self::assertTrue(URL::hasValidSignature($request));

        $this->get($url)->assertOk();
    }

    /**
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     */
    protected function disableStreamRoute($app): void
    {
        $app['config']->set('atlas-assets.routes.stream.enabled', false);
    }

    /**
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     */
    protected function customStreamRoute($app): void
    {
        $app['config']->set('atlas-assets.routes.stream.uri', 'media/assets/{asset}');
        $app['config']->set('atlas-assets.routes.stream.name', 'media.assets.stream');
    }
}
