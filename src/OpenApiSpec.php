<?php

declare(strict_types=1);

namespace EzPhp\OpenApi;

/**
 * Immutable value object representing a complete OpenAPI specification — 3.0.0
 * (default) or 3.1.0. For 3.0, component schemas are rewritten from JSON Schema
 * 2020-12 (what ez-php/json-schema emits, and what 3.1 uses) into the 3.0 dialect.
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
     * @param string                                             $specVersion '3.0' or '3.1'.
     *
     * @throws \InvalidArgumentException For any other spec version.
     */
    public function __construct(
        private readonly string $title,
        private readonly string $version,
        private readonly array $paths,
        private readonly array $components = [],
        private readonly string $specVersion = '3.0',
    ) {
        if (!in_array($specVersion, ['3.0', '3.1'], true)) {
            throw new \InvalidArgumentException("OpenAPI version must be '3.0' or '3.1', got '{$specVersion}'.");
        }
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
            'openapi' => $this->specVersion === '3.1' ? '3.1.0' : '3.0.0',
            'info' => [
                'title' => $this->title,
                'version' => $this->version,
            ],
            'paths' => $this->paths,
        ];

        if ($this->components !== []) {
            $components = $this->components;

            if ($this->specVersion === '3.0' && is_array($components['schemas'] ?? null)) {
                $components['schemas'] = array_map(
                    static fn (mixed $schema): mixed => is_array($schema) ? SchemaDialect::toOpenApi30($schema) : $schema,
                    $components['schemas'],
                );
            }

            $spec['components'] = $components;
        }

        return $spec;
    }
}
