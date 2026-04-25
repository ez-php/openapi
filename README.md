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

## Notes

- Only `[Controller::class, 'method']` handler routes are reflected for attributes. Closure-based routes appear in the spec without attribute data.
- Path parameters (`{id}`) are auto-detected from route patterns and added as `in: 'path', required: true, type: 'string'` when not explicitly declared via `#[ApiParam]`.
- Component schemas (`#/components/schemas/...`) referenced by `$schemaClass` must be registered separately — this module generates the `$ref` but not the schema definition.
