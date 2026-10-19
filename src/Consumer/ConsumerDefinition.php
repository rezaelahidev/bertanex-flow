<?php

declare(strict_types=1);

namespace BertanexFlow\Consumer;

/**
 * Declares domain defaults supplied by an integrated consumer (theme, plugin, etc.).
 *
 * @phpstan-type EventChoice array{value: string, label: string}
 * @phpstan-type ConditionFieldDefinition array{
 *     name: string,
 *     label: string,
 *     valueType?: string,
 *     values?: list<array{value: bool|int|float|string, label: string}>
 * }
 * @phpstan-type SmsPatternRow array{id: string, label: string, defaultBody: string, builtin?: bool}
 */
final class ConsumerDefinition
{
    /**
     * @param list<EventChoice> $events
     * @param array<string, ConditionFieldDefinition> $conditionFieldDefinitions
     * @param list<string|ConditionFieldDefinition> $suggestedConditionFields
     */
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly ?string $defaultRulesJsonPath = null,
        public readonly ?string $defaultSmsPatternsJsonPath = null,
        public readonly array $events = [],
        public readonly array $conditionFieldDefinitions = [],
        public readonly array $suggestedConditionFields = [],
        public readonly int $priority = 10,
        public readonly ?string $textDomain = null
    ) {
    }
}
