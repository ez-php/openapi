<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Http\Request;
use EzPhp\Http\Response;
use EzPhp\OpenApi\Attributes\ApiOperation;
use EzPhp\OpenApi\Attributes\ApiParam;
use EzPhp\OpenApi\Attributes\ApiResponse;
use EzPhp\OpenApi\OpenApiGenerator;
use EzPhp\OpenApi\OpenApiSpec;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(OpenApiGenerator::class)]
#[UsesClass(OpenApiSpec::class)]
#[UsesClass(ApiOperation::class)]
#[UsesClass(ApiParam::class)]
#[UsesClass(ApiResponse::class)]
final class OpenApiGeneratorTest extends TestCase
{
    // ─── generate() returns OpenApiSpec ───────────────────────────────────────

    public function testGenerateReturnsOpenApiSpec(): void
    {
        $generator = new OpenApiGenerator([]);

        self::assertInstanceOf(OpenApiSpec::class, $generator->generate());
    }

    public function testGenerateWithNoRoutesProducesEmptyPaths(): void
    {
        $generator = new OpenApiGenerator([]);

        $spec = $generator->generate()->toArray();

        self::assertSame([], $spec['paths']);
    }

    public function testTitleAndVersionArePassedToSpec(): void
    {
        $generator = new OpenApiGenerator([], 'My Service', '2.3.1');

        $spec = $generator->generate()->toArray();

        self::assertSame('My Service', $spec['info']['title']);
        self::assertSame('2.3.1', $spec['info']['version']);
    }

    // ─── Route without attributes ─────────────────────────────────────────────

    public function testRouteWithoutAttributesGetsDefaultOperation(): void
    {
        $routes = [$this->makeRoute('GET', '/ping', GeneratorFixtureController::class, 'noAttributes')];
        $generator = new OpenApiGenerator($routes);

        $spec = $generator->generate()->toArray();

        self::assertArrayHasKey('/ping', $spec['paths']);

        $paths = $spec['paths'];
        self::assertIsArray($paths['/ping']);

        $pingPath = $paths['/ping'];
        self::assertIsArray($pingPath['get']);

        $getOp = $pingPath['get'];
        self::assertIsArray($getOp['responses']);

        $responses = $getOp['responses'];
        self::assertArrayHasKey('200', $responses);
    }

    // ─── ApiOperation attribute ───────────────────────────────────────────────

    public function testSummaryIsIncludedFromApiOperation(): void
    {
        $routes = [$this->makeRoute('GET', '/users', GeneratorFixtureController::class, 'listUsers')];
        $generator = new OpenApiGenerator($routes);

        $spec = $generator->generate()->toArray();

        $paths = $spec['paths'];
        self::assertIsArray($paths['/users']);
        $usersPath = $paths['/users'];
        self::assertIsArray($usersPath['get']);
        $getOp = $usersPath['get'];

        self::assertSame('List users', $getOp['summary']);
    }

    public function testDescriptionIsIncludedFromApiOperation(): void
    {
        $routes = [$this->makeRoute('GET', '/users', GeneratorFixtureController::class, 'listUsers')];
        $generator = new OpenApiGenerator($routes);

        $spec = $generator->generate()->toArray();

        $paths = $spec['paths'];
        self::assertIsArray($paths['/users']);
        $usersPath = $paths['/users'];
        self::assertIsArray($usersPath['get']);
        $getOp = $usersPath['get'];

        self::assertSame('Returns all active users', $getOp['description']);
    }

    public function testTagsAreIncludedFromApiOperation(): void
    {
        $routes = [$this->makeRoute('GET', '/users', GeneratorFixtureController::class, 'listUsers')];
        $generator = new OpenApiGenerator($routes);

        $spec = $generator->generate()->toArray();

        $paths = $spec['paths'];
        self::assertIsArray($paths['/users']);
        $usersPath = $paths['/users'];
        self::assertIsArray($usersPath['get']);
        $getOp = $usersPath['get'];

        self::assertSame(['users'], $getOp['tags']);
    }

