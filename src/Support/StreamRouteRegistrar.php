<?php

declare(strict_types=1);

namespace Atlas\Assets\Support;

use Atlas\Assets\Http\Controllers\AssetStreamController;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Router;

/**
 * Class StreamRouteRegistrar
 *
 * Encapsulates registration of the signed asset stream fallback route so consumers
 * can customize or disable it via configuration without touching package routes.
 * PRD Reference: Atlas Assets Overview — Retrieval APIs.
 */
class StreamRouteRegistrar
{
    public function __construct(
        private readonly Router $router,
        private readonly Repository $config,
    ) {}

    public function register(): void
    {
        $config = $this->config->get('atlas-assets.routes.stream', []);

        if (! (bool) ($config['enabled'] ?? true)) {
            return;
        }

        $uri = $this->normalizeUri($config['uri'] ?? 'atlas-assets/stream/{asset}');
        $name = $this->normalizeName($config['name'] ?? 'atlas-assets.stream');
        $middleware = $this->normalizeMiddleware($config['middleware'] ?? ['signed', SubstituteBindings::class]);

        $this->router->middleware($middleware)->group(function () use ($uri, $name): void {
            $this->router->get($uri, AssetStreamController::class)->name($name);
        });
    }

    private function normalizeUri(mixed $uri): string
    {
        if ($uri instanceof \Stringable) {
            $uri = (string) $uri;
        }

        if (! is_string($uri)) {
            $uri = 'atlas-assets/stream/{asset}';
        }

        $uri = trim($uri);
        $uri = ltrim($uri, '/');

        return $uri === '' ? 'atlas-assets/stream/{asset}' : $uri;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeMiddleware(mixed $middleware): array
    {
        if ($middleware instanceof \Stringable || is_string($middleware)) {
            $middleware = [$middleware];
        }

        if (! is_array($middleware)) {
            $middleware = [];
        }

        $normalized = array_values(array_filter(array_map(static function ($layer): ?string {
            if ($layer instanceof \Stringable) {
                $layer = (string) $layer;
            }

            if (is_string($layer)) {
                $layer = trim($layer);

                return $layer === '' ? null : $layer;
            }

            return null;
        }, $middleware)));

        return $normalized === []
            ? ['signed', SubstituteBindings::class]
            : $normalized;
    }

    private function normalizeName(mixed $name): string
    {
        if ($name instanceof \Stringable) {
            $name = (string) $name;
        }

        if (! is_string($name)) {
            return 'atlas-assets.stream';
        }

        $name = trim($name);

        return $name === '' ? 'atlas-assets.stream' : $name;
    }
}
