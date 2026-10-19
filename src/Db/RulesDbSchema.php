<?php

declare(strict_types=1);

namespace BertanexFlow\Db;

use BertanexFlow\Rule\DatabaseRulesSync;
use BertanexFlow\Rule\JsonRulesParser;
use BertanexFlow\Rule\OptionRulesRepository;

/**
 * DDL and one-time hydration for `{prefix}bertanex_flow_rules`.
 */
final class RulesDbSchema
{
    public const SCHEMA_VERSION_OPTION = 'bertanex_flow_rules_schema_version';

    /** Bump when altering DDL so dbDelta detects schema changes reliably. */
    public const SCHEMA_VERSION = '2026-02-06-3';

    private static ?bool $tableReadyCache = null;

    public static function tableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'bertanex_flow_rules';
    }

    public static function tableReady(): bool
    {
        if (self::$tableReadyCache !== null) {
            return self::$tableReadyCache;
        }

        // Schema version is autoloaded; matching it means dbDelta already created the table.
        // Avoid SHOW TABLES LIKE on the hot path — it scans information_schema and is a frequent slow query.
        if ((string) get_option(self::SCHEMA_VERSION_OPTION, '') === self::SCHEMA_VERSION) {
            self::$tableReadyCache = true;

            return true;
        }

        global $wpdb;

        $table = self::tableName();

        /** @phpstan-ignore-next-line */
        $found = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))
        );

        self::$tableReadyCache = $found === $table;

        return self::$tableReadyCache;
    }

    /**
     * Idempotent install / upgrade ({@see dbDelta()}).
     */
    public static function upgradeIfNeeded(): void
    {
        if ((string) get_option(self::SCHEMA_VERSION_OPTION, '') === self::SCHEMA_VERSION) {
            return;
        }

        self::installOrUpgradeTables();

        if (! self::tableReady()) {
            return;
        }

        update_option(self::SCHEMA_VERSION_OPTION, self::SCHEMA_VERSION, true);
    }

    public static function installOrUpgradeTables(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $table   = self::tableName();

        $sql = <<<SQL
CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  rule_slug varchar(190) NOT NULL,
  label varchar(255) NOT NULL DEFAULT '',
  priority int(11) NOT NULL DEFAULT 10,
  enabled tinyint(1) NOT NULL DEFAULT 1,
  trigger_json longtext NULL,
  conditions_json longtext NOT NULL,
  actions_json longtext NOT NULL,
  created_gmt datetime NULL,
  updated_gmt datetime NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY rule_slug (rule_slug),
  KEY enabled_priority (enabled, priority),
  KEY priority_slug (priority, rule_slug)
) {$charset};
SQL;

        dbDelta($sql);
        self::$tableReadyCache = null;
    }

    public const SOURCES_HYDRATED_OPTION = 'bertanex_flow_rules_sources_hydrated_v1';

    /**
     * One-time copy from legacy option JSON or consumer defaults when the table is still empty.
     *
     * After it runs (even if nothing was imported), {@see SOURCES_HYDRATED_OPTION} prevents accidental
     * re-import when an admin intentionally deletes all rows.
     */
    public static function maybeHydrateFromLegacySourcesOnce(string $defaultRulesDocumentJson): void
    {
        if (get_option(self::SOURCES_HYDRATED_OPTION)) {
            return;
        }

        if (! self::tableReady()) {
            return;
        }

        global $wpdb;

        $table = self::tableName();
        /** @phpstan-ignore-next-line */
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        if ($count > 0) {
            update_option(self::SOURCES_HYDRATED_OPTION, '1', true);

            return;
        }

        if (get_option(self::SOURCES_HYDRATED_OPTION)) {
            return;
        }

        $parser = new JsonRulesParser();

        /** @phpstan-ignore-next-line */
        $rawOption = get_option((string) apply_filters('gro_rules_option_name', OptionRulesRepository::DEFAULT_OPTION_NAME), '');

        if (is_string($rawOption) && $rawOption !== '') {
            try {
                $definitions = $parser->parse($rawOption);
                if ($definitions !== []) {
                    DatabaseRulesSync::persistDefinitions($wpdb, $definitions);
                }
            } catch (\Throwable) {
                /* fall through to file */
            }
        }

        /** @phpstan-ignore-next-line */
        $countAfterOption = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        if ($countAfterOption === 0) {
            $json = trim($defaultRulesDocumentJson);
            if ($json !== '') {
                try {
                    $definitions = $parser->parse($json);
                    if ($definitions !== []) {
                        DatabaseRulesSync::persistDefinitions($wpdb, $definitions);
                    }
                } catch (\Throwable) {
                    /* nothing */
                }
            }
        }

        update_option(self::SOURCES_HYDRATED_OPTION, '1', true);
    }

    /** Plugin activation shim (delegates upgrade + hydration). */
    public static function onPluginActivate(string $defaultRulesDocumentJson): void
    {
        self::installOrUpgradeTables();
        if (self::tableReady()) {
            update_option(self::SCHEMA_VERSION_OPTION, self::SCHEMA_VERSION, true);
        }

        self::maybeHydrateFromLegacySourcesOnce($defaultRulesDocumentJson);
    }
}
