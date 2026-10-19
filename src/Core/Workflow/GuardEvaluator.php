<?php

declare(strict_types=1);

namespace BertanexFlow\Core\Workflow;

use BertanexFlow\Core\Data\DataContext;

/**
 * Evaluates workflow conditions against a data context.
 */
final class GuardEvaluator
{
    /**
     * @param list<array{field: string, operator: string, value: mixed}> $conditions
     */
    public function passes(DataContext $context, array $conditions): bool
    {
        foreach ($conditions as $condition) {
            $actual = $context->resolve((string) ($condition['field'] ?? ''), null);
            $expected = $condition['value'] ?? null;
            $operator = (string) ($condition['operator'] ?? 'eq');

            if (! $this->compare($actual, $operator, $expected)) {
                return false;
            }
        }

        return true;
    }

    private function compare(mixed $actual, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            'neq', '!=' => $actual != $expected,
            'gt', '>' => is_numeric($actual) && is_numeric($expected) && (float) $actual > (float) $expected,
            'gte', '>=' => is_numeric($actual) && is_numeric($expected) && (float) $actual >= (float) $expected,
            'lt', '<' => is_numeric($actual) && is_numeric($expected) && (float) $actual < (float) $expected,
            'lte', '<=' => is_numeric($actual) && is_numeric($expected) && (float) $actual <= (float) $expected,
            'contains' => is_string($actual) && is_scalar($expected) && str_contains($actual, (string) $expected),
            'empty' => $actual === null || $actual === '' || $actual === [],
            'not_empty' => ! ($actual === null || $actual === '' || $actual === []),
            default => $actual == $expected,
        };
    }
}