    public function testEmptySummaryIsOmitted(): void
    {
        $routes = [$this->makeRoute('GET', '/ping', GeneratorFixtureController::class, 'noAttributes')];
        $generator = new OpenApiGenerator($routes);

        $spec = $generator->generate()->toArray();

        $paths = $spec['paths'];
        self::assertIsArray($paths['/ping']);
        $pingPath = $paths['/ping'];
        self::assertIsArray($pingPath['get']);
        $getOp = $pingPath['get'];

        self::assertArrayNotHasKey('summary', $getOp);
    }

    // ─── ApiResponse attributes ───────────────────────────────────────────────

    public function testSingleApiResponseIsIncluded(): void
    {
        $routes = [$this->makeRoute('GET', '/users', GeneratorFixtureController::class, 'listUsers')];
        $generator = new OpenApiGenerator($routes);

        $spec = $generator->generate()->toArray();

        $paths = $spec['paths'];
        self::assertIsArray($paths['/users']);
        $usersPath = $paths['/users'];
        self::assertIsArray($usersPath['get']);
        $getOp = $usersPath['get'];
        self::assertIsArray($getOp['responses']);

        $responses = $getOp['responses'];
        self::assertArrayHasKey('200', $responses);

        $response200 = $responses['200'];
        self::assertIsArray($response200);
        self::assertSame('OK', $response200['description']);
    }

    public function testMultipleApiResponsesAreAllIncluded(): void
    {
        $routes = [$this->makeRoute('GET', '/users/{id}', GeneratorFixtureController::class, 'showUser')];
        $generator = new OpenApiGenerator($routes);

        $spec = $generator->generate()->toArray();

        $paths = $spec['paths'];
        self::assertIsArray($paths['/users/{id}']);
        $userPath = $paths['/users/{id}'];
        self::assertIsArray($userPath['get']);
        $getOp = $userPath['get'];
        self::assertIsArray($getOp['responses']);

        $responses = $getOp['responses'];
        self::assertArrayHasKey('200', $responses);
        self::assertArrayHasKey('404', $responses);
    }

    public function testApiResponseWithSchemaClassProducesRef(): void
    {
        $routes = [$this->makeRoute('GET', '/users/{id}', GeneratorFixtureController::class, 'showUser')];
        $generator = new OpenApiGenerator($routes);

        $spec = $generator->generate()->toArray();

        $paths = $spec['paths'];
        self::assertIsArray($paths['/users/{id}']);
        $userPath = $paths['/users/{id}'];
        self::assertIsArray($userPath['get']);
        $getOp = $userPath['get'];
        self::assertIsArray($getOp['responses']);

        $responses = $getOp['responses'];
        $response200 = $responses['200'];
        self::assertIsArray($response200);
        self::assertArrayHasKey('content', $response200);

        $content = $response200['content'];
        self::assertIsArray($content);
        self::assertArrayHasKey('application/json', $content);

        $jsonContent = $content['application/json'];
        self::assertIsArray($jsonContent);
        self::assertIsArray($jsonContent['schema']);

        $schema = $jsonContent['schema'];
        $ref = $schema['$ref'];
        self::assertIsString($ref);
        self::assertStringContainsString('UserSchema', $ref);
    }

    public function testDefaultResponseAddedWhenNoApiResponseDeclared(): void
    {
        $routes = [$this->makeRoute('POST', '/items', GeneratorFixtureController::class, 'noAttributes')];
        $generator = new OpenApiGenerator($routes);

        $spec = $generator->generate()->toArray();

        $paths = $spec['paths'];
        self::assertIsArray($paths['/items']);
        $itemsPath = $paths['/items'];
        self::assertIsArray($itemsPath['post']);
        $postOp = $itemsPath['post'];
        self::assertIsArray($postOp['responses']);

        self::assertArrayHasKey('200', $postOp['responses']);
    }

    // ─── ApiParam attributes ──────────────────────────────────────────────────

    public function testExplicitQueryParamIsIncluded(): void
    {
        $routes = [$this->makeRoute('GET', '/search', GeneratorFixtureController::class, 'search')];
        $generator = new OpenApiGenerator($routes);

        $spec = $generator->generate()->toArray();

        $paths = $spec['paths'];
        self::assertIsArray($paths['/search']);
        $searchPath = $paths['/search'];
        self::assertIsArray($searchPath['get']);
        $getOp = $searchPath['get'];
        self::assertIsArray($getOp['parameters']);

        $params = $getOp['parameters'];
        self::assertCount(1, $params);

        $param = $params[0];
        self::assertIsArray($param);
        self::assertSame('q', $param['name']);
        self::assertSame('query', $param['in']);
        self::assertFalse($param['required']);

        $schema = $param['schema'];
        self::assertIsArray($schema);
        self::assertSame('string', $schema['type']);
    }

