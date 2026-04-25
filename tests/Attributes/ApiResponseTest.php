<?php

declare(strict_types=1);

namespace Tests\Attributes;

use EzPhp\OpenApi\Attributes\ApiResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ApiResponse::class)]
final class ApiResponseTest extends TestCase
{
    public function testRequiredStatusProperty(): void
    {
        $attr = new ApiResponse(status: 200);

        self::assertSame(200, $attr->status);
        self::assertSame('', $attr->description);
        self::assertNull($attr->schemaClass);
    }

    public function testConstructorSetsAllProperties(): void
    {
        $attr = new ApiResponse(
            status: 201,
            description: 'Created',
            schemaClass: 'App\\Schemas\\UserSchema',
        );

        self::assertSame(201, $attr->status);
        self::assertSame('Created', $attr->description);
        self::assertSame('App\\Schemas\\UserSchema', $attr->schemaClass);
    }

    public function testIsRepeatableOnMethod(): void
    {
        $reflection = new \ReflectionMethod(RepeatableResponseFixture::class, 'multiResponse');
        $attrs = $reflection->getAttributes(ApiResponse::class);

        self::assertCount(2, $attrs);

        $statuses = array_map(fn ($a) => $a->newInstance()->status, $attrs);

        self::assertContains(200, $statuses);
        self::assertContains(404, $statuses);
    }

    public function testReadFromReflectionWithSchema(): void
    {
        $reflection = new \ReflectionMethod(RepeatableResponseFixture::class, 'withSchema');
        $attrs = $reflection->getAttributes(ApiResponse::class);

        self::assertCount(1, $attrs);

        $instance = $attrs[0]->newInstance();

        self::assertSame(200, $instance->status);
        self::assertSame('UserSchema', $instance->schemaClass);
    }
}

// ─── Fixtures ────────────────────────────────────────────────────────────────

final class RepeatableResponseFixture
{
    #[ApiResponse(200, 'The resource')]
    #[ApiResponse(404, 'Not found')]
    public function multiResponse(): void
    {
    }

    #[ApiResponse(200, 'The user', 'UserSchema')]
    public function withSchema(): void
    {
    }
}
