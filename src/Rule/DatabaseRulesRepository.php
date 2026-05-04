<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Rule;

use GrowthOrchestrator\Db\RulesDbSchema;

/**
 * Canonical runtime source when `{prefix}growth_orchestrator_rules` has rows.
 */
final class DatabaseRulesRepository implements RulesRepositoryInterface
{
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
        if (! RulesDbSchema::tableReady()) {
            return 0;
        }

        $table = RulesDbSchema::tableName();

        /** @phpstan-ignore-next-line */
        return max(0, (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$table}"));
    }

    /**
     * @return list<RuleDefinition>
     */
    public function getRules(): array
    {
        if (! RulesDbSchema::tableReady()) {
            return [];
        }

        $table = RulesDbSchema::tableName();

        /** @phpstan-ignore-next-line */
        $rows = $this->wpdb->get_results(
            "SELECT rule_slug AS id, label, priority, enabled, trigger_json, conditions_json, actions_json
             FROM {$table}
             ORDER BY priority ASC, rule_slug ASC",
            ARRAY_A
        );

        if (! is_array($rows) || $rows === []) {
            return [];
        }

        $definitions = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['id'], $row['conditions_json'], $row['actions_json'])) {
                continue;
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
                continue;
            }

            if (! is_array($decoded)) {
                continue;
            }

            $assembled['conditions'] = $decoded;

            $actRaw = isset($row['actions_json']) && is_string($row['actions_json']) ? $row['actions_json'] : '';

            try {
                /** @phpstan-ignore-next-line */
                $actDecoded = json_decode($actRaw === '' ? '[]' : $actRaw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                continue;
            }

            if (! is_array($actDecoded)) {
                continue;
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

            try {
                $definitions[] = RuleDefinition::fromArray($assembled);
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
        return apply_filters('gro_parsed_rules', $definitions, 'database:' . $table);
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
}