    public function testExplicitPathParamIsIncluded(): void
    {
        $routes = [$this->makeRoute('GET', '/users/{id}', GeneratorFixtureController::class, 'showUser')];
        $generator = new OpenApiGenerator($routes);

        $spec = $generator->generate()->toArray();

        $paths = $spec['paths'];
        self::assertIsArray($paths['/users/{id}']);
        $userPath = $paths['/users/{id}'];
        self::assertIsArray($userPath['get']);
        $getOp = $userPath['get'];
        self::assertIsArray($getOp['parameters']);

        $pathParam = $this->findParam($getOp['parameters'], 'id');

        self::assertNotNull($pathParam);
        self::assertSame('path', $pathParam['in']);
        self::assertTrue($pathParam['required']);

        $schema = $pathParam['schema'];
        self::assertIsArray($schema);
        self::assertSame('integer', $schema['type']);
    }

    public function testPathParamIsAlwaysRequiredEvenWhenNotMarked(): void
    {
        $routes = [$this->makeRoute('GET', '/users/{id}', GeneratorFixtureController::class, 'showUser')];
        $generator = new OpenApiGenerator($routes);

        $spec = $generator->generate()->toArray();

        $paths = $spec['paths'];
        self::assertIsArray($paths['/users/{id}']);
        $userPath = $paths['/users/{id}'];
        self::assertIsArray($userPath['get']);
        $getOp = $userPath['get'];
        self::assertIsArray($getOp['parameters']);

        $pathParam = $this->findParam($getOp['parameters'], 'id');

        self::assertNotNull($pathParam);
        self::assertTrue($pathParam['required']);
    }

    public function testParamDescriptionIsIncluded(): void
    {
        $routes = [$this->makeRoute('GET', '/users/{id}', GeneratorFixtureController::class, 'showUser')];
        $generator = new OpenApiGenerator($routes);

        $spec = $generator->generate()->toArray();

        $paths = $spec['paths'];
        self::assertIsArray($paths['/users/{id}']);
        $userPath = $paths['/users/{id}'];
        self::assertIsArray($userPath['get']);
        $getOp = $userPath['get'];
        self::assertIsArray($getOp['parameters']);

        $idParam = $this->findParam($getOp['parameters'], 'id');

        self::assertNotNull($idParam);
        self::assertSame('The user ID', $idParam['description']);
    }

    // ─── Auto-detected path parameters ───────────────────────────────────────

    public function testAutoDetectedPathParamAdded(): void
    {
        $routes = [$this->makeRoute('DELETE', '/posts/{slug}', GeneratorFixtureController::class, 'noAttributes')];
        $generator = new OpenApiGenerator($routes);

        $spec = $generator->generate()->toArray();

        $paths = $spec['paths'];
        self::assertIsArray($paths['/posts/{slug}']);
        $postPath = $paths['/posts/{slug}'];
        self::assertIsArray($postPath['delete']);
        $deleteOp = $postPath['delete'];
        self::assertIsArray($deleteOp['parameters'] ?? null);

        $slugParam = $this->findParam($deleteOp['parameters'], 'slug');

        self::assertNotNull($slugParam);
        self::assertSame('path', $slugParam['in']);
        self::assertTrue($slugParam['required']);

        $schema = $slugParam['schema'];
        self::assertIsArray($schema);
        self::assertSame('string', $schema['type']);
    }

