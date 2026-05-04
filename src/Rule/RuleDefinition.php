<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Rule;

/**
 * Normalized rule from JSON ({@see docs/rule-schema.json}).
 *
 * @phpstan-type Trigger array<string, mixed>|null
 * @phpstan-type ConditionGroup array<string, mixed>
 * @phpstan-type ActionRow array{type: string, template_id?: string, params?: array<string, mixed>, delay_seconds?: int}
 */
final readonly class RuleDefinition
{
    /**
     * @param Trigger         $trigger
     * @param ConditionGroup  $conditions
     * @param list<ActionRow> $actions
     */
    public function __construct(
        public string $id,
        public ?string $label,
        public bool $enabled,
        public int $priority,
        public ?array $trigger,
        public array $conditions,
        public array $actions
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        if (! isset($row['id'], $row['conditions'], $row['actions']) || ! is_array($row['conditions']) || ! is_array($row['actions'])) {
            throw new \InvalidArgumentException('Rule JSON must include id, conditions, and actions arrays.');
        }

        $trigger = isset($row['trigger']) && is_array($row['trigger']) ? $row['trigger'] : null;

        /** @var list<ActionRow> $actions */
        $actions = array_values($row['actions']);

        return new self(
            id: (string) $row['id'],
            label: isset($row['label']) ? (string) $row['label'] : null,
            enabled: array_key_exists('enabled', $row) ? (bool) $row['enabled'] : true,
            priority: isset($row['priority']) ? (int) $row['priority'] : 10,
            trigger: $trigger,
            conditions: $row['conditions'],
            actions: $actions
        );
    }

    /**
     * Canonical shape for persistence / editor export (DB or JSON mirrors).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        /** @phpstan-var list<ActionRow> $ordered */
        $ordered = array_values($this->actions);

        $out = [
            'id'         => $this->id,
            'enabled'    => $this->enabled,
            'priority'   => $this->priority,
            'conditions' => $this->conditions,
            'actions'    => $ordered,
        ];

        if ($this->label !== null && $this->label !== '') {
            $out['label'] = $this->label;
        }

        if ($this->trigger !== null) {
            $out['trigger'] = $this->trigger;
        }

        return $out;
    }
}
