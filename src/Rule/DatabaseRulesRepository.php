<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Rule;

use GrowthOrchestrator\Db\RulesDbSchema;

/**
 * Canonical runtime source when `{prefix}growth_orchestrator_rules` has rows.
 */
final class DatabaseRulesRepository implements RulesRepositoryInterface
{
    public const COMPILED_OPTION = 'growth_orchestrator_rules_compiled_v1';

    private const OBJECT_CACHE_GROUP = 'growth_orchestrator';

    private const OBJECT_CACHE_KEY = 'rules_compiled_v1';

    /** @var list<RuleDefinition>|null */
    private ?array $rulesCache = null;

    private ?int $ruleCountCache = null;

    public function __construct(
        /** @phpstan-ignore-next-line */
        private readonly \wpdb $wpdb
    ) {
    }

    /**
     * True when DDL exists and contains at least one rule row (enabled or disabled).
     */
    public function hasPersistedRules(): bool
    {
        if (! RulesDbSchema::tableReady()) {
            return false;
        }

        return $this->ruleCountCached() > 0;
    }

    public function ruleCountCached(): int
    {
        if ($this->ruleCountCache !== null) {
            return $this->ruleCountCache;
        }

        $this->hydrateFromSnapshotOrDatabase();

        return $this->ruleCountCache ?? 0;
    }

    public function flushCache(): void
    {
        $this->rulesCache = null;
        $this->ruleCountCache = null;
        wp_cache_delete(self::OBJECT_CACHE_KEY, self::OBJECT_CACHE_GROUP);
    }

    /**
     * @return list<RuleDefinition>
     */
    public function getRules(): array
    {
        $this->hydrateFromSnapshotOrDatabase();

        return $this->rulesCache ?? [];
    }

    /**
     * Persist a compiled snapshot so later requests skip the rules table.
     *
     * @param list<RuleDefinition> $definitions
     */
    public static function storeCompiledSnapshot(array $definitions): void
    {
        $payload = [];

        foreach ($definitions as $def) {
            if (! $def instanceof RuleDefinition) {
                continue;
            }

            $payload[] = $def->toArray();
        }

        self::writeCompiledSnapshot(count($payload), $payload);
    }

    /** Pretty JSON `{ version, rules:[…] }` for editor textareas. */
    public function exportPrettyDocumentJson(string $schemaVersion = '1.0.0'): string
    {
        $rulesArr = [];

        foreach ($this->getRules() as $def) {
            $rulesArr[] = $def->toArray();
        }

        $doc = [
            'version' => $schemaVersion,
            'rules'   => $rulesArr,
        ];

        /** @phpstan-ignore-next-line */
        $pretty = wp_json_encode($doc, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return $pretty !== false ? $pretty : "{\"version\":\"{$schemaVersion}\",\"rules\":[]}";
    }

    private function hydrateFromSnapshotOrDatabase(): void
    {
        if ($this->rulesCache !== null && $this->ruleCountCache !== null) {
            return;
        }

        if (! RulesDbSchema::tableReady()) {
            $this->rulesCache = [];
            $this->ruleCountCache = 0;

            return;
        }

        $snapshot = $this->readCompiledSnapshot();

        if ($snapshot !== null) {
            $this->installHydratedState(
                $snapshot['row_count'],
                $this->definitionsFromAssembled($snapshot['rules'])
            );

            return;
        }

        $table = RulesDbSchema::tableName();

        /** @phpstan-ignore-next-line */
        $rows = $this->wpdb->get_results(
            "SELECT rule_slug AS id, label, priority, enabled, trigger_json, conditions_json, actions_json
             FROM {$table}
             ORDER BY priority ASC, rule_slug ASC",
            ARRAY_A
        );

        if (! is_array($rows)) {
            $rows = [];
        }

        $assembled = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $mapped = $this->assembledFromDatabaseRow($row);
            if ($mapped === null) {
                continue;
            }

            $assembled[] = $mapped;
        }

        $definitions = $this->definitionsFromAssembled($assembled);

        self::writeCompiledSnapshot(count($rows), $assembled);
        $this->installHydratedState(count($rows), $definitions);
    }

