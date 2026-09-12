<?php

declare(strict_types=1);

namespace EzPhp\OpenApi;

/**
 * Immutable value object representing a complete OpenAPI 3.0.0 specification.
 *
 * Built by `OpenApiGenerator::generate()`. Call `toArray()` to obtain
 * the spec as a plain PHP array suitable for `json_encode()`.
 */
final class OpenApiSpec
{
    /**
     * @param string                                             $title      API title shown in the spec's `info` block.
     * @param string                                             $version    API version string shown in the spec's `info` block.
     * @param array<string, array<string, array<string, mixed>>> $paths      Spec paths map: path → method → operation.
     * @param array<string, mixed>                               $components Reusable component objects (`schemas`, `securitySchemes`, …),
     *                                                                       supplied by the application. Omitted from the output when empty.
     */
    public function __construct(
        private readonly string $title,
        private readonly string $version,
        private readonly array $paths,
        private readonly array $components = [],
    ) {
    }

    /**
     * Serialize the spec to a plain PHP array following the OpenAPI 3.0.0 schema.
     *
     * The `components` key is emitted only when components were supplied. An empty
     * `components` object is valid OpenAPI but carries no information, so it is
     * omitted to keep the generated document minimal.
     *
     * @return array{openapi: string, info: array{title: string, version: string}, paths: array<string, array<string, array<string, mixed>>>, components?: array<string, mixed>}
     */
    public function toArray(): array
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => $this->title,
                'version' => $this->version,
            ],
            'paths' => $this->paths,
        ];

        if ($this->components !== []) {
            $spec['components'] = $this->components;
        }

        return $spec;
    }
}
