<?php

declare(strict_types=1);

namespace EzPhp\OpenApi\Attributes;

use Attribute;

/**
 * Marks a controller method as an API operation.
 *
 * Apply to any controller method to add a summary, description, and tags
 * to the corresponding OpenAPI operation entry.
 *
 * Usage:
 *
 *   #[ApiOperation(summary: 'List users', tags: ['users'])]
 *   public function index(Request $request): Response { ... }
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class ApiOperation
{
    /**
     * @param string       $summary     Short human-readable summary of the operation.
     * @param string       $description Longer description (Markdown supported by most renderers).
     * @param list<string> $tags        Tag names used to group operations in the rendered docs.
     */
    public function __construct(
        public readonly string $summary = '',
        public readonly string $description = '',
        public readonly array $tags = [],
    ) {
    }
}
