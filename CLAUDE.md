# Coding Guidelines

Applies to the entire ez-php project — framework core, all modules, and the application template.

---

## Environment

- PHP **8.5**, Composer for dependency management
- All project based commands run **inside Docker** — never directly on the host

```
docker compose exec app <command>
```

Container name: `ez-php-app`, service name: `app`.

---

## Quality Suite

Run after every change:

```
docker compose exec app composer full
```

Executes in order:
1. `phpstan analyse` — static analysis, level 9, config: `phpstan.neon`
2. `php-cs-fixer fix` — auto-fixes style (`@PSR12` + `@PHP83Migration` + strict rules)
3. `phpunit` — all tests with coverage

Individual commands when needed:
```
composer analyse   # PHPStan only
composer cs        # CS Fixer only
composer test      # PHPUnit only
```

**PHPStan:** never suppress with `@phpstan-ignore-line` — always fix the root cause.

---

## Coding Standards

- `declare(strict_types=1)` at the top of every PHP file
- Typed properties, parameters, and return values — avoid `mixed`
- PHPDoc on every class and public method
- One responsibility per class — keep classes small and focused
- Constructor injection — no service locator pattern
- No global state unless intentional and documented

**Naming:**

| Thing | Convention |
|---|---|
| Classes / Interfaces | `PascalCase` |
| Methods / variables | `camelCase` |
| Constants | `UPPER_CASE` |
| Files | Match class name exactly |

**Principles:** SOLID · KISS · DRY · YAGNI

---

## Workflow & Behavior

- Write tests **before or alongside** production code (test-first)
- Read and understand the relevant code before making any changes
- Modify the minimal number of files necessary
- Keep implementations small — if it feels big, it likely belongs in a separate module
- No hidden magic — everything must be explicit and traceable
- No large abstractions without clear necessity
- No heavy dependencies — check if PHP stdlib suffices first
- Respect module boundaries — don't reach across packages
- Keep the framework core small — what belongs in a module stays there
- Document architectural reasoning for non-obvious design decisions
- Do not change public APIs unless necessary
- Prefer composition over inheritance — no premature abstractions

---

## New Modules & CLAUDE.md Files

### 1 — Required files

Every module under `modules/<name>/` must have:

| File | Purpose |
|---|---|
| `composer.json` | package definition, deps, autoload |
| `phpstan.neon` | static analysis config, level 9 |
| `phpunit.xml` | test suite config |
| `.php-cs-fixer.php` | code style config |
| `.gitignore` | ignore `vendor/`, `.env`, cache |
| `.env.example` | environment variable defaults (copy to `.env` on first run) |
| `docker-compose.yml` | Docker Compose service definition (always `container_name: ez-php-<name>-app`) |
| `docker/app/Dockerfile` | module Docker image (`FROM au9500/php:8.5`) |
| `docker/app/container-start.sh` | container entrypoint: `composer install` → `sleep infinity` |
| `docker/app/php.ini` | PHP ini overrides (`memory_limit`, `display_errors`, `xdebug.mode`) |
| `.github/workflows/ci.yml` | standalone CI pipeline |
| `README.md` | public documentation |
| `tests/TestCase.php` | base test case for the module |
| `start.sh` | convenience script: copy `.env`, bring up Docker, wait for services, exec shell |
| `CLAUDE.md` | see section 2 below |

### 2 — CLAUDE.md structure

Every module `CLAUDE.md` must follow this exact structure:

1. **Full content of `CODING_GUIDELINES.md`, verbatim** — copy it as-is, do not summarize or shorten
2. A `---` separator
3. `# Package: ez-php/<name>` (or `# Directory: <name>` for non-package directories)
4. Module-specific section covering:
   - Source structure — file tree with one-line description per file
   - Key classes and their responsibilities
   - Design decisions and constraints
   - Testing approach and infrastructure requirements (MySQL, Redis, etc.)
   - What does **not** belong in this module

### 3 — Docker scaffold

Run from the new module root (requires `"ez-php/docker": "0.*"` in `require-dev`):

```
vendor/bin/docker-init
```

This copies `Dockerfile`, `docker-compose.yml`, `.env.example`, `start.sh`, and `docker/` into the module, replacing `{{MODULE_NAME}}` placeholders. Existing files are never overwritten.

After scaffolding:

1. Adapt `docker-compose.yml` — add or remove services (MySQL, Redis) as needed
2. Adapt `.env.example` — fill in connection defaults matching the services above
3. Assign a unique host port for each exposed service (see table below)

**Allocated host ports:**

| Package | `DB_HOST_PORT` (MySQL) | `REDIS_PORT` |
|---|---|---|
| root (`ez-php-project`) | 3306 | 6379 |
| `ez-php/framework` | 3307 | — |
| `ez-php/orm` | 3309 | — |
| `ez-php/cache` | — | 6380 |
| **next free** | **3310** | **6381** |

Only set a port for services the module actually uses. Modules without external services need no port config.

### 4 — Monorepo scripts

`packages.sh` at the project root is the **central package registry**. Both `push_all.sh` and `update_all.sh` source it — the package list lives in exactly one place.

