<?php

declare(strict_types=1);

namespace EzPhp\OpenApi;

/**
 * Class SchemaDialect
 *
 * Rewrites JSON Schema 2020-12 constructs (what ez-php/json-schema emits and what
 * OpenAPI 3.1 uses) into the OpenAPI 3.0 Schema Object dialect:
 *
 *   type: [T, "null"]            → type: T, nullable: true
 *   type: [A, B]                 → anyOf: [{type: A}, {type: B}]
 *   anyOf/oneOf with {type:null} → the other member(s) + nullable: true
 *                                  (a lone $ref becomes allOf: [$ref] — 3.0 ignores $ref siblings)
 *   enum containing null         → enum without null + nullable: true
 *   examples: [x, …]             → example: x
 *   const: x                     → enum: [x]
 *
 * Applied recursively through properties, items, additionalProperties and the
 * combinators. Anything already in 3.0 form passes through unchanged.
 *
 * @internal Used by OpenApiSpec for 3.0 output.
 */
final class SchemaDialect
{
    /**
     * @param array<array-key, mixed> $schema
     *
     * @return array<array-key, mixed>
     */
    public static function toOpenApi30(array $schema): array
    {
        $out = [];
        $nullable = false;

        foreach ($schema as $key => $value) {
            switch ($key) {
                case 'type':
                    if (!is_array($value)) {
                        $out['type'] = $value;
                        break;
                    }

                    $types = array_values(array_filter($value, static fn (mixed $t): bool => $t !== 'null'));
                    $nullable = $nullable || count($types) !== count($value);

                    if (count($types) === 1) {
                        $out['type'] = $types[0];
                    } elseif ($types !== []) {
                        $out['anyOf'] = array_map(static fn (mixed $t): array => ['type' => $t], $types);
                    }

                    break;

                case 'anyOf':
                case 'oneOf':
                    if (!is_array($value)) {
                        $out[$key] = $value;
                        break;
                    }

                    $members = [];

                    foreach ($value as $member) {
                        if ($member === ['type' => 'null']) {
                            $nullable = true;
                        } elseif (is_array($member)) {
                            $members[] = self::toOpenApi30($member);
                        }
                    }

                    if (count($members) === 1 && count($members) !== count($value)) {
                        $out = isset($members[0]['$ref']) ? [...$out, 'allOf' => $members] : [...$out, ...$members[0]];
                    } else {
                        $out[$key] = $members;
                    }

                    break;

                case 'enum':
                    if (is_array($value) && in_array(null, $value, true)) {
                        $nullable = true;
                        $value = array_values(array_filter($value, static fn (mixed $v): bool => $v !== null));
                    }

                    $out['enum'] = $value;

                    break;

                case 'examples':
                    if (is_array($value) && $value !== []) {
                        $out['example'] = reset($value);
                    }

                    break;

                case 'const':
                    $out['enum'] = [$value];

                    break;

                case 'properties':
                    $out['properties'] = is_array($value) ? array_map(
                        static fn (mixed $property): mixed => is_array($property) ? self::toOpenApi30($property) : $property,
                        $value,
                    ) : $value;

                    break;

                case 'items':
                case 'additionalProperties':
                case 'not':
                    $out[$key] = is_array($value) ? self::toOpenApi30($value) : $value;

                    break;

                case 'allOf':
                    $out['allOf'] = is_array($value) ? array_map(
                        static fn (mixed $member): mixed => is_array($member) ? self::toOpenApi30($member) : $member,
                        $value,
                    ) : $value;

                    break;

                default:
                    $out[$key] = $value;
            }

            if ($nullable && !isset($out['nullable']) && in_array($key, ['type', 'anyOf', 'oneOf', 'enum'], true)) {
                $out['nullable'] = true;
            }
        }

        return $out;
    }
}
