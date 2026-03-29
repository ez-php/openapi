<?php

declare(strict_types=1);

namespace EzPhp\OpenApi;

use EzPhp\Contracts\ConfigInterface;
use EzPhp\Contracts\ContainerInterface;
use EzPhp\Contracts\ServiceProvider;
use EzPhp\Routing\Router;

/**
 * Service provider for the ez-php/openapi module.
 *
 * Register in `provider/modules.php`:
 *
 *   $app->register(OpenApiServiceProvider::class);
 *
 * The provider binds `OpenApiGenerator` lazily and registers `GET /openapi.json`.
 *
 * Configuration keys (config/openapi.php or environment):
 *
 *   app.name     — used as the OpenAPI `info.title` (default: 'API')
 *   app.version  — used as the OpenAPI `info.version` (default: '1.0.0')
 *   openapi.endpoint — URI for the spec endpoint (default: '/openapi.json')
 */
final class OpenApiServiceProvider extends ServiceProvider
{
    /**
     * Bind `OpenApiGenerator` into the container.
     *
     * The generator is resolved lazily at request time so that all routes
     * (registered during boot) are already present in the router's registry
     * when `$router->toCache()` is called.
     */
    public function register(): void
    {
        $this->app->bind(OpenApiGenerator::class, function (ContainerInterface $app): OpenApiGenerator {
            $routes = [];
            $title = 'API';
            $version = '1.0.0';

            try {
                $router = $app->make(Router::class);
                $routes = $router->toCache();
            } catch (\Throwable) {
                // Router not available in minimal / CLI contexts.
            }

            try {
                $config = $app->make(ConfigInterface::class);
                $raw = $config->get('app.name', 'API');
                $title = is_string($raw) ? $raw : 'API';
                $raw = $config->get('app.version', '1.0.0');
                $version = is_string($raw) ? $raw : '1.0.0';
            } catch (\Throwable) {
                // Config not bound — use defaults.
            }

            return new OpenApiGenerator($routes, $title, $version);
        });
    }

    /**
     * Register the `GET /openapi.json` route.
     *
     * Wrapped in try/catch so the provider degrades gracefully in CLI and
     * test contexts where the Router is not bound.
     */
    public function boot(): void
    {
        try {
            $router = $this->app->make(Router::class);

            $endpoint = '/openapi.json';

            try {
                $config = $this->app->make(ConfigInterface::class);
                $raw = $config->get('openapi.endpoint', '/openapi.json');
                $endpoint = is_string($raw) ? $raw : '/openapi.json';
            } catch (\Throwable) {
                // Config not bound — use default endpoint.
            }

            $router->get($endpoint, [OpenApiController::class, '__invoke']);
        } catch (\Throwable) {
            // Router not available — route registration skipped.
        }
    }
}
