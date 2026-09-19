# ez-php/openapi

OpenAPI 3.0.0 spec generator for the ez-php framework. Annotate controller methods with PHP attributes and get a live `GET /openapi.json` endpoint — no code generation, no annotation parsing framework, no YAML.

## Installation

```bash
composer require ez-php/openapi
```

Register the service provider in `provider/modules.php`:

```php
$app->register(\EzPhp\OpenApi\OpenApiServiceProvider::class);
```

## Usage

Add attributes to your controller methods:

```php
use EzPhp\OpenApi\Attributes\ApiOperation;
use EzPhp\OpenApi\Attributes\ApiParam;
use EzPhp\OpenApi\Attributes\ApiResponse;

final class UserController
{
    #[ApiOperation(summary: 'List users', tags: ['users'])]
    #[ApiResponse(200, 'List of users')]
    #[ApiParam('search', 'string', 'query', false, 'Filter by name')]
    public function index(Request $request): Response { ... }

    #[ApiOperation(summary: 'Get user')]
    #[ApiResponse(200, 'The user', UserSchema::class)]
    #[ApiResponse(404, 'Not found')]
    #[ApiParam('id', 'integer', 'path', true, 'The user ID')]
    public function show(Request $request): Response { ... }
}
```

Visit `GET /openapi.json` to retrieve the generated spec.

## Attributes

### `#[ApiOperation]`

| Parameter     | Type            | Default | Description                          |
|---------------|-----------------|---------|--------------------------------------|
| `$summary`    | `string`        | `''`    | Short description of the operation   |
| `$description`| `string`        | `''`    | Longer Markdown description          |
| `$tags`       | `list<string>`  | `[]`    | Tag names for grouping in the UI     |

Not repeatable — one per method.

### `#[ApiResponse]`

| Parameter      | Type      | Default | Description                                    |
|----------------|-----------|---------|------------------------------------------------|
| `$status`      | `int`     | —       | HTTP status code (required)                    |
| `$description` | `string`  | `''`    | Human-readable description                     |
| `$schemaClass` | `?string` | `null`  | FQCN used to emit a `$ref` in the response     |

Repeatable — multiple responses per method.

### `#[ApiParam]`

| Parameter      | Type     | Default    | Description                                      |
|----------------|----------|------------|--------------------------------------------------|
| `$name`        | `string` | —          | Parameter name (required)                        |
| `$type`        | `string` | `'string'` | JSON Schema type: `string`, `integer`, `boolean` |
| `$in`          | `string` | `'query'`  | Location: `path`, `query`, `header`, `cookie`    |
| `$required`    | `bool`   | `false`    | Whether required (path params always required)   |
| `$description` | `string` | `''`       | Human-readable description                       |

Repeatable — multiple parameters per method.

## Configuration

| Key                | Default          | Description                                    |
|--------------------|------------------|------------------------------------------------|
| `app.name`         | `'API'`          | Spec `info.title`                              |
| `app.version`      | `'1.0.0'`        | Spec `info.version`                            |
| `openapi.endpoint` | `'/openapi.json'`| URI for the generated spec                     |
| `openapi.components` | `[]`           | Reusable component objects merged into the spec's `components` key, e.g. `['schemas' => ['User' => ['type' => 'object', ...]]]`. Required for `#[ApiResponse(schemaClass: ...)]` refs to resolve. |
| `openapi.schema_classes` | `[]`       | `list<class-string>` auto-converted into `components.schemas` via `ez-php/json-schema`'s `SchemaGenerator`, keyed by short class name. Requires `ez-php/json-schema` (a soft dependency — install it separately). |

### Auto-generating component schemas

Instead of hand-writing every schema under `openapi.components`, point `openapi.schema_classes`
at your DTOs and let `ez-php/json-schema` derive them from typed properties:

```php
// config/openapi.php
return [
    'schema_classes' => [App\Dto\User::class, App\Dto\Post::class],
];
```

Each class is generated under `components.schemas` keyed by its short class name (`User`,
`Post`) — the same short-name convention `#[ApiResponse(schemaClass: ...)]`'s `$ref` already
uses, so the two line up automatically. A schema manually supplied under
`openapi.components['schemas']` for the same key always wins over the generated one, so you
can override individual classes without losing auto-generation for the rest.

## Notes

- Only `[Controller::class, 'method']` handler routes are reflected for attributes. Closure-based routes appear in the spec without attribute data.
- Path parameters (`{id}`) are auto-detected from route patterns and added as `in: 'path', required: true, type: 'string'` when not explicitly declared via `#[ApiParam]`.
- Component schemas (`#/components/schemas/...`) referenced by `$schemaClass` resolve against whatever is configured in `openapi.components` (hand-written) or `openapi.schema_classes` (auto-generated) — this module emits the `$ref` and the `components` key it points at, but does not itself introspect your code; population is either manual or delegated to `ez-php/json-schema`.
