<?php

declare(strict_types=1);

namespace Atlas\Assets\Tests\Feature;

use Atlas\Assets\Support\StreamRouteRegistrar;
use Atlas\Assets\Tests\TestCase;
use Illuminate\Config\Repository;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

/**
 * Class StreamRouteRegistrarTest
 *
 * Covers the route registration helper so configurable behavior remains deterministic.
 * PRD Reference: Atlas Assets Overview — Retrieval APIs.
 */
final class StreamRouteRegistrarTest extends TestCase
{
    public function test_registers_default_stream_route(): void
    {
        [$router, $registrar] = $this->makeRegistrar();

        $registrar->register();

        $route = $this->routeByName($router, 'atlas-assets.stream');

        self::assertNotNull($route);
        self::assertSame('atlas-assets/stream/{asset}', $route?->uri());
        self::assertSame(['signed', SubstituteBindings::class], $route?->gatherMiddleware());
    }

    public function test_register_can_be_disabled(): void
    {
        [$router, $registrar] = $this->makeRegistrar([
            'routes' => [
                'stream' => ['enabled' => false],
            ],
        ]);

        $registrar->register();

        self::assertNull($router->getRoutes()->getByName('atlas-assets.stream'));
    }

    public function test_register_applies_custom_configuration(): void
    {
        [$router, $registrar] = $this->makeRegistrar([
            'routes' => [
                'stream' => [
                    'uri' => '/media/assets/{asset}',
                    'name' => 'media.assets.stream',
                    'middleware' => ['  ', null],
                ],
            ],
        ]);

        $registrar->register();

        $route = $this->routeByName($router, 'media.assets.stream');

        self::assertNotNull($route);
        self::assertSame('media/assets/{asset}', $route?->uri());
        self::assertSame(['signed', SubstituteBindings::class], $route?->gatherMiddleware());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{Router, StreamRouteRegistrar}
     */
    private function makeRegistrar(array $overrides = []): array
    {
        $router = new Router($this->app['events'], $this->app);

        /** @var array<string, mixed> $baseConfig */
        $baseConfig = config('atlas-assets');

        $config = new Repository([
            'atlas-assets' => array_replace_recursive($baseConfig, $overrides),
        ]);

        return [$router, new StreamRouteRegistrar($router, $config)];
    }

    private function routeByName(Router $router, string $name): ?Route
    {
        $routes = $router->getRoutes();

        if (method_exists($routes, 'refreshNameLookups')) {
            $routes->refreshNameLookups();
        }

        return $routes->getByName($name);
    }
}
