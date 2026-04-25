<?php

declare(strict_types=1);

namespace Tests\Attributes;

use EzPhp\OpenApi\Attributes\ApiOperation;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ApiOperation::class)]
final class ApiOperationTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $attr = new ApiOperation();

        self::assertSame('', $attr->summary);
        self::assertSame('', $attr->description);
        self::assertSame([], $attr->tags);
    }

    public function testConstructorSetsAllProperties(): void
    {
        $attr = new ApiOperation(
            summary: 'List users',
            description: 'Returns all active users',
            tags: ['users', 'admin'],
        );

        self::assertSame('List users', $attr->summary);
        self::assertSame('Returns all active users', $attr->description);
        self::assertSame(['users', 'admin'], $attr->tags);
    }

    public function testIsReadFromReflection(): void
    {
        $reflection = new \ReflectionMethod(AttributeFixture::class, 'annotated');
        $attrs = $reflection->getAttributes(ApiOperation::class);

        self::assertCount(1, $attrs);

        $instance = $attrs[0]->newInstance();

        self::assertSame('Annotated method', $instance->summary);
        self::assertSame(['fixtures'], $instance->tags);
    }

    public function testOnlyOneInstanceAllowedPerMethod(): void
    {
        // ApiOperation is NOT IS_REPEATABLE — only one per method.
        $reflection = new \ReflectionMethod(AttributeFixture::class, 'annotated');
        $attrs = $reflection->getAttributes(ApiOperation::class);

        self::assertCount(1, $attrs);
    }
}

// ─── Fixture ─────────────────────────────────────────────────────────────────

final class AttributeFixture
{
    #[ApiOperation(summary: 'Annotated method', tags: ['fixtures'])]
    public function annotated(): void
    {
    }
}
