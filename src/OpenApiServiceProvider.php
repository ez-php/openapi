<?php

declare(strict_types=1);

namespace EzPhp\OpenApi;

use EzPhp\Contracts\ConfigInterface;
use EzPhp\Contracts\ContainerInterface;
use EzPhp\Contracts\RouterInterface;
use EzPhp\Contracts\ServiceProvider;
use EzPhp\JsonSchema\SchemaGenerator;

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
 *   openapi.endpoint   — URI for the spec endpoint (default: '/openapi.json')
 *   openapi.components — reusable component objects merged into the spec, e.g.
 *                        ['schemas' => ['User' => ['type' => 'object', ...]]].
 *                        Required for `#[ApiResponse(schemaClass: ...)]` refs to resolve.
 *   openapi.schema_classes — list<class-string> auto-converted into `components.schemas`
 *                        via ez-php/json-schema's SchemaGenerator, keyed by short class name.
 *                        Optional (soft dependency on ez-php/json-schema — require-dev only).
 *                        An entry in `openapi.components['schemas']` with the same key wins
 *                        over the generated one.
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
            $components = [];

            try {
                $router = $app->make(RouterInterface::class);
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
                $raw = $config->get('openapi.components', []);
                $components = is_array($raw) ? $raw : [];

                $rawSchemaClasses = $config->get('openapi.schema_classes', []);
                $schemaClasses = is_array($rawSchemaClasses) ? array_values($rawSchemaClasses) : [];

                if ($schemaClasses !== []) {
                    $components = $this->mergeGeneratedSchemas($components, $schemaClasses);
                }
            } catch (\Throwable) {
                // Config not bound — use defaults.
            }

            return new OpenApiGenerator($routes, $title, $version, $components);
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
            $router = $this->app->make(RouterInterface::class);

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

    /**
     * Merge SchemaGenerator-derived schemas for the given classes into
     * $components['schemas'], keyed by short class name. Any schema already
     * present under `openapi.components['schemas']` wins over the generated
     * one — the application's manual customisation is never overwritten.
     *
     * @param array<string, mixed> $components
     * @param list<mixed>          $schemaClasses
     *
     * @return array<string, mixed>
     */
    private function mergeGeneratedSchemas(array $components, array $schemaClasses): array
    {
        $generator = new SchemaGenerator();
        $generated = [];

        foreach ($schemaClasses as $class) {
            if (!is_string($class) || !class_exists($class)) {
                continue;
            }

            $lastSlash = strrpos($class, '\\');
            $shortName = $lastSlash === false ? $class : substr($class, $lastSlash + 1);
            $generated[$shortName] = $generator->generate($class);
        }

        /** @var array<string, mixed> $existingSchemas */
        $existingSchemas = is_array($components['schemas'] ?? null) ? $components['schemas'] : [];
        $components['schemas'] = [...$generated, ...$existingSchemas];

        return $components;
    }
}
