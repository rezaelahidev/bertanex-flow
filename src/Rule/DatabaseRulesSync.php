<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Rule;

use GrowthOrchestrator\Db\RulesDbSchema;

/**
 * Persists normalized {@see RuleDefinition} rows into `{prefix}growth_orchestrator_rules`.
 */
final class DatabaseRulesSync
{
    /**
     * Replace all rows with the supplied definitions (validated elsewhere).
     *
     * @param list<RuleDefinition> $definitions
     */
    public static function persistDefinitions(\wpdb $wpdb, array $definitions): bool
    {
        $table = RulesDbSchema::tableName();

        /** @phpstan-ignore-next-line */
        $wpdb->query("DELETE FROM {$table}");

        if ($wpdb->last_error !== '') {
            return false;
        }

        /** @phpstan-ignore-next-line */
        $nowGmt = current_time('mysql', true);

        foreach ($definitions as $def) {
            $slug = substr($def->id, 0, 190);
            if ($slug === '') {
                continue;
            }

            $data = [
                'rule_slug'       => $slug,
                'label'           => (string) ($def->label ?? ''),
                'priority'        => $def->priority,
                'enabled'         => $def->enabled ? 1 : 0,
                'conditions_json' => wp_json_encode($def->conditions) ?: '{}',
                'actions_json'    => wp_json_encode(array_values($def->actions)) ?: '[]',
                'created_gmt'     => $nowGmt,
                'updated_gmt'     => $nowGmt,
            ];

            if ($def->trigger !== null) {
                $enc = wp_json_encode($def->trigger);
                if ($enc !== false && $enc !== '') {
                    $data['trigger_json'] = $enc;
                }
            }

            /** @phpstan-ignore-next-line */
            $formats = [];
            foreach (array_keys($data) as $key) {
                $formats[] = $key === 'priority' || $key === 'enabled' ? '%d' : '%s';
            }

            /** @phpstan-ignore-next-line */
            $wpdb->insert($table, $data, $formats);

            if ($wpdb->last_error !== '') {
                return false;
            }
        }

        return true;
    }
}
