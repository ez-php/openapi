<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\OpenApi\OpenApiSpec;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(OpenApiSpec::class)]
final class OpenApiSpecTest extends TestCase
{
    public function testToArrayContainsOpenApiVersion(): void
    {
        $spec = new OpenApiSpec('My API', '2.0.0', []);

        $array = $spec->toArray();

        self::assertSame('3.0.0', $array['openapi']);
    }

    public function testToArrayContainsInfoBlock(): void
    {
        $spec = new OpenApiSpec('My API', '2.0.0', []);

        $array = $spec->toArray();

        self::assertSame('My API', $array['info']['title']);
        self::assertSame('2.0.0', $array['info']['version']);
    }

    public function testToArrayContainsEmptyPathsWhenNoneProvided(): void
    {
        $spec = new OpenApiSpec('API', '1.0.0', []);

        $array = $spec->toArray();

        self::assertSame([], $array['paths']);
    }

    public function testToArrayContainsProvidedPaths(): void
    {
        $paths = [
            '/users' => [
                'get' => ['responses' => ['200' => ['description' => 'OK']]],
            ],
        ];

        $spec = new OpenApiSpec('API', '1.0.0', $paths);

        $array = $spec->toArray();

        self::assertArrayHasKey('/users', $array['paths']);
        self::assertArrayHasKey('get', $array['paths']['/users']);
    }

    public function testToArrayOmitsComponentsWhenNoneProvided(): void
    {
        $spec = new OpenApiSpec('API', '1.0.0', []);

        self::assertArrayNotHasKey('components', $spec->toArray());
    }

    public function testToArrayContainsProvidedComponents(): void
    {
        $components = [
            'schemas' => [
                'User' => [
                    'type' => 'object',
                    'properties' => ['id' => ['type' => 'integer']],
                ],
            ],
        ];

        $spec = new OpenApiSpec('API', '1.0.0', [], $components);

        $array = $spec->toArray();

        self::assertArrayHasKey('components', $array);
        self::assertSame($components, $array['components'] ?? null);
    }

    public function testProvidedComponentsResolveResponseRefs(): void
    {
        // A $ref emitted by OpenApiGenerator points at #/components/schemas/<ShortName>.
        // Supplying the matching component makes that reference resolvable.
        $paths = [
            '/users' => [
                'get' => [
                    'responses' => [
                        '200' => [
                            'description' => 'OK',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/User'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $spec = new OpenApiSpec('API', '1.0.0', $paths, ['schemas' => ['User' => ['type' => 'object']]]);

        $array = $spec->toArray();

        $components = $array['components'] ?? null;
        self::assertIsArray($components);

        $schemas = $components['schemas'] ?? null;
        self::assertIsArray($schemas);
        self::assertArrayHasKey('User', $schemas);
    }

    public function testToArrayStructureMatchesOpenApiSchema(): void
    {
        $spec = new OpenApiSpec('Test API', '0.1.0', []);
        $array = $spec->toArray();

        self::assertArrayHasKey('openapi', $array);
        self::assertArrayHasKey('info', $array);
        self::assertArrayHasKey('paths', $array);
        self::assertArrayHasKey('title', $array['info']);
        self::assertArrayHasKey('version', $array['info']);
    }
}
