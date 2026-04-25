<?php

declare(strict_types=1);

namespace Tests\Attributes;

use EzPhp\OpenApi\Attributes\ApiParam;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ApiParam::class)]
final class ApiParamTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $attr = new ApiParam('search');

        self::assertSame('search', $attr->name);
        self::assertSame('string', $attr->type);
        self::assertSame('query', $attr->in);
        self::assertFalse($attr->required);
        self::assertSame('', $attr->description);
    }

    public function testConstructorSetsAllProperties(): void
    {
        $attr = new ApiParam(
            name: 'id',
            type: 'integer',
            in: 'path',
            required: true,
            description: 'The resource ID',
        );

        self::assertSame('id', $attr->name);
        self::assertSame('integer', $attr->type);
        self::assertSame('path', $attr->in);
        self::assertTrue($attr->required);
        self::assertSame('The resource ID', $attr->description);
    }

    public function testIsRepeatableOnMethod(): void
    {
        $reflection = new \ReflectionMethod(ParamFixture::class, 'multiParam');
        $attrs = $reflection->getAttributes(ApiParam::class);

        self::assertCount(2, $attrs);

        $names = array_map(fn ($a) => $a->newInstance()->name, $attrs);

        self::assertContains('id', $names);
        self::assertContains('include', $names);
    }

    public function testReadFromReflection(): void
    {
        $reflection = new \ReflectionMethod(ParamFixture::class, 'singleParam');
        $attrs = $reflection->getAttributes(ApiParam::class);

        self::assertCount(1, $attrs);

        $instance = $attrs[0]->newInstance();

        self::assertSame('q', $instance->name);
        self::assertSame('string', $instance->type);
        self::assertSame('query', $instance->in);
    }
}

// ─── Fixtures ────────────────────────────────────────────────────────────────

final class ParamFixture
{
    #[ApiParam('id', 'integer', 'path', true)]
    #[ApiParam('include', 'string', 'query', false, 'Relations to include')]
    public function multiParam(): void
    {
    }

    #[ApiParam('q')]
    public function singleParam(): void
    {
    }
}
