<?php

declare(strict_types=1);

namespace BertanexFlow\Core\Workflow;

use BertanexFlow\Core\Contracts\WorkflowInterface;
use InvalidArgumentException;

/**
 * Versioned declarative workflow document.
 */
final class WorkflowDefinition implements WorkflowInterface
{
    /**
     * @param list<array{type: string, config: array<string, mixed>}> $triggers
     * @param list<array{field: string, operator: string, value: mixed}> $conditions
     * @param list<array{type: string, config: array<string, mixed>}> $actions
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        private readonly int $version,
        private readonly string $id,
        private readonly string $name,
        private readonly string $description,
        private readonly array $triggers,
        private readonly array $conditions,
        private readonly array $actions,
        private readonly array $metadata = [],
        private readonly string $status = 'draft'
    ) {
    }

    /**
     * @param array<string, mixed> $document
     */
    public static function fromArray(array $document): self
    {
        $trigger = $document['trigger'] ?? null;
        $triggers = $document['triggers'] ?? null;

        if (! is_array($triggers)) {
            $triggers = is_array($trigger) ? [$trigger] : [];
        }

        $workflow = new self(
            (int) ($document['version'] ?? 1),
            self::text($document['id'] ?? ''),
            self::text($document['name'] ?? ''),
            self::text($document['description'] ?? ''),
            self::normalizeNodes($triggers),
            self::normalizeConditions(is_array($document['conditions'] ?? null) ? $document['conditions'] : []),
            self::normalizeNodes(is_array($document['actions'] ?? null) ? $document['actions'] : []),
            is_array($document['metadata'] ?? null) ? $document['metadata'] : [],
            self::text($document['status'] ?? 'draft')
        );

        $errors = (new WorkflowValidator())->validate($workflow);
        foreach ($errors as $error) {
            if ($error['level'] === 'error') {
                throw new InvalidArgumentException($error['message']);
            }
        }

        return $workflow;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getTriggers(): array
    {
        return $this->triggers;
    }

    public function getConditions(): array
    {
        return $this->conditions;
    }

    public function getActions(): array
    {
        return $this->actions;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function validate(): bool
    {
        return (new WorkflowValidator())->isValid($this);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'version' => $this->version,
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'trigger' => $this->triggers[0] ?? null,
            'triggers' => $this->triggers,
            'conditions' => $this->conditions,
            'actions' => $this->actions,
            'metadata' => $this->metadata,
        ];
    }

    private static function text(mixed $value): string
    {
        $text = is_scalar($value) ? trim((string) $value) : '';

        return function_exists('sanitize_text_field') ? sanitize_text_field($text) : $text;
    }

    /**
     * @param array<mixed> $nodes
     * @return list<array{type: string, config: array<string, mixed>}>
     */
    private static function normalizeNodes(array $nodes): array
    {
        $normalized = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $normalized[] = [
                'type' => self::text($node['type'] ?? ''),
                'config' => is_array($node['config'] ?? null) ? $node['config'] : [],
            ];
        }

        return $normalized;
    }

    /**
     * @param array<mixed> $conditions
     * @return list<array{field: string, operator: string, value: mixed}>
     */
    private static function normalizeConditions(array $conditions): array
    {
        $normalized = [];

        foreach ($conditions as $condition) {
            if (! is_array($condition)) {
                continue;
            }

            $normalized[] = [
                'field' => self::text($condition['field'] ?? ''),
                'operator' => self::text($condition['operator'] ?? 'eq'),
                'value' => $condition['value'] ?? null,
            ];
        }

        return $normalized;
    }
}