When adding a new module, add `"$ROOT/modules/<name>"` to the `PACKAGES` array in `packages.sh` in **alphabetical order** among the other `modules/*` entries (before `framework`, `ez-php`, and the root entry at the end).

---

# Package: ez-php/openapi

OpenAPI 3.0.0 spec generator for ez-php. Uses PHP 8.x attributes placed directly on controller methods to describe operations, parameters, and responses. `OpenApiGenerator::generate()` reads `Router::toCache()` and reflects the declared attributes to build an `OpenApiSpec` value object. `OpenApiServiceProvider` binds the generator and registers `GET /openapi.json`. No code-generation magic — attributes are explicit and optional; routes without them still appear in the spec.

---

## Source structure

```
src/
  Attributes/
    ApiOperation.php      — #[ApiOperation(summary, description, tags)] — marks a method as an API operation
    ApiResponse.php       — #[ApiResponse(status, description, schemaClass)] — repeatable; documents a response code
    ApiParam.php          — #[ApiParam(name, type, in, required, description)] — repeatable; documents a parameter
  OpenApiSpec.php         — Immutable value object: title, version, paths; toArray() produces OpenAPI 3.0.0 array
  OpenApiGenerator.php    — Reads Router::toCache() routes, reflects attributes, builds OpenApiSpec
  OpenApiController.php   — Invokable controller: generates spec, returns JSON response at GET /openapi.json
  OpenApiServiceProvider.php — Binds generator lazily, registers GET /openapi.json route

tests/
  TestCase.php                     — Base PHPUnit test case
  Attributes/
    ApiOperationTest.php           — Attribute defaults, properties, Reflection read, non-repeatable guard
    ApiResponseTest.php            — Attribute properties, IS_REPEATABLE behaviour via Reflection
    ApiParamTest.php               — Attribute properties, IS_REPEATABLE behaviour via Reflection
  OpenApiSpecTest.php              — toArray() format, info block, paths passthrough
  OpenApiGeneratorTest.php         — generate() with no routes, attributes, auto path params, reflection failure
  OpenApiControllerTest.php        — HTTP 200, JSON content-type, body structure
  OpenApiServiceProviderTest.php   — Container binding, GET /openapi.json route registration, graceful degradation
```

---

## Key classes and their responsibilities

### ApiOperation (`src/Attributes/ApiOperation.php`)

PHP 8 Attribute targeting methods. Not repeatable — only one per method. Carries `$summary`, `$description`, and `$tags`. All fields have empty/null defaults so the attribute can be applied with only the fields that matter. If not present on a method, the generator produces a bare operation entry (no summary, no tags).

---

### ApiResponse (`src/Attributes/ApiResponse.php`)

Repeatable PHP 8 Attribute (`IS_REPEATABLE | TARGET_METHOD`). The `$status` integer is the only required field. `$schemaClass` is an optional FQCN — when present, the generator derives the short class name (via `basename(str_replace(...))`) and emits a `$ref: '#/components/schemas/ShortName'` entry in the response content. The component schema itself is not generated — that is the application's responsibility.

---

### ApiParam (`src/Attributes/ApiParam.php`)

Repeatable PHP 8 Attribute (`IS_REPEATABLE | TARGET_METHOD`). `$in` defaults to `'query'`. Path parameters (`in: 'path'`) are always `required: true` regardless of the `$required` flag. Parameters listed via `#[ApiParam]` with `in: 'path'` suppress the auto-detection logic for that parameter name.

---

### OpenApiSpec (`src/OpenApiSpec.php`)

Immutable value object. Constructed by `OpenApiGenerator::generate()`. `toArray()` returns a PHP array conforming to the OpenAPI 3.0.0 object structure: `openapi`, `info.title`, `info.version`, `paths`. The paths map keys are raw route patterns (e.g. `/users/{id}`) — OpenAPI uses the same `{param}` placeholder syntax as the framework router.

---

### OpenApiGenerator (`src/OpenApiGenerator.php`)

Accepts the routes array from `Router::toCache()` at construction time. This decouples the generator from the Router class itself (testable without a full application) and allows the service provider to capture the routes lazily at request time.

`generate()` iterates routes and calls `buildOperation()` for each. `buildOperation()` uses `ReflectionMethod` to read attributes. A `\ReflectionException` (e.g. nonexistent method) returns a bare operation without throwing — the route still appears in the spec. Path parameters are auto-detected via `preg_match_all('/\{([^}]+)\}/', $path, ...)` and added with `in: 'path', required: true, schema: {type: 'string'}` if not already declared by an explicit `#[ApiParam]` with `in: 'path'`.

---

### OpenApiController (`src/OpenApiController.php`)

Invokable controller. Calls `$this->generator->generate()` on each request so the spec reflects the live router state. Returns HTTP 200 with `Content-Type: application/json` and a pretty-printed JSON body. `json_encode` failure falls back to `'{}'` rather than throwing.

---

### OpenApiServiceProvider (`src/OpenApiServiceProvider.php`)

