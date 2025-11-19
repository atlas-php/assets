<?php

declare(strict_types=1);

namespace Atlas\Assets\Tests\Feature;

use Atlas\Assets\Support\StreamRouteRegistrar;
use Atlas\Assets\Tests\TestCase;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Router;
use Mockery;

/**
 * Class StreamRouteRegistrarTest
 *
 * Ensures the stream route registration honors configuration overrides.
 * PRD Reference: Atlas Assets Overview — Retrieval APIs.
 */
final class StreamRouteRegistrarTest extends TestCase
{
    public function test_register_uses_custom_configuration_values(): void
    {
        config()->set('atlas-assets.routes.stream', [
            'enabled' => true,
            'uri' => new class implements \Stringable
            {
                public function __toString(): string
                {
                    return ' /custom/stream/{asset} ';
                }
            },
            'name' => new class implements \Stringable
            {
                public function __toString(): string
                {
                    return ' custom.stream ';
                }
            },
            'middleware' => new class implements \Stringable
            {
                public function __toString(): string
                {
                    return ' signed ';
                }
            },
        ]);

        $router = Mockery::mock(Router::class);
        $group = Mockery::mock();
        $group->shouldReceive('group')
            ->once()
            ->andReturnUsing(function ($callback): void {
                $callback();
            });

        $router->shouldReceive('middleware')
            ->once()
            ->with(['signed'])
            ->andReturn($group);

        $router->shouldReceive('get')
            ->once()
            ->with('custom/stream/{asset}', \Atlas\Assets\Http\Controllers\AssetStreamController::class)
            ->andReturn(
                Mockery::mock()->shouldReceive('name')->once()->with('custom.stream')->getMock()
            );

        $registrar = new StreamRouteRegistrar($router, $this->app->make(Repository::class));
        $registrar->register();

        self::assertTrue(true);
    }

    public function test_register_falls_back_to_defaults_when_configuration_invalid(): void
    {
        config()->set('atlas-assets.routes.stream', [
            'enabled' => true,
            'uri' => 100,
            'name' => 200,
            'middleware' => 5,
        ]);

        $router = Mockery::mock(Router::class);
        $group = Mockery::mock();
        $group->shouldReceive('group')
            ->once()
            ->andReturnUsing(function ($callback): void {
                $callback();
            });

        $router->shouldReceive('middleware')
            ->once()
            ->with(['signed', SubstituteBindings::class])
            ->andReturn($group);

        $router->shouldReceive('get')
            ->once()
            ->with('atlas-assets/stream/{asset}', \Atlas\Assets\Http\Controllers\AssetStreamController::class)
            ->andReturn(
                Mockery::mock()->shouldReceive('name')->once()->with('atlas-assets.stream')->getMock()
            );

        $registrar = new StreamRouteRegistrar($router, $this->app->make(Repository::class));
        $registrar->register();

        self::assertTrue(true);
    }

    public function test_register_respects_disabled_configuration(): void
    {
        config()->set('atlas-assets.routes.stream', [
            'enabled' => false,
            'uri' => 'disabled/stream',
        ]);

        $router = Mockery::mock(Router::class);
        $router->shouldReceive('middleware')->never();
        $router->shouldReceive('get')->never();

        $registrar = new StreamRouteRegistrar($router, $this->app->make(Repository::class));
        $registrar->register();

        self::assertTrue(true);
    }
}
