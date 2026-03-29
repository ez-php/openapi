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
     * @param string                                             $title   API title shown in the spec's `info` block.
     * @param string                                             $version API version string shown in the spec's `info` block.
     * @param array<string, array<string, array<string, mixed>>> $paths   Spec paths map: path → method → operation.
     */
    public function __construct(
        private readonly string $title,
        private readonly string $version,
        private readonly array $paths,
    ) {
    }

    /**
     * Serialize the spec to a plain PHP array following the OpenAPI 3.0.0 schema.
     *
     * @return array{openapi: string, info: array{title: string, version: string}, paths: array<string, array<string, array<string, mixed>>>}
     */
    public function toArray(): array
    {
        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => $this->title,
                'version' => $this->version,
            ],
            'paths' => $this->paths,
        ];
    }
}
