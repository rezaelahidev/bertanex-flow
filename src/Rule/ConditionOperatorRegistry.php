<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Rule;

/**
 * Operators are completely data-driven: merge {@see BuiltinConditionOperators} with hooks.
 *
 * Unknown operators fall through {@see 'gro_condition_operator_unknown'}.
 */
final class ConditionOperatorRegistry
{
    /** @var array<string, callable(mixed, array<string, mixed>, array<string, mixed>): bool> */
    private array $handlers;

    public function __construct()
    {
        $builtins = BuiltinConditionOperators::defaultHandlers();

        /** @var array<string, callable(mixed, array<string, mixed>, array<string, mixed>): bool> $handlers */
        $handlers = apply_filters('gro_condition_operator_handlers', $builtins);

        $this->handlers = $handlers;
    }

    /**
     * @param array<string, mixed> $leaf   Single condition leaf (field, op, optional value/pattern …).
     * @param array<string, mixed> $state  Full evaluated user/context state.
     */
    public function evaluate(string $operator, mixed $actual, array $leaf, array $state): bool
    {
        $op = $operator;

        if (isset($this->handlers[$op])) {
            return ($this->handlers[$op])($actual, $leaf, $state);
        }

        /** @phpstan-ignore-next-line */
        $fallback = apply_filters('gro_condition_operator_unknown', null, $op, $actual, $leaf, $state);

        return $fallback === null ? false : (bool) $fallback;
    }
}