`register()` binds `OpenApiGenerator` lazily. The closure captures the container reference and calls `$router->toCache()` when the generator is first resolved (at request time, after all routes are registered). Config values `app.name` and `app.version` are used as the spec title and version. Both the router and config lookups are wrapped in `try/catch` — the provider degrades gracefully in CLI and test contexts.

`boot()` registers `GET /openapi.json` (configurable via `openapi.endpoint`) using `[OpenApiController::class, '__invoke']` so the route appears in the spec itself (via `toCache()`). Also wrapped in `try/catch` for CLI safety.

---

## Design decisions and constraints

- **Generator accepts routes array, not Router.** Passing the `Router::toCache()` result at construction avoids a circular dependency: the service provider registers a route in `boot()`, and if the generator held a live Router reference it might produce stale or missing route data at registration time. By accepting the array as a constructor parameter, the generator is fully testable without a container.
- **Lazy binding captures routes at request time.** The `register()` closure calls `$router->toCache()` when `OpenApiGenerator` is resolved from the container — which happens when the `GET /openapi.json` route is dispatched. At that point all routes (including the spec route itself) are already registered.
- **No component schema generation.** `#[ApiResponse(200, UserSchema::class)]` produces a `$ref` pointing at `#/components/schemas/UserSchema`. The actual schema definition under `components.schemas` is the application's responsibility. Adding automatic schema introspection would require deep knowledge of the application's data model and is out of scope for this module.
- **Auto-detected path parameters use `type: 'string'`.** The router stores path params as `{name}` placeholders; the actual type is not encoded in the route pattern. Auto-detection therefore defaults to `string`. Accurate types require an explicit `#[ApiParam('name', 'integer', 'path')]` declaration — this is intentional; the attribute is the documentation source of truth.
- **`ReflectionException` produces a bare operation.** When a handler method does not exist (e.g. a stale cache entry), the generator degrades gracefully rather than aborting the entire spec build. This is consistent with the "never fail a read request" principle for documentation endpoints.
- **`basename(str_replace(...))` for schema class short name.** PHP has no stdlib `class_basename()`. Using `basename(str_replace('\\', '/', $fqcn))` is idiomatic and avoids an unnecessary `ReflectionClass` instantiation (which would fail for non-existent classes). It produces the correct short name for any valid FQCN.
- **`json_encode` fallback to `'{}'`.** `json_encode` returns `false` only for unencodable input (circular references, `INF`/`NaN`). The spec array contains only strings, booleans, and arrays — failure is practically impossible. The fallback is a defensive measure, not an expected code path.
- **Depends on `ez-php/framework`.** The service provider uses `Router` from `ez-php/framework` for route registration and `toCache()` introspection. This is intentional: the module exists specifically to document the framework's HTTP routes. Direct use of `OpenApiGenerator` (without the service provider) does not require the framework.

---

## Testing approach

No external infrastructure required — all tests run in-process.

- `ApiOperationTest`, `ApiResponseTest`, `ApiParamTest` — instantiate attributes directly and via `ReflectionMethod::getAttributes()`. Test `IS_REPEATABLE` behaviour by applying multiple attributes on fixture methods defined in the same test file. No controller or router required.
- `OpenApiSpecTest` — constructs `OpenApiSpec` directly and asserts `toArray()` structure. Pure unit test.
- `OpenApiGeneratorTest` — constructs `OpenApiGenerator` with a manually built routes array (same shape as `Router::toCache()` returns). Uses a `GeneratorFixtureController` defined in the test file with real attributes applied to real methods. Covers: no routes, no attributes, all three attribute types, auto path params, duplicate suppression, reflection failure fallback, multiple routes.
- `OpenApiControllerTest` — constructs the controller directly with a real `OpenApiGenerator`. Asserts HTTP 200, `application/json` content-type, valid JSON body, and correct `openapi` / `info` structure.
- `OpenApiServiceProviderTest` — constructs a `Container` and `Router` directly (no full Application bootstrap). Registers the provider, calls `register()` and `boot()`, then asserts binding and route presence via `router->toCache()`. Also tests graceful degradation when the Router is not bound.

---

## What does not belong in this module

- **Component schema generation (`#/components/schemas`)** — inspecting data classes or DTOs to emit JSON Schema is a separate concern; belongs in an application-layer generator or a dedicated `ez-php/json-schema` module.
- **Authentication on the `/openapi.json` endpoint** — apply `AuthMiddleware` or `ThrottleMiddleware` to the route in the application's service provider.
- **Swagger UI / ReDoc rendering** — serving the HTML UI requires shipping static assets; belongs in the application layer or a separate `ez-php/swagger-ui` module.
- **YAML output** — `toArray()` produces a PHP array; `json_encode()` is used by the controller. YAML output would require a third-party library (e.g. `symfony/yaml`) and is out of scope.
- **OpenAPI 3.1.x support** — the module targets 3.0.0. 3.1 introduced JSON Schema alignment changes that would require significant structural changes; treat as a separate concern.
- **Webhook / async API documentation** — `asyncapi` is a different specification; not in scope.
- **Route-level attribute scanning (non-controller routes)** — closure-based routes have no class/method to reflect on and are intentionally excluded from attribute scanning.
