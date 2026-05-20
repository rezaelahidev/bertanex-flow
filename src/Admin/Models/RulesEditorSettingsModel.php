<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Admin\Models;

/**
 * Visual rules editor panel options (Settings API), exposed on the Rules → Config tab.
 */
final class RulesEditorSettingsModel
{
    public const SETTINGS_GROUP = RulesAdminConfig::SETTINGS_GROUP;

    public const OPTION_MAX_CONDITIONS = 'growth_orchestrator_rules_max_conditions';

    public const OPTION_MAX_ACTIONS = 'growth_orchestrator_rules_max_actions';

    public const OPTION_DISPLAY_COLUMNS = 'growth_orchestrator_rules_display_columns';

    public const DEFAULT_MAX_CONDITIONS = 1;

    public const DEFAULT_MAX_ACTIONS = 1;

    public const DEFAULT_DISPLAY_COLUMNS = 1;

    private const MAX_LIMIT_CAP = 50;

    public function registerSettings(): void
    {
        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_MAX_CONDITIONS,
            [
                'type'              => 'integer',
                'default'           => self::DEFAULT_MAX_CONDITIONS,
                'sanitize_callback' => static fn ($v): int => self::sanitizeLimit($v, self::DEFAULT_MAX_CONDITIONS),
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_MAX_ACTIONS,
            [
                'type'              => 'integer',
                'default'           => self::DEFAULT_MAX_ACTIONS,
                'sanitize_callback' => static fn ($v): int => self::sanitizeLimit($v, self::DEFAULT_MAX_ACTIONS),
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_DISPLAY_COLUMNS,
            [
                'type'              => 'integer',
                'default'           => self::DEFAULT_DISPLAY_COLUMNS,
                'sanitize_callback' => static fn ($v): int => self::sanitizeDisplayColumns($v),
            ]
        );

        add_settings_section(
            'gro_rules_editor_panel_section',
            '',
            static function (): void {
                echo '<p class="gro-settings-card__lede">' .
                    esc_html__(
                        'Limits and layout for the visual rule builder lists.',
                        'growth-orchestrator'
                    ) .
                    '</p>';
            },
            self::SETTINGS_GROUP
        );

        add_settings_field(
            self::OPTION_MAX_CONDITIONS,
            __('Max conditions per rule', 'growth-orchestrator'),
            [$this, 'renderMaxConditionsField'],
            self::SETTINGS_GROUP,
            'gro_rules_editor_panel_section',
            ['label_for' => self::OPTION_MAX_CONDITIONS]
        );

        add_settings_field(
            self::OPTION_MAX_ACTIONS,
            __('Max actions per rule', 'growth-orchestrator'),
            [$this, 'renderMaxActionsField'],
            self::SETTINGS_GROUP,
            'gro_rules_editor_panel_section',
            ['label_for' => self::OPTION_MAX_ACTIONS]
        );

        add_settings_field(
            self::OPTION_DISPLAY_COLUMNS,
            __('Display columns', 'growth-orchestrator'),
            [$this, 'renderDisplayColumnsField'],
            self::SETTINGS_GROUP,
            'gro_rules_editor_panel_section',
            ['label_for' => self::OPTION_DISPLAY_COLUMNS]
        );
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
            <option value="1" <?php selected($v, 1); ?>><?php echo esc_html__('1 column', 'growth-orchestrator'); ?></option>
            <option value="2" <?php selected($v, 2); ?>><?php echo esc_html__('2 columns', 'growth-orchestrator'); ?></option>
        </select>
        <p class="description">
            <?php echo esc_html__('Applies to the rules list and condition/action lists inside each rule.', 'growth-orchestrator'); ?>
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
            'maxConditions'  => self::maxConditions(),
            'maxActions'     => self::maxActions(),
            'displayColumns' => self::displayColumns(),
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
        return self::sanitizeLimit(get_option(self::OPTION_MAX_CONDITIONS, self::DEFAULT_MAX_CONDITIONS), self::DEFAULT_MAX_CONDITIONS);
    }

    public static function maxActions(): int
    {
        return self::sanitizeLimit(get_option(self::OPTION_MAX_ACTIONS, self::DEFAULT_MAX_ACTIONS), self::DEFAULT_MAX_ACTIONS);
    }

    public static function displayColumns(): int
    {
        return self::sanitizeDisplayColumns(get_option(self::OPTION_DISPLAY_COLUMNS, self::DEFAULT_DISPLAY_COLUMNS));
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
