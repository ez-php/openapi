<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Contracts\ContainerInterface;
use EzPhp\OpenApi\OpenApiController;
use EzPhp\OpenApi\OpenApiGenerator;
use EzPhp\OpenApi\OpenApiServiceProvider;
use EzPhp\OpenApi\OpenApiSpec;
use EzPhp\Routing\Router;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(OpenApiServiceProvider::class)]
#[UsesClass(OpenApiGenerator::class)]
#[UsesClass(OpenApiController::class)]
#[UsesClass(OpenApiSpec::class)]
final class OpenApiServiceProviderTest extends TestCase
{
    private ServiceProviderFakeContainer $container;

    private Router $router;

    private OpenApiServiceProvider $provider;

    protected function setUp(): void
    {
        $this->container = new ServiceProviderFakeContainer();
        $this->router = new Router($this->container);
        $this->container->instance(Router::class, $this->router);
        $this->container->instance(ContainerInterface::class, $this->container);

        $this->provider = new OpenApiServiceProvider($this->container);
    }

    public function testRegisterBindsOpenApiGenerator(): void
    {
        $this->provider->register();

        $generator = $this->container->make(OpenApiGenerator::class);

        self::assertInstanceOf(OpenApiGenerator::class, $generator);
    }

    public function testMakeOpenApiGeneratorReturnsInstance(): void
    {
        $this->provider->register();

        $generator = $this->container->make(OpenApiGenerator::class);

        self::assertInstanceOf(OpenApiGenerator::class, $generator);
    }

    public function testBootRegistersGetOpenApiJsonRoute(): void
    {
        $this->provider->register();
        $this->provider->boot();

        // The /openapi.json route must be registered
        $routes = $this->router->toCache();

        $found = false;

        foreach ($routes as $route) {
            if ($route['method'] === 'GET' && $route['path'] === '/openapi.json') {
                $found = true;
                break;
            }
        }

        self::assertTrue($found, 'GET /openapi.json route was not registered by boot()');
    }

    public function testBootHandlesRouterNotBound(): void
    {
        $emptyContainer = new ServiceProviderFakeContainer();
        $provider = new OpenApiServiceProvider($emptyContainer);
        $provider->register();

        // Must not throw — Router is not bound, boot() degrades gracefully
        $provider->boot();

        $this->addToAssertionCount(1);
    }

    public function testRegisterUsesDefaultTitleAndVersion(): void
    {
        $this->provider->register();

        $generator = $this->container->make(OpenApiGenerator::class);
        self::assertInstanceOf(OpenApiGenerator::class, $generator);

        $spec = $generator->generate()->toArray();

        self::assertSame('API', $spec['info']['title']);
        self::assertSame('1.0.0', $spec['info']['version']);
    }
}

// ─── Fixture ─────────────────────────────────────────────────────────────────

/**
 * Minimal ContainerInterface implementation for service provider tests.
 * Supports Closure-based bindings and instance registration only.
 */
final class ServiceProviderFakeContainer implements ContainerInterface
{
    /** @var array<string, callable> */
    private array $bindings = [];

    public function bind(string $abstract, string|callable|null $factory = null): static
    {
        if (is_callable($factory)) {
            $this->bindings[$abstract] = $factory;
        }

        return $this;
    }

    public function make(string $abstract): mixed
    {
        if (!isset($this->bindings[$abstract])) {
            throw new \RuntimeException("Not bound: {$abstract}");
        }

        return ($this->bindings[$abstract])($this);
    }

    public function instance(string $abstract, object $instance): void
    {
        $this->bindings[$abstract] = static fn (ContainerInterface $_c): object => $instance;
    }
}
