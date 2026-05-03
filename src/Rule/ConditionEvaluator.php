<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Rule;

/**
 * Evaluates condition groups (all / any / not) from JSON rules against runtime user state.
 *
 * @phpstan-import-type ConditionGroup from RuleDefinition
 */
final class ConditionEvaluator
{
    /**
     * @param ConditionGroup       $group
     * @param array<string, mixed> $state
     */
    public function evaluate(array $group, array $state): bool
    {
        return $this->evaluateGroup($group, $state);
    }

    /**
     * @param array<string, mixed> $group
     * @param array<string, mixed> $state
     */
    private function evaluateGroup(array $group, array $state): bool
    {
        if (isset($group['all'])) {
            $items = $group['all'];
            if (! is_array($items)) {
                return false;
            }

            foreach ($items as $item) {
                if (! is_array($item)) {
                    return false;
                }
                if (! $this->evaluateBranch($item, $state)) {
                    return false;
                }
            }

            return true;
        }

        if (isset($group['any'])) {
            $items = $group['any'];
            if (! is_array($items) || $items === []) {
                return false;
            }

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                if ($this->evaluateBranch($item, $state)) {
                    return true;
                }
            }

            return false;
        }

        if (isset($group['not'])) {
            $inner = $group['not'];
            if (! is_array($inner)) {
                return false;
            }

            return ! $this->evaluateGroup($inner, $state);
        }

        return false;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, mixed> $state
     */
    private function evaluateBranch(array $item, array $state): bool
    {
        if ($this->isLeaf($item)) {
            return $this->evaluateLeaf($item, $state);
        }

        return $this->evaluateGroup($item, $state);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function isLeaf(array $item): bool
    {
        return array_key_exists('field', $item);
    }

    /**
     * @param array<string, mixed> $leaf
     * @param array<string, mixed> $state
     */
    private function evaluateLeaf(array $leaf, array $state): bool
    {
        $field = (string) $leaf['field'];
        $op    = (string) $leaf['op'];
        $actual = $state[$field] ?? null;

        return match ($op) {
            'eq'       => $actual == ($leaf['value'] ?? null),
            'neq'      => $actual != ($leaf['value'] ?? null),
            'gt'       => $this->numeric($actual) > $this->numeric($leaf['value'] ?? null),
            'gte'      => $this->numeric($actual) >= $this->numeric($leaf['value'] ?? null),
            'lt'       => $this->numeric($actual) < $this->numeric($leaf['value'] ?? null),
            'lte'      => $this->numeric($actual) <= $this->numeric($leaf['value'] ?? null),
            'in'       => in_array($actual, (array) ($leaf['value'] ?? []), true),
            'not_in'   => ! in_array($actual, (array) ($leaf['value'] ?? []), true),
            'empty'    => $this->isEmpty($actual),
            'not_empty'=> ! $this->isEmpty($actual),
            'truthy'   => (bool) $actual,
            'falsy'    => ! (bool) $actual,
            default    => false,
        };
    }

    private function numeric(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        return 0.0;
    }

    private function isEmpty(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return $value === [];
    }
}