    /**
     * @param list<array<string, mixed>> $assembled
     * @return list<RuleDefinition>
     */
    private function definitionsFromAssembled(array $assembled): array
    {
        $definitions = [];

        foreach ($assembled as $row) {
            try {
                $definitions[] = RuleDefinition::fromArray($row);
            } catch (\Throwable) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(
                        '[Growth Orchestrator] Skipped invalid persisted rule definition: '
                        . (string) ($row['id'] ?? '')
                    );
                }
            }
        }

        /** @var list<RuleDefinition> */
        return apply_filters(
            'gro_parsed_rules',
            $definitions,
            'database:' . RulesDbSchema::tableName()
        );
    }

    /**
     * @param list<RuleDefinition> $definitions
     */
    private function installHydratedState(int $rowCount, array $definitions): void
    {
        $this->ruleCountCache = max(0, $rowCount);
        $this->rulesCache = $definitions;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    private function assembledFromDatabaseRow(array $row): ?array
    {
        if (! isset($row['id'], $row['conditions_json'], $row['actions_json'])) {
            return null;
        }

        $assembled = [
            'id'         => (string) $row['id'],
            'label'      => isset($row['label']) ? (string) $row['label'] : '',
            'enabled'    => ! isset($row['enabled']) || (int) $row['enabled'] === 1,
            'priority'   => isset($row['priority']) ? (int) $row['priority'] : 10,
            'conditions' => [],
            'actions'    => [],
            'trigger'    => null,
        ];

        $condRaw = isset($row['conditions_json']) && is_string($row['conditions_json']) ? $row['conditions_json'] : '';

        try {
            /** @phpstan-ignore-next-line */
            $decoded = json_decode($condRaw === '' ? '{}' : $condRaw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        $assembled['conditions'] = $decoded;

        $actRaw = isset($row['actions_json']) && is_string($row['actions_json']) ? $row['actions_json'] : '';

        try {
            /** @phpstan-ignore-next-line */
            $actDecoded = json_decode($actRaw === '' ? '[]' : $actRaw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($actDecoded)) {
            return null;
        }

        $assembled['actions'] = $actDecoded;

        $tj = isset($row['trigger_json']) && is_string($row['trigger_json']) ? trim($row['trigger_json']) : '';

        if ($tj !== '') {
            try {
                /** @phpstan-ignore-next-line */
                $tDecoded = json_decode($tj, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                $tDecoded = null;
            }
            $assembled['trigger'] = is_array($tDecoded) ? $tDecoded : null;
        }

        if ($assembled['label'] === '') {
            unset($assembled['label']);
        }

        return $assembled;
    }

    /**
     * @return array{row_count: int, rules: list<array<string, mixed>>}|null
     */
    private function readCompiledSnapshot(): ?array
    {
        $found = false;
        $cached = wp_cache_get(self::OBJECT_CACHE_KEY, self::OBJECT_CACHE_GROUP, false, $found);

        if ($found && is_array($cached)) {
            $normalized = self::normalizeCompiledSnapshot($cached);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        $option = get_option(self::COMPILED_OPTION, null);
        if (! is_array($option)) {
            return null;
        }

        $normalized = self::normalizeCompiledSnapshot($option);
        if ($normalized === null) {
            return null;
        }

        wp_cache_set(self::OBJECT_CACHE_KEY, $normalized, self::OBJECT_CACHE_GROUP);

        return $normalized;
    }

    /**
     * @param list<array<string, mixed>> $rules
     */
    private static function writeCompiledSnapshot(int $rowCount, array $rules): void
    {
        $snapshot = [
            'row_count' => max(0, $rowCount),
            'rules'     => array_values($rules),
        ];

        update_option(self::COMPILED_OPTION, $snapshot, true);
        wp_cache_set(self::OBJECT_CACHE_KEY, $snapshot, self::OBJECT_CACHE_GROUP);
    }

    /**
     * @param mixed $raw
     * @return array{row_count: int, rules: list<array<string, mixed>>}|null
     */
    private static function normalizeCompiledSnapshot(mixed $raw): ?array
    {
        if (! is_array($raw) || ! isset($raw['row_count'], $raw['rules']) || ! is_array($raw['rules'])) {
            return null;
        }

        $rules = [];

        foreach ($raw['rules'] as $row) {
            if (is_array($row)) {
                $rules[] = $row;
            }
        }

        return [
            'row_count' => max(0, (int) $raw['row_count']),
            'rules'     => $rules,
        ];
    }
}
