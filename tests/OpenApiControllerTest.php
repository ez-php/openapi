<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Http\Request;
use EzPhp\OpenApi\OpenApiController;
use EzPhp\OpenApi\OpenApiGenerator;
use EzPhp\OpenApi\OpenApiSpec;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(OpenApiController::class)]
#[UsesClass(OpenApiGenerator::class)]
#[UsesClass(OpenApiSpec::class)]
final class OpenApiControllerTest extends TestCase
{
    private OpenApiController $controller;

    protected function setUp(): void
    {
        $this->controller = new OpenApiController(new OpenApiGenerator([]));
    }

    public function testReturns200(): void
    {
        $response = ($this->controller)($this->makeRequest());

        self::assertSame(200, $response->status());
    }

    public function testResponseHasJsonContentType(): void
    {
        $response = ($this->controller)($this->makeRequest());

        $headers = array_change_key_case($response->headers(), CASE_LOWER);

        self::assertStringContainsString('application/json', $headers['content-type'] ?? '');
    }

    public function testResponseBodyIsValidJson(): void
    {
        $response = ($this->controller)($this->makeRequest());

        $decoded = json_decode($response->body(), true);

        self::assertIsArray($decoded);
    }

    public function testResponseBodyContainsOpenApiVersion(): void
    {
        $response = ($this->controller)($this->makeRequest());

        $decoded = json_decode($response->body(), true);
        self::assertIsArray($decoded);

        self::assertSame('3.0.0', $decoded['openapi']);
    }

    public function testResponseBodyContainsInfoBlock(): void
    {
        $controller = new OpenApiController(new OpenApiGenerator([], 'Test API', '1.2.3'));

        $response = $controller($this->makeRequest());

        $decoded = json_decode($response->body(), true);
        self::assertIsArray($decoded);

        $info = $decoded['info'];
        self::assertIsArray($info);

        self::assertSame('Test API', $info['title']);
        self::assertSame('1.2.3', $info['version']);
    }

    public function testResponseBodyContainsPathsKey(): void
    {
        $response = ($this->controller)($this->makeRequest());

        $decoded = json_decode($response->body(), true);
        self::assertIsArray($decoded);

        self::assertArrayHasKey('paths', $decoded);
    }

    private function makeRequest(): Request
    {
        return new Request('GET', '/openapi.json');
    }
}
