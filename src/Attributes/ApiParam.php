<?php

declare(strict_types=1);

namespace EzPhp\OpenApi\Attributes;

use Attribute;

/**
 * Declares a parameter for a controller method operation.
 *
 * This attribute is repeatable — apply multiple instances to document
 * all parameters for a single operation.
 *
 * Path parameters (`in: 'path'`) are automatically marked as required
 * regardless of the `$required` flag.
 *
 * Usage:
 *
 *   #[ApiParam('id', 'integer', 'path', true, 'The user ID')]
 *   #[ApiParam('include', 'string', 'query', false, 'Comma-separated relations to include')]
 *   public function show(Request $request): Response { ... }
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ApiParam
{
    /**
     * @param string $name        Parameter name as it appears in the path or query string.
     * @param string $type        JSON Schema primitive type: 'string', 'integer', 'number', 'boolean', 'array'.
     * @param string $in          Location: 'path', 'query', 'header', or 'cookie'.
     * @param bool   $required    Whether the parameter is required.
     * @param string $description Human-readable description of the parameter.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type = 'string',
        public readonly string $in = 'query',
        public readonly bool $required = false,
        public readonly string $description = '',
    ) {
    }
}
