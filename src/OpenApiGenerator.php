<?php

declare(strict_types=1);

namespace EzPhp\OpenApi;

use EzPhp\OpenApi\Attributes\ApiOperation;
use EzPhp\OpenApi\Attributes\ApiParam;
use EzPhp\OpenApi\Attributes\ApiResponse;
use ReflectionMethod;

/**
 * Generates an OpenAPI 3.0.0 specification from registered routes and PHP attributes.
 *
 * The generator accepts the serialisable route list produced by `Router::toCache()`.
 * Only routes whose handler is a `[ControllerClass::class, 'method']` tuple are
 * inspected for `#[ApiOperation]`, `#[ApiResponse]`, and `#[ApiParam]` attributes.
 *
 * Routes without any attributes still appear in the spec with a bare operation
 * entry (empty summary, default 200 response).
 *
 * Path parameters (e.g. `{id}`) are auto-detected from the route pattern and
 * added as `in: 'path', required: true` parameters if not already declared via
 * `#[ApiParam]`.
 *
 * Usage in a service provider:
 *
 *   $routes = $app->make(Router::class)->toCache();
 *   $generator = new OpenApiGenerator($routes, 'My API', '1.0.0');
 *   $spec = $generator->generate();
 *   echo json_encode($spec->toArray());
 */
final class OpenApiGenerator
{
    /**
     * @param list<array{method: string, path: string, name: string|null, handler: array{0: class-string, 1: string}, middleware: array<int, class-string>, constraints: array<string, string>, csrfExempt: bool}> $routes
     * @param string $title   API title placed in the OpenAPI `info.title` field.
     * @param string $version API version placed in the OpenAPI `info.version` field.
     */
    public function __construct(
        private readonly array $routes,
        private readonly string $title = 'API',
        private readonly string $version = '1.0.0',
    ) {
    }

    /**
     * Generate the OpenAPI spec from the registered routes and their attributes.
     *
     * @return OpenApiSpec
     */
    public function generate(): OpenApiSpec
    {
        $paths = [];

        foreach ($this->routes as $route) {
            $path = $route['path'];
            $method = strtolower($route['method']);
            $handler = $route['handler'];

            $operation = $this->buildOperation($path, $handler);
            $paths[$path][$method] = $operation;
        }

        return new OpenApiSpec($this->title, $this->version, $paths);
    }

    /**
     * Build a single operation array for the given path and handler.
     *
     * Reads `#[ApiOperation]`, `#[ApiResponse]`, and `#[ApiParam]` attributes
     * from the controller method. Auto-detects path parameters not covered by
     * explicit `#[ApiParam]` declarations.
     *
     * @param string                            $path    Route URI pattern (e.g. '/users/{id}').
     * @param array{0: class-string, 1: string} $handler Controller class and method name tuple.
     *
     * @return array<string, mixed>
     */
    private function buildOperation(string $path, array $handler): array
    {
        try {
            $reflection = new ReflectionMethod($handler[0], $handler[1]);
        } catch (\ReflectionException) {
            return ['responses' => ['200' => ['description' => 'OK']]];
        }

        $operation = [];

        // ApiOperation — summary, description, tags
        $operationAttrs = $reflection->getAttributes(ApiOperation::class);

        if ($operationAttrs !== []) {
            $apiOp = $operationAttrs[0]->newInstance();

            if ($apiOp->summary !== '') {
                $operation['summary'] = $apiOp->summary;
            }

            if ($apiOp->description !== '') {
                $operation['description'] = $apiOp->description;
            }

            if ($apiOp->tags !== []) {
                $operation['tags'] = $apiOp->tags;
            }
        }

        // ApiParam — explicit parameters
        $parameters = [];
        $declaredPathParams = [];

        foreach ($reflection->getAttributes(ApiParam::class) as $attr) {
            $param = $attr->newInstance();
            $isPathParam = $param->in === 'path';

            $paramEntry = [
                'name' => $param->name,
                'in' => $param->in,
                'required' => $isPathParam || $param->required,
                'schema' => ['type' => $param->type],
            ];

            if ($param->description !== '') {
                $paramEntry['description'] = $param->description;
            }

            $parameters[] = $paramEntry;

            if ($isPathParam) {
                $declaredPathParams[] = $param->name;
            }
        }

        // Auto-detect path parameters not already declared via #[ApiParam]
        preg_match_all('/\{([^}]+)\}/', $path, $matches);

        foreach ($matches[1] as $paramName) {
            if (!in_array($paramName, $declaredPathParams, true)) {
                $parameters[] = [
                    'name' => $paramName,
                    'in' => 'path',
                    'required' => true,
                    'schema' => ['type' => 'string'],
                ];
            }
        }

        if ($parameters !== []) {
            $operation['parameters'] = $parameters;
        }

        // ApiResponse — declared responses
        $responses = [];

        foreach ($reflection->getAttributes(ApiResponse::class) as $attr) {
            $resp = $attr->newInstance();
            $respEntry = ['description' => $resp->description !== '' ? $resp->description : 'OK'];

            if ($resp->schemaClass !== null) {
                $shortName = basename(str_replace('\\', '/', $resp->schemaClass));
                $respEntry['content'] = [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/' . $shortName],
                    ],
                ];
            }

            $responses[(string) $resp->status] = $respEntry;
        }

        if ($responses === []) {
            $responses['200'] = ['description' => 'OK'];
        }

        $operation['responses'] = $responses;

        return $operation;
    }
}
