<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Contracts\ConfigInterface;
use EzPhp\OpenApi\OpenApiGenerator;
use EzPhp\OpenApi\OpenApiServiceProvider;
use EzPhp\OpenApi\OpenApiSpec;
use EzPhp\OpenApi\SchemaDialect;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Self-referencing DTO for the schema_classes integration.
 */
final class OpenApiTreeNode
{
    public function __construct(
        public string $label = '',
        public ?OpenApiTreeNode $next = null,
    ) {
    }
}

/**
 * OpenAPI 3.0 / 3.1 output and the JSON Schema dialect conversion for 3.0.
 *
 * @package Tests
 */
#[CoversClass(OpenApiSpec::class)]
#[CoversClass(SchemaDialect::class)]
#[CoversClass(OpenApiGenerator::class)]
#[CoversClass(OpenApiServiceProvider::class)]
final class OpenApiVersionTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function components(): array
    {
        return ['schemas' => ['User' => [
            'type' => 'object',
            'properties' => [
                'nickname' => ['type' => ['string', 'null'], 'examples' => ['neo']],
                'age' => ['anyOf' => [['type' => 'integer'], ['type' => 'null']]],
                'role' => ['type' => 'string', 'enum' => ['admin', 'user', null]],
                'kind' => ['const' => 'user'],
                'manager' => ['anyOf' => [['$ref' => '#/components/schemas/User'], ['type' => 'null']]],
                'id' => ['type' => ['integer', 'string']],
                'tags' => ['type' => 'array', 'items' => ['type' => ['string', 'null']]],
            ],
        ]]];
    }

    public function test_3_1_passes_json_schema_through(): void
    {
        $spec = (new OpenApiSpec('API', '1', [], self::components(), '3.1'))->toArray();

        self::assertSame('3.1.0', $spec['openapi']);
        self::assertSame(self::components(), $spec['components'] ?? null);
    }

    public function test_3_0_is_the_default_and_converts_the_dialect(): void
    {
        $spec = (new OpenApiSpec('API', '1', [], self::components()))->toArray();

        self::assertSame('3.0.0', $spec['openapi']);
        self::assertSame([
            'type' => 'object',
            'properties' => [
                'nickname' => ['type' => 'string', 'nullable' => true, 'example' => 'neo'],
                'age' => ['type' => 'integer', 'nullable' => true],
                'role' => ['type' => 'string', 'enum' => ['admin', 'user'], 'nullable' => true],
                'kind' => ['enum' => ['user']],
                'manager' => ['allOf' => [['$ref' => '#/components/schemas/User']], 'nullable' => true],
                'id' => ['anyOf' => [['type' => 'integer'], ['type' => 'string']]],
                'tags' => ['type' => 'array', 'items' => ['type' => 'string', 'nullable' => true]],
            ],
        ], self::dig($spec, 'components', 'schemas', 'User'));
    }

    public function test_unknown_version_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new OpenApiSpec('API', '1', [], [], '2.0');
    }

    public function test_provider_reads_the_version_and_generates_recursive_schema_classes(): void
    {
        $container = new ServiceProviderFakeContainer();
        $container->instance(ConfigInterface::class, new OpenApiFakeConfig([
            'openapi.version' => '3.1',
            'openapi.schema_classes' => [OpenApiTreeNode::class],
        ]));
        (new OpenApiServiceProvider($container))->register();

        $spec = $container->make(OpenApiGenerator::class)->generate()->toArray();

        self::assertSame('3.1.0', $spec['openapi']);
        self::assertSame(
            ['anyOf' => [['$ref' => '#/components/schemas/OpenApiTreeNode'], ['type' => 'null']], 'default' => null],
            self::dig($spec, 'components', 'schemas', 'OpenApiTreeNode', 'properties', 'next'),
        );
    }

    /**
     * Walk nested arrays, asserting each level exists.
     *
     * @param array<array-key, mixed> $data
     */
    private static function dig(array $data, string ...$keys): mixed
    {
        $value = $data;

        foreach ($keys as $key) {
            self::assertIsArray($value);
            self::assertArrayHasKey($key, $value);
            $value = $value[$key];
        }

        return $value;
    }
}