    public function testAutoDetectedParamNotDuplicatedWhenAlreadyDeclared(): void
    {
        // showUser declares #[ApiParam('id', 'integer', 'path')] so auto-detection must not add a second 'id' param
        $routes = [$this->makeRoute('GET', '/users/{id}', GeneratorFixtureController::class, 'showUser')];
        $generator = new OpenApiGenerator($routes);

        $spec = $generator->generate()->toArray();

        $paths = $spec['paths'];
        self::assertIsArray($paths['/users/{id}']);
        $userPath = $paths['/users/{id}'];
        self::assertIsArray($userPath['get']);
        $getOp = $userPath['get'];
        self::assertIsArray($getOp['parameters']);

        $params = $getOp['parameters'];
        $idParams = array_values(array_filter($params, fn (mixed $p): bool => is_array($p) && ($p['name'] ?? '') === 'id'));

        self::assertCount(1, $idParams);
    }

    // ─── Multiple routes ──────────────────────────────────────────────────────

    public function testMultipleRoutesProduceMultiplePaths(): void
    {
        $routes = [
            $this->makeRoute('GET', '/users', GeneratorFixtureController::class, 'listUsers'),
            $this->makeRoute('POST', '/users', GeneratorFixtureController::class, 'noAttributes'),
            $this->makeRoute('GET', '/users/{id}', GeneratorFixtureController::class, 'showUser'),
        ];
        $generator = new OpenApiGenerator($routes);

        $spec = $generator->generate()->toArray();

        $paths = $spec['paths'];
        self::assertArrayHasKey('/users', $paths);
        self::assertArrayHasKey('/users/{id}', $paths);

        self::assertIsArray($paths['/users']);
        $usersPath = $paths['/users'];
        self::assertArrayHasKey('get', $usersPath);
        self::assertArrayHasKey('post', $usersPath);

        self::assertIsArray($paths['/users/{id}']);
        $userPath = $paths['/users/{id}'];
        self::assertArrayHasKey('get', $userPath);
    }

    // ─── Reflection failure ───────────────────────────────────────────────────

    public function testNonExistentMethodFallsBackToDefaultOperation(): void
    {
        $routes = [$this->makeRoute('GET', '/missing', GeneratorFixtureController::class, 'nonExistentMethod')];
        $generator = new OpenApiGenerator($routes);

        // Should not throw — falls back gracefully
        $spec = $generator->generate()->toArray();

        $paths = $spec['paths'];
        self::assertArrayHasKey('/missing', $paths);

        self::assertIsArray($paths['/missing']);
        $missingPath = $paths['/missing'];
        self::assertIsArray($missingPath['get']);
        $getOp = $missingPath['get'];
        self::assertIsArray($getOp['responses']);

        self::assertArrayHasKey('200', $getOp['responses']);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * @param class-string $class
     *
     * @return array{method: string, path: string, name: string|null, handler: array{0: class-string, 1: string}, middleware: array<int, class-string>, constraints: array<string, string>, csrfExempt: bool}
     */
    private function makeRoute(string $method, string $path, string $class, string $action): array
    {
        return [
            'method' => $method,
            'path' => $path,
            'name' => null,
            'handler' => [$class, $action],
            'middleware' => [],
            'constraints' => [],
            'csrfExempt' => false,
        ];
    }

    /**
     * @param array<mixed> $params
     *
     * @return array<string, mixed>|null
     */
    private function findParam(array $params, string $name): ?array
    {
        foreach ($params as $param) {
            if (is_array($param) && isset($param['name']) && $param['name'] === $name) {
                /** @var array<string, mixed> $param */
                return $param;
            }
        }

        return null;
    }
}

// ─── Fixtures ────────────────────────────────────────────────────────────────

final class GeneratorFixtureController
{
    public function noAttributes(Request $request): Response
    {
        return new Response('', 200);
    }

    #[ApiOperation(summary: 'List users', description: 'Returns all active users', tags: ['users'])]
    #[ApiResponse(200, 'OK')]
    public function listUsers(Request $request): Response
    {
        return new Response('', 200);
    }

    #[ApiOperation(summary: 'Get user')]
    #[ApiResponse(200, 'The user', 'App\\Schemas\\UserSchema')]
    #[ApiResponse(404, 'Not found')]
    #[ApiParam('id', 'integer', 'path', true, 'The user ID')]
    public function showUser(Request $request): Response
    {
        return new Response('', 200);
    }

    #[ApiOperation(summary: 'Search')]
    #[ApiParam('q', 'string', 'query', false, 'Search term')]
    public function search(Request $request): Response
    {
        return new Response('', 200);
    }
}
