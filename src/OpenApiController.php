<?php

declare(strict_types=1);

namespace EzPhp\OpenApi;

use EzPhp\Http\Request;
use EzPhp\Http\Response;

/**
 * Invokable HTTP controller that serves the OpenAPI 3.0.0 specification as JSON.
 *
 * Registered by `OpenApiServiceProvider::boot()` at `GET /openapi.json`.
 *
 * The spec is generated fresh on every request so that dynamic route changes
 * (e.g. during testing) are always reflected. In production the generator's
 * route list is set once at container-bind time and does not change.
 */
final class OpenApiController
{
    /**
     * @param OpenApiGenerator $generator Bound by the container via OpenApiServiceProvider.
     */
    public function __construct(private readonly OpenApiGenerator $generator)
    {
    }

    /**
     * Render the OpenAPI spec and return a JSON response.
     *
     * Always returns HTTP 200. The `Content-Type` header is set to
     * `application/json`. The JSON is pretty-printed with unescaped slashes
     * for readability in browsers and Swagger UI.
     *
     * @param Request $request Incoming HTTP request (unused but required by the router signature).
     *
     * @return Response
     */
    public function __invoke(Request $request): Response
    {
        $spec = $this->generator->generate();
        $json = json_encode($spec->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return (new Response($json !== false ? $json : '{}', 200))
            ->withHeader('Content-Type', 'application/json');
    }
}
