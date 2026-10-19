<?php

declare(strict_types=1);

namespace BertanexFlow\Rule;

/**
 * Recursive condition groups backed by plug-in operators ({@see ConditionOperatorRegistry}).
 *
 * @phpstan-import-type ConditionGroup from RuleDefinition
 */
final class ConditionEvaluator
{
    public function __construct(
        private readonly ConditionOperatorRegistry $registry
    ) {
    }

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
        /**
         * Let extensions short-circuit a single predicate before operator registry runs.
         *
         * @param bool|null                                   $carry
         * @param array<string, mixed>                        $leaf  Raw JSON leaf ({@code field},{@code op},{@code value}…).
         * @param array<string, mixed>                        $state Merged contextual state snapshot.
         */
        $intercept = apply_filters('gro_condition_evaluate_leaf', null, $leaf, $state);
        if (is_bool($intercept)) {
            return $intercept;
        }

        /** @phpstan-ignore-next-line */
        $field = isset($leaf['field']) ? (string) $leaf['field'] : '';

        /** @phpstan-ignore-next-line */
        $resolvedValue = apply_filters(
            'gro_condition_field_value',
            $field !== '' ? ($state[$field] ?? null) : null,
            $field,
            $state,
            $leaf
        );

        /** @phpstan-ignore-next-line */
        $actual = apply_filters(
            'gro_condition_resolve_actual_operand',
            $resolvedValue,
            $field,
            $state,
            $leaf
        );

        /** @phpstan-ignore-next-line */
        $operator = isset($leaf['op']) ? (string) $leaf['op'] : '';

        if ($operator === '') {
            return false;
        }

        return $this->registry->evaluate($operator, $actual, $leaf, $state);
    }
}
