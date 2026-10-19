<?php

declare(strict_types=1);

namespace BertanexFlow\Rule;

/**
 * Default comparison operators merged with {@see 'gro_condition_operator_handlers'}.
 *
 * Signature: (mixed $actual, array $leaf, array $state): bool.
 */
final class BuiltinConditionOperators
{
    /** @phpstan-return array<string, callable(mixed $actual, array<string, mixed>, array<string, mixed>): bool> */
    public static function defaultHandlers(): array
    {
        return [
            'eq'        => static fn ($actual, $leaf): bool => $actual == ($leaf['value'] ?? null),
            'neq'       => static fn ($actual, $leaf): bool => $actual != ($leaf['value'] ?? null),
            'gt'        => static fn ($actual, $leaf): bool => self::numeric($actual) > self::numeric($leaf['value'] ?? null),
            'gte'       => static fn ($actual, $leaf): bool => self::numeric($actual) >= self::numeric($leaf['value'] ?? null),
            'lt'        => static fn ($actual, $leaf): bool => self::numeric($actual) < self::numeric($leaf['value'] ?? null),
            'lte'       => static fn ($actual, $leaf): bool => self::numeric($actual) <= self::numeric($leaf['value'] ?? null),
            'in'        => static fn ($actual, $leaf): bool => in_array($actual, (array) ($leaf['value'] ?? []), true),
            'not_in'    => static fn ($actual, $leaf): bool => ! in_array($actual, (array) ($leaf['value'] ?? []), true),
            'empty'     => static fn ($actual): bool => self::isEmpty($actual),
            'not_empty' => static fn ($actual): bool => ! self::isEmpty($actual),
            'truthy'    => static fn ($actual): bool => (bool) $actual,
            'falsy'     => static fn ($actual): bool => ! (bool) $actual,
            'regex'     => static function ($actual, $leaf): bool {
                if (! is_string($actual)) {
                    return false;
                }
                $pattern = isset($leaf['pattern']) ? (string) $leaf['pattern'] : '';
                if ($pattern === '') {
                    return false;
                }

                $result = preg_match($pattern, $actual);

                return $result !== false && $result === 1;
            },
            'starts_with' => static function ($actual, $leaf): bool {
                $prefix = isset($leaf['value']) ? (string) $leaf['value'] : '';

                return is_string($actual) && $prefix !== '' && str_starts_with($actual, $prefix);
            },
            'ends_with' => static function ($actual, $leaf): bool {
                $suffix = isset($leaf['value']) ? (string) $leaf['value'] : '';

                return is_string($actual) && $suffix !== '' && str_ends_with($actual, $suffix);
            },
            'contains' => static function ($actual, $leaf): bool {
                $needle = isset($leaf['value']) ? (string) $leaf['value'] : '';

                return is_string($actual) && $needle !== '' && str_contains($actual, $needle);
            },
        ];
    }

    private static function numeric(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        return 0.0;
    }

    private static function isEmpty(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return $value === [];
    }
}
