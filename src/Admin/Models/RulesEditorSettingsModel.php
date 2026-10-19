<?php

declare(strict_types=1);

namespace BertanexFlow\Admin\Models;

/**
 * Visual rules editor panel options (Settings API), exposed on the Rules → Config tab.
 */
final class RulesEditorSettingsModel
{
    public const SETTINGS_GROUP = RulesAdminConfig::SETTINGS_GROUP;

    public const OPTION_MAX_CONDITIONS = 'bertanex_flow_rules_max_conditions';

    public const OPTION_MAX_ACTIONS = 'bertanex_flow_rules_max_actions';

    public const OPTION_DISPLAY_COLUMNS = 'bertanex_flow_rules_display_columns';

    public const DEFAULT_MAX_CONDITIONS = 1;

    public const DEFAULT_MAX_ACTIONS = 1;

    public const DEFAULT_DISPLAY_COLUMNS = 1;

    private const MAX_LIMIT_CAP = 50;

    public function registerSettings(): void
    {
    }

    public function renderMaxConditionsField(): void
    {
        $this->renderNumberField(self::OPTION_MAX_CONDITIONS, self::maxConditions());
    }

    public function renderMaxActionsField(): void
    {
        $this->renderNumberField(self::OPTION_MAX_ACTIONS, self::maxActions());
    }

    public function renderDisplayColumnsField(): void
    {
        $v = self::displayColumns();
        ?>
        <select
            name="<?php echo esc_attr(self::OPTION_DISPLAY_COLUMNS); ?>"
            id="<?php echo esc_attr(self::OPTION_DISPLAY_COLUMNS); ?>"
        >
            <option value="1" <?php selected($v, 1); ?>><?php echo esc_html__('1 column', 'bertanex-flow'); ?></option>
            <option value="2" <?php selected($v, 2); ?>><?php echo esc_html__('2 columns', 'bertanex-flow'); ?></option>
        </select>
        <p class="description">
            <?php echo esc_html__('Applies to the rules list and condition/action lists inside each rule.', 'bertanex-flow'); ?>
        </p>
        <?php
    }

    private function renderNumberField(string $option, int $value): void
    {
        ?>
        <input
            type="number"
            class="small-text"
            name="<?php echo esc_attr($option); ?>"
            id="<?php echo esc_attr($option); ?>"
            value="<?php echo esc_attr((string) $value); ?>"
            min="1"
            max="<?php echo esc_attr((string) self::MAX_LIMIT_CAP); ?>"
            step="1"
        />
        <?php
    }

    /**
     * @return array{maxConditions: int, maxActions: int, displayColumns: int}
     */
    public static function panelConfigForScript(): array
    {
        $config = [
            'maxConditions'  => self::DEFAULT_MAX_CONDITIONS,
            'maxActions'     => self::DEFAULT_MAX_ACTIONS,
            'displayColumns' => self::DEFAULT_DISPLAY_COLUMNS,
        ];

        /**
         * @param array{maxConditions: int, maxActions: int, displayColumns: int} $config
         */
        $filtered = apply_filters('gro_rules_editor_panel_config', $config);

        return is_array($filtered) ? [
            'maxConditions'  => self::sanitizeLimit($filtered['maxConditions'] ?? $config['maxConditions'], $config['maxConditions']),
            'maxActions'     => self::sanitizeLimit($filtered['maxActions'] ?? $config['maxActions'], $config['maxActions']),
            'displayColumns' => self::sanitizeDisplayColumns($filtered['displayColumns'] ?? $config['displayColumns']),
        ] : $config;
    }

    public static function maxConditions(): int
    {
        return self::DEFAULT_MAX_CONDITIONS;
    }

    public static function maxActions(): int
    {
        return self::DEFAULT_MAX_ACTIONS;
    }

    public static function displayColumns(): int
    {
        return self::DEFAULT_DISPLAY_COLUMNS;
    }

    private static function sanitizeLimit(mixed $value, int $default): int
    {
        if (! is_numeric($value)) {
            return $default;
        }

        $n = (int) $value;

        if ($n < 1) {
            return 1;
        }

        if ($n > self::MAX_LIMIT_CAP) {
            return self::MAX_LIMIT_CAP;
        }

        return $n;
    }

    private static function sanitizeDisplayColumns(mixed $value): int
    {
        $n = self::sanitizeLimit($value, self::DEFAULT_DISPLAY_COLUMNS);

        return $n === 2 ? 2 : 1;
    }
}
