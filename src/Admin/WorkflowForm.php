<?php

declare(strict_types=1);

namespace BertanexFlow\Admin;

use BertanexFlow\Core\Workflow\WorkflowDefinition;
use InvalidArgumentException;

/**
 * Converts the workflow admin form into a version 1 document.
 */
final class WorkflowForm
{
    /** @var list<string> */
    public const OPERATORS = ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'contains', 'empty', 'not_empty'];

    /** @var list<string> */
    public const ACTIONS = ['log.message', 'data.map', 'webhook.request'];

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function toDocument(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $id = trim((string) ($input['id'] ?? ''));
        if ($id === '') {
            $id = trim((string) preg_replace('/[^a-z0-9_-]+/i', '-', strtolower($name)), '-');
        }

        return [
            'version' => 1,
            'id' => $id,
            'name' => $name,
            'description' => trim((string) ($input['description'] ?? '')),
            'status' => (($input['status'] ?? '') === 'active') ? 'active' : 'draft',
            'trigger' => [
                'type' => trim((string) ($input['trigger'] ?? '')),
                'config' => [],
            ],
            'conditions' => self::conditions(is_array($input['conditions'] ?? null) ? $input['conditions'] : []),
            'actions' => self::actions(is_array($input['actions'] ?? null) ? $input['actions'] : []),
            'metadata' => [
                'retry' => [
                    'max_attempts' => max(1, (int) ($input['retry_max_attempts'] ?? 5)),
                    'strategy' => (($input['retry_strategy'] ?? '') === 'fixed') ? 'fixed' : 'exponential_backoff',
                    'initial_delay' => max(1, (int) ($input['retry_initial_delay'] ?? 60)),
                    'max_delay' => max(1, (int) ($input['retry_max_delay'] ?? 3600)),
                ],
                'dedupe_window' => max(0, (int) ($input['dedupe_window'] ?? 0)),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromWorkflow(WorkflowDefinition $workflow): array
    {
        $metadata = $workflow->getMetadata();
        $retry = is_array($metadata['retry'] ?? null) ? $metadata['retry'] : [];
        $trigger = $workflow->getTriggers()[0]['type'] ?? '';
        $actions = [];

        foreach ($workflow->getActions() as $action) {
            $config = is_array($action['config'] ?? null) ? $action['config'] : [];
            $body = $config['body'] ?? [];
            $actions[] = [
                'type' => (string) ($action['type'] ?? ''),
                'message' => (string) ($config['message'] ?? ''),
                'method' => (string) ($config['method'] ?? 'POST'),
                'url' => (string) ($config['url'] ?? ''),
                'body' => $body === [] ? '' : (string) json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                'into' => (string) ($config['into'] ?? 'mapped'),
                'mappings' => is_array($config['mappings'] ?? null) ? $config['mappings'] : [],
            ];
        }

        return [
            'id' => $workflow->getId(),
            'name' => $workflow->getName(),
            'description' => $workflow->getDescription(),
            'status' => $workflow->getStatus(),
            'trigger' => $trigger,
            'conditions' => $workflow->getConditions(),
            'actions' => $actions,
            'retry_max_attempts' => (int) ($retry['max_attempts'] ?? 5),
            'retry_strategy' => (string) ($retry['strategy'] ?? 'exponential_backoff'),
            'retry_initial_delay' => (int) ($retry['initial_delay'] ?? 60),
            'retry_max_delay' => (int) ($retry['max_delay'] ?? 3600),
            'dedupe_window' => (int) ($metadata['dedupe_window'] ?? 0),
        ];
    }

    /**
     * @param array<mixed> $rows
     * @return list<array{field: string, operator: string, value: mixed}>
     */
    private static function conditions(array $rows): array
    {
        $conditions = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $field = trim((string) ($row['field'] ?? ''));
            if ($field === '') {
                continue;
            }

            $operator = (string) ($row['operator'] ?? 'eq');
            if (! in_array($operator, self::OPERATORS, true)) {
                $operator = 'eq';
            }

            $value = $row['value'] ?? '';
            if (is_string($value) && is_numeric($value)) {
                $value = str_contains($value, '.') ? (float) $value : (int) $value;
            }

            $conditions[] = [
                'field' => $field,
                'operator' => $operator,
                'value' => $value,
            ];
        }

        return $conditions;
    }

    /**
     * @param array<mixed> $rows
     * @return list<array{type: string, config: array<string, mixed>}>
     */
    private static function actions(array $rows): array
    {
        $actions = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $type = (string) ($row['type'] ?? '');
            if (! in_array($type, self::ACTIONS, true)) {
                continue;
            }

            $actions[] = [
                'type' => $type,
                'config' => self::config($type, $row),
            ];
        }

        return $actions;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function config(string $type, array $row): array
    {
        return match ($type) {
            'log.message' => [
                'message' => (string) ($row['message'] ?? ''),
            ],
            'webhook.request' => [
                'method' => strtoupper((string) ($row['method'] ?? 'POST')),
                'url' => trim((string) ($row['url'] ?? '')),
                'body' => self::body((string) ($row['body'] ?? '')),
            ],
            default => [
                'into' => trim((string) ($row['into'] ?? 'mapped')) ?: 'mapped',
                'mappings' => self::mappings(is_array($row['mappings'] ?? null) ? $row['mappings'] : []),
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function body(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new InvalidArgumentException('HTTP body must be a JSON object.');
        }

        return $decoded;
    }

    /**
     * @param array<mixed> $rows
     * @return list<array{source: string, target: string, transform?: string}>
     */
    private static function mappings(array $rows): array
    {
        $mappings = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $source = trim((string) ($row['source'] ?? ''));
            $target = trim((string) ($row['target'] ?? ''));
            if ($source === '' || $target === '') {
                continue;
            }

            $mapping = [
                'source' => $source,
                'target' => $target,
            ];
            $transform = trim((string) ($row['transform'] ?? ''));
            if ($transform !== '') {
                $mapping['transform'] = $transform;
            }
            $mappings[] = $mapping;
        }

        return $mappings;
    }
}
