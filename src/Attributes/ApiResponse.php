<?php

declare(strict_types=1);

namespace EzPhp\OpenApi\Attributes;

use Attribute;

/**
 * Declares an HTTP response for a controller method.
 *
 * This attribute is repeatable — apply multiple instances to document
 * all possible response codes for a single operation.
 *
 * Usage:
 *
 *   #[ApiResponse(200, 'The user object', UserSchema::class)]
 *   #[ApiResponse(404, 'User not found')]
 *   public function show(Request $request): Response { ... }
 *
 * When `$schemaClass` is provided its short class name is used as the
 * `$ref` key under `#/components/schemas/`. The component schema
 * itself must be registered in the spec separately (e.g. via a custom
 * service provider that extends `OpenApiGenerator`).
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ApiResponse
{
    /**
     * @param int         $status      HTTP status code (e.g. 200, 201, 404).
     * @param string      $description Human-readable description of this response.
     * @param string|null $schemaClass Optional FQCN used to build a JSON Schema `$ref`.
     */
    public function __construct(
        public readonly int $status,
        public readonly string $description = '',
        public readonly ?string $schemaClass = null,
    ) {
    }
}
