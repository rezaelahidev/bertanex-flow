<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Admin\Controllers;

use GrowthOrchestrator\Admin\OmdboxAdminFonts;
use GrowthOrchestrator\Admin\Models\RulesAdminConfig;
use GrowthOrchestrator\Admin\Models\RulesDocumentModel;
use GrowthOrchestrator\Admin\Models\RulesEditorSettingsModel;
use GrowthOrchestrator\Admin\Models\SmsSettingsModel;
use GrowthOrchestrator\Admin\Models\SmsTemplatesModel;
use GrowthOrchestrator\Admin\ViewModels\RulesPageContext;
use GrowthOrchestrator\Admin\Views\RulesPageView;
use GrowthOrchestrator\Event\EventType;
use GrowthOrchestrator\I18n;
use GrowthOrchestrator\Loader\HooksLoader;
use GrowthOrchestrator\Rule\BuiltinConditionOperators;
use GrowthOrchestrator\Rule\RuleLabelTranslator;

/**
 * Controller: wires WordPress hooks, delegates persistence to {@see RulesDocumentModel}, renders via {@see RulesPageView}.
 */
final class RulesController
{
    public const PAGE_SLUG = 'growth-orchestrator';

    private const DRAFT_TRANSIENT_PREFIX = 'gro_rules_admin_draft_';

    public function __construct(
        private readonly RulesDocumentModel $model,
        private readonly RulesPageView $view,
        private readonly RulesEditorSettingsModel $editorSettings,
        private readonly SmsSettingsModel $smsSettings,
        private readonly SmsTemplatesModel $smsTemplates
    ) {
    }

    public function register(HooksLoader $hooks): void
    {
        $hooks->add_action($this, 'onAdminMenu', 'admin_menu', 9, 0);
        $hooks->add_action($this, 'registerEditorSettings', 'admin_init', 5, 0);
        $hooks->add_action($this, 'redirectLegacySmsMenu', 'admin_init', 2, 0);
        $hooks->add_action($this, 'onAdminInit', 'admin_init', 1, 0);
        $hooks->add_action($this, 'enqueueAssets', 'admin_enqueue_scripts', 10, 1);
        $hooks->add_action($this, 'onAjaxSaveRules', 'wp_ajax_gro_rules_save', 0, 0);
    }

    public function registerEditorSettings(): void
    {
        if (! is_admin()) {
            return;
        }

        $this->editorSettings->registerSettings();
        $this->smsSettings->registerSettings();
        $this->smsTemplates->registerSettings();

        add_filter('wp_redirect', [$this, 'preserveRulesTabOnSettingsSave'], 10, 2);
    }

    /**
     * After options.php save, reopen the tab the user submitted from (config vs patterns).
     */
    public function preserveRulesTabOnSettingsSave(string $location, int $status): string
    {
        unset($status);

        if (! is_admin() || ! isset($_POST['option_page'])) {
            return $location;
        }

        if ((string) $_POST['option_page'] !== RulesAdminConfig::SETTINGS_GROUP) {
            return $location;
        }

        $tab = isset($_POST['gro_open_rules_tab']) ? sanitize_key(wp_unslash((string) $_POST['gro_open_rules_tab'])) : '';

        if (in_array($tab, ['config', 'patterns'], true)) {
            return add_query_arg('gro_open_tab', $tab, $location);
        }

        return $location;
    }

    /** SMS submenu merged into Rules → Configuration; keep legacy URL working. */
    public function redirectLegacySmsMenu(): void
    {
        if (! is_admin()) {
            return;
        }

        $page = isset($_GET['page']) ? (string) $_GET['page'] : '';
        if ($page !== SmsSettingsModel::LEGACY_PAGE_SLUG) {
            return;
        }

        wp_safe_redirect(
            add_query_arg(
                [
                    'page'              => self::PAGE_SLUG,
                    'settings-updated' => '1',
                ],
                admin_url('admin.php')
            )
        );
        exit;
    }

    public function onAdminInit(): void
    {
        if (! is_admin()) {
            return;
        }

        if (! isset($_POST['gro_rules_save'])) {
            return;
        }

        $page = isset($_REQUEST['page']) ? (string) $_REQUEST['page'] : '';
        if ($page !== self::PAGE_SLUG) {
            return;
        }

        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to save rules.', 'growth-orchestrator'));
        }

        check_admin_referer('gro_rules_save');

        $raw = isset($_POST['gro_rules_json']) ? wp_unslash((string) $_POST['gro_rules_json']) : '';

        try {
            $this->model->saveValidatedDocument($raw);
        } catch (\Throwable $e) {
            $code = $this->errorCodeForThrowable($e);

            add_settings_error(
                'gro_rules',
                $code,
                sprintf(
                    /* translators: %s: parser or persistence error message */
                    __('Rules could not be saved: %s', 'growth-orchestrator'),
                    esc_html($e->getMessage())
                ),
                'error'
            );

            set_transient(
                self::DRAFT_TRANSIENT_PREFIX . get_current_user_id(),
                $raw,
                HOUR_IN_SECONDS
            );

            return;
        }

        delete_transient(self::DRAFT_TRANSIENT_PREFIX . get_current_user_id());

        wp_safe_redirect(
            add_query_arg(
                [
                    'page'    => self::PAGE_SLUG,
                    'updated' => '1',
                ],
                admin_url('admin.php')
            )
        );
        exit;
    }

    public function onAjaxSaveRules(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error(
                ['message' => esc_html__('You do not have permission to save rules.', 'growth-orchestrator')],
                403
            );
        }

        check_ajax_referer('gro_rules_save', 'nonce');

        $raw = isset($_POST['gro_rules_json']) ? wp_unslash((string) $_POST['gro_rules_json']) : '';

        try {
            $this->model->saveValidatedDocument($raw);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()], 400);
        }

        delete_transient(self::DRAFT_TRANSIENT_PREFIX . get_current_user_id());

        wp_send_json_success([
            'message' => __('Rules saved.', 'growth-orchestrator'),
        ]);
    }

    public function enqueueAssets(string $hook_suffix): void
    {
        if ($hook_suffix !== 'toplevel_page_' . self::PAGE_SLUG) {
            return;
        }

        if (! \defined('GROWTH_ORCH_PLUGIN_URL') || ! \defined('GROWTH_ORCH_PLUGIN_DIR')) {
            return;
        }

        I18n::loadTextdomain();

        $base = GROWTH_ORCH_PLUGIN_URL . 'assets/admin/';

        wp_enqueue_style('dashicons');

        $cssVer = self::assetFileVersion('assets/admin/rules-visual-editor.css');
        wp_enqueue_style(
            'gro-rules-visual-editor',
            $base . 'rules-visual-editor.css',
            [],
            $cssVer
        );

        wp_enqueue_style(
            'gro-rules-panel-polish',
            $base . 'rules-panel-polish.css',
            ['gro-rules-visual-editor'],
            self::assetFileVersion('assets/admin/rules-panel-polish.css')
        );

        wp_enqueue_style(
            'gro-rules-patterns',
            $base . 'rules-patterns.css',
            ['gro-rules-visual-editor', 'gro-rules-panel-polish'],
            self::assetFileVersion('assets/admin/rules-patterns.css')
        );

        if (is_rtl()) {
            OmdboxAdminFonts::enqueue('gro-rules-visual-editor');

            wp_enqueue_style(
                'gro-rules-visual-editor-rtl',
                $base . 'rules-visual-editor-rtl.css',
                ['gro-rules-visual-editor'],
                self::assetFileVersion('assets/admin/rules-visual-editor-rtl.css')
            );

            wp_add_inline_style(
                'gro-rules-visual-editor',
                '.gro-rules-admin--rtl,.rtl .gro-rules-admin{--gro-font-fa:' . OmdboxAdminFonts::fontFamily() . ';}'
            );
        }

        wp_enqueue_script(
            'gro-rules-visual-editor',
            $base . 'rules-visual-editor.bundle.js',
            [],
            self::assetFileVersion('assets/admin/rules-visual-editor.bundle.js'),
            true
        );

        wp_enqueue_script(
            'gro-sms-templates-settings',
            $base . 'sms-templates-settings.js',
            [],
            self::assetFileVersion('assets/admin/sms-templates-settings.js'),
            true
        );

        $events = [];

        foreach (EventType::cases() as $case) {
            $events[] = [
                'value' => $case->value,
                'label' => self::eventTypeLabel($case),
            ];
        }

        /** @phpstan-ignore-next-line */
        $operators = apply_filters(
            'gro_rules_editor_operator_choices',
            array_keys(BuiltinConditionOperators::defaultHandlers())
        );

        /** @phpstan-ignore-next-line */
        $actionTypes = apply_filters(
            'gro_rules_editor_action_type_choices',
            ['send_sms', 'send_email', 'send_discount', 'webhook', 'log']
        );

        $recurrences = ['hourly', 'twicedaily', 'daily', 'weekly'];

        /** @phpstan-ignore-next-line */
        $suggestedFieldSlugs = apply_filters(
            'gro_rules_editor_suggested_condition_fields',
            [
                'profile_completed',
                'has_product',
                'has_subscription',
                'subscription_status',
                'subscription_days_until_expiry',
            ]
        );

        $suggestedFields = self::conditionFieldsForSlugs($suggestedFieldSlugs);

        wp_localize_script(
            'gro-rules-visual-editor',
            'groRulesEditor',
            [
                'saveAjaxUrl'      => admin_url('admin-ajax.php'),
                'saveAction'       => 'gro_rules_save',
                'saveNonce'        => wp_create_nonce('gro_rules_save'),
                'panelConfig'      => RulesEditorSettingsModel::panelConfigForScript(),
                'defaultVersion'   => '1.0.0',
                'recurrences'      => $recurrences,
                'events'           => $events,
                'operators'        => $operators,
                'operatorLabels'   => self::operatorLabelsForSlugs($operators),
                'actionTypes'      => $actionTypes,
                'actionTypeLabels' => self::actionTypeLabelsForSlugs($actionTypes),
                'recurrenceLabels' => self::recurrenceLabelsForSlugs($recurrences),
                'suggestedFields'  => $suggestedFields,
                'smsTemplates'     => SmsTemplatesModel::forEditor(),
                'useJalaliCalendar' => is_rtl() && str_starts_with(
                    function_exists('determine_locale') ? determine_locale() : (string) get_locale(),
                    'fa'
                ),
                'jalaliMonthNames' => self::jalaliMonthNamesForEditor(),
                'strings'          => [
                    'errInvalidRoot'   => __('JSON must be an object with a "rules" array.', 'growth-orchestrator'),
                    'noRules'          => __('No rules. Add one or paste JSON in the Raw JSON tab.', 'growth-orchestrator'),
                    'addRule'          => __('Add rule', 'growth-orchestrator'),
                    'versionLabel'     => __('Format version', 'growth-orchestrator'),
                    'advancedBadge'    => __('Advanced', 'growth-orchestrator'),
                    'advancedDesc'     => __('This rule uses triggers or nested conditions that the visual editor does not edit. Change it under Raw JSON; the card will reload after a successful parse.', 'growth-orchestrator'),
                    'placeholderLabel' => __('Label (optional)', 'growth-orchestrator'),
                    'untitledRuleSummary' => __('Untitled rule', 'growth-orchestrator'),
                    'ruleDetailsHeading'   => __('Edit rule', 'growth-orchestrator'),
                    'enabledLabel'     => __('Enabled', 'growth-orchestrator'),
                    'priorityLabel'    => __('Priority', 'growth-orchestrator'),
                    'ruleIdShort'      => __('id', 'growth-orchestrator'),
                    'dupRule'          => __('Duplicate', 'growth-orchestrator'),
                    'up'               => __('Move up', 'growth-orchestrator'),
                    'down'             => __('Move down', 'growth-orchestrator'),
                    'removeRule'       => __('Remove rule', 'growth-orchestrator'),
                    'removeRuleShort'  => __('Remove', 'growth-orchestrator'),
                    'openRuleModal'     => __('Edit rule %s', 'growth-orchestrator'),
                    'openConditionModal' => __('Edit condition %s', 'growth-orchestrator'),
                    'openActionModal'   => __('Edit action %s', 'growth-orchestrator'),
                    'modalClose'        => __('Close', 'growth-orchestrator'),
                    'saveRule'          => __('Save rule', 'growth-orchestrator'),
                    'saveAll'           => __('Save all', 'growth-orchestrator'),
                    'saving'            => __('Saving…', 'growth-orchestrator'),
                    'saveSuccess'       => __('Rules saved.', 'growth-orchestrator'),
                    'saveFailed'        => __('Rules could not be saved: %s', 'growth-orchestrator'),
                    'statusLabel'       => __('Status', 'growth-orchestrator'),
                    'labelCol'          => __('Label', 'growth-orchestrator'),
                    'idCol'             => __('ID', 'growth-orchestrator'),
                    'removeConditionAria' => __('Remove condition %s from this rule', 'growth-orchestrator'),
                    'removeActionAria' => __('Remove action %s from this rule', 'growth-orchestrator'),
                    'removeRuleCollapsedAria' => __('Remove %s permanently from this list', 'growth-orchestrator'),
                    'ruleListHeading' => __('Configured rules', 'growth-orchestrator'),
                    'disabledLabel'   => __('Disabled', 'growth-orchestrator'),
                    'collapsedIdLabel'      => __('Rule ID:', 'growth-orchestrator'),
                    'collapsedPriorityLabel' => __('Priority:', 'growth-orchestrator'),
                    'confirmRemove'    => __('Remove this rule?', 'growth-orchestrator'),
                    'confirmRemoveAdv' => __('Remove this rule from the document?', 'growth-orchestrator'),
                    'triggerHeading'   => __('When to run', 'growth-orchestrator'),
                    'triggerNone'      => __('Evaluate without event filter', 'growth-orchestrator'),
                    'triggerEvents'    => __('On automation events', 'growth-orchestrator'),
                    'triggerSchedule'  => __('On a schedule (cron)', 'growth-orchestrator'),
                    'customEventsLabel'=> __('Additional event names (comma-separated)', 'growth-orchestrator'),
                    'hookLabel'        => __('Cron hook', 'growth-orchestrator'),
                    'recurrenceLabel'  => __('Recurrence', 'growth-orchestrator'),
                    'conditionsHeading'=> __('Conditions (all must match)', 'growth-orchestrator'),
                    'fieldCol'         => __('Field', 'growth-orchestrator'),
                    'opCol'            => __('Operator', 'growth-orchestrator'),
                    'patternCol'       => __('Pattern (regex)', 'growth-orchestrator'),
                    'valueCol'         => __('Value (JSON)', 'growth-orchestrator'),
                    'customField'      => __('Custom…', 'growth-orchestrator'),
                    'fieldPlaceholder' => __('State field key', 'growth-orchestrator'),
                    'removeRow'        => __('Remove', 'growth-orchestrator'),
                    'addCondition'     => __('Add condition', 'growth-orchestrator'),
                    'actionsHeading'   => __('Actions', 'growth-orchestrator'),
                    'actionType'       => __('Type', 'growth-orchestrator'),
                    'templateId'       => __('template_id', 'growth-orchestrator'),
                    'delaySeconds'     => __('delay_seconds', 'growth-orchestrator'),
                    'delayLabel'       => __('Delay', 'growth-orchestrator'),
                    'delayRunImmediately' => __('Run immediately when the rule matches', 'growth-orchestrator'),
                    'delayModeImmediateHint' => __('When the rule matches', 'growth-orchestrator'),
                    'delayModeSchedule'    => __('Schedule', 'growth-orchestrator'),
                    'delayModeScheduleHint' => __('Pick date and time after now', 'growth-orchestrator'),
                    'delayTimeNoneShort'   => __('No extra time', 'growth-orchestrator'),
                    'delayDateLegend'  => __('Date', 'growth-orchestrator'),
                    'delayDateToday'   => __('Today', 'growth-orchestrator'),
                    'delayDateInDays'  => __('In', 'growth-orchestrator'),
                    'delayDateDaysLabel' => __('days', 'growth-orchestrator'),
                    'delayDateOn'      => __('On', 'growth-orchestrator'),
                    'delayTimeLegend'  => __('Time', 'growth-orchestrator'),
                    'delayTimeNone'    => __('Same time (date offset only)', 'growth-orchestrator'),
                    'delayTimeSeconds' => __('seconds', 'growth-orchestrator'),
                    'delayTimeMinutes' => __('minutes', 'growth-orchestrator'),
                    'delayTimeHours'   => __('hours', 'growth-orchestrator'),
                    'delayTimeAt'      => __('At', 'growth-orchestrator'),
                    'delayPreviewLabel' => __('Preview:', 'growth-orchestrator'),
                    'delayImmediate'   => __('Immediately when the rule matches', 'growth-orchestrator'),
                    'delayImmediateShort' => __('Immediate', 'growth-orchestrator'),
                    'delayPreviewAt'   => __('Scheduled at %1$s %2$s (about %3$s from now)', 'growth-orchestrator'),
                    'delayPreviewSeconds' => __('About %s seconds after the rule matches', 'growth-orchestrator'),
                    'delayPreviewRelative' => __('About %s after the rule matches', 'growth-orchestrator'),
                    'delayOneDay'      => __('1 day', 'growth-orchestrator'),
                    'delayDays'        => __('%s days', 'growth-orchestrator'),
                    'delayOneHour'     => __('1 hour', 'growth-orchestrator'),
                    'delayHours'       => __('%s hours', 'growth-orchestrator'),
                    'delayOneMinute'   => __('1 minute', 'growth-orchestrator'),
                    'delayMinutes'     => __('%s minutes', 'growth-orchestrator'),
                    'delayOneSecond'   => __('1 second', 'growth-orchestrator'),
                    'delaySecondsUnit' => __('%s seconds', 'growth-orchestrator'),
                    'jalaliDay'        => __('Day', 'growth-orchestrator'),
                    'jalaliMonth'      => __('Month', 'growth-orchestrator'),
                    'jalaliYear'       => __('Year', 'growth-orchestrator'),
                    'paramsJson'       => __('params (JSON object)', 'growth-orchestrator'),
                    'removeAction'     => __('Remove action', 'growth-orchestrator'),
                    'addAction'        => __('Add action', 'growth-orchestrator'),
                    'regexPatternPlaceholder' => __('/^...$/', 'growth-orchestrator'),
                    'valueJsonPlaceholder' => __('Examples: true, false, 3, "text", []', 'growth-orchestrator'),
                    'valueSelectPlaceholder' => __('Select a value…', 'growth-orchestrator'),
                    'customValue'          => __('Custom value (JSON)…', 'growth-orchestrator'),
                    'conditionsLimitReached' => __('Maximum number of conditions reached (%s).', 'growth-orchestrator'),
                    'actionsLimitReached'    => __('Maximum number of actions reached (%s).', 'growth-orchestrator'),
                    'templateSelect'         => __('SMS template', 'growth-orchestrator'),
                    'templateCustom'         => __('Custom template ID…', 'growth-orchestrator'),
                    'templatePreview'        => __('Preview:', 'growth-orchestrator'),
                    'templateConfigHint'     => __('Edit message text under Message patterns.', 'growth-orchestrator'),
                ],
            ]
        );
    }

    /**
     * @param list<string|array{name?: string, label?: string, valueType?: string, values?: list<array{value?: mixed, label?: string}>}> $items
     * @return list<array{name: string, label: string, valueType?: string, values?: list<array{value: bool|int|float|string, label: string}>}>
     */
    private static function conditionFieldsForSlugs(array $items): array
    {
        $baseline = self::baselineConditionFieldDefinitions();
        $out      = [];

        foreach ($items as $item) {
            if (is_string($item) && $item !== '') {
                $out[] = $baseline[$item] ?? [
                    'name'  => $item,
                    'label' => self::conditionFieldSlugLabel($item),
                ];
                continue;
            }
            if (! is_array($item)) {
                continue;
            }
            $name = isset($item['name']) ? (string) $item['name'] : '';
            if ($name === '') {
                continue;
            }
            $merged = $baseline[$name] ?? [
                'name'  => $name,
                'label' => self::conditionFieldSlugLabel($name),
            ];
            if (isset($item['label']) && (string) $item['label'] !== '') {
                $merged['label'] = (string) $item['label'];
            }
            if (isset($item['valueType']) && is_string($item['valueType']) && $item['valueType'] !== '') {
                $merged['valueType'] = $item['valueType'];
            }
            if (isset($item['values']) && is_array($item['values']) && $item['values'] !== []) {
                $merged['values'] = self::normalizeConditionValueChoices($item['values']);
            }
            $out[] = $merged;
        }

        return $out;
    }

    /**
     * @return array<string, array{name: string, label: string, valueType?: string, values?: list<array{value: bool|int|float|string, label: string}>}>
     */
    private static function baselineConditionFieldDefinitions(): array
    {
        $yes = __('Yes', 'growth-orchestrator');
        $no  = __('No', 'growth-orchestrator');

        $defs = [
            'profile_completed' => [
                'name'      => 'profile_completed',
                'label'     => self::conditionFieldSlugLabel('profile_completed'),
                'valueType' => 'boolean',
                'values'    => [
                    ['value' => true, 'label' => $yes],
                    ['value' => false, 'label' => $no],
                ],
            ],
            'has_product' => [
                'name'      => 'has_product',
                'label'     => self::conditionFieldSlugLabel('has_product'),
                'valueType' => 'boolean',
                'values'    => [
                    ['value' => true, 'label' => $yes],
                    ['value' => false, 'label' => $no],
                ],
            ],
            'has_subscription' => [
                'name'      => 'has_subscription',
                'label'     => self::conditionFieldSlugLabel('has_subscription'),
                'valueType' => 'boolean',
                'values'    => [
                    ['value' => true, 'label' => $yes],
                    ['value' => false, 'label' => $no],
                ],
            ],
            'subscription_status' => [
                'name'      => 'subscription_status',
                'label'     => self::conditionFieldSlugLabel('subscription_status'),
                'valueType' => 'string',
                'values'    => [
                    ['value' => 'active', 'label' => __('Active', 'growth-orchestrator')],
                    ['value' => 'expired', 'label' => __('Expired', 'growth-orchestrator')],
                    ['value' => 'cancelled', 'label' => __('Cancelled', 'growth-orchestrator')],
                ],
            ],
            'subscription_days_until_expiry' => [
                'name'      => 'subscription_days_until_expiry',
                'label'     => self::conditionFieldSlugLabel('subscription_days_until_expiry'),
                'valueType' => 'integer',
                'values'    => [
                    ['value' => 0, 'label' => __('0 days (expires today)', 'growth-orchestrator')],
                    ['value' => 1, 'label' => __('1 day', 'growth-orchestrator')],
                    ['value' => 3, 'label' => __('3 days', 'growth-orchestrator')],
                    ['value' => 7, 'label' => __('7 days', 'growth-orchestrator')],
                    ['value' => 14, 'label' => __('14 days', 'growth-orchestrator')],
                    ['value' => 30, 'label' => __('30 days', 'growth-orchestrator')],
                ],
            ],
        ];

        /**
         * @param array<string, array{name: string, label: string, valueType?: string, values?: list<array{value: bool|int|float|string, label: string}>}> $definitions
         */
        return apply_filters('gro_rules_editor_condition_field_definitions', $defs);
    }

    /**
     * @param list<array{value?: mixed, label?: string}> $choices
     * @return list<array{value: bool|int|float|string, label: string}>
     */
    private static function normalizeConditionValueChoices(array $choices): array
    {
        $out = [];
        foreach ($choices as $choice) {
            if (! is_array($choice) || ! isset($choice['value'], $choice['label'])) {
                continue;
            }
            $value = $choice['value'];
            if (! is_bool($value) && ! is_int($value) && ! is_float($value) && ! is_string($value)) {
                continue;
            }
            $label = trim((string) $choice['label']);
            if ($label === '') {
                continue;
            }
            $out[] = ['value' => $value, 'label' => $label];
        }

        return $out;
    }

    private static function conditionFieldSlugLabel(string $slug): string
    {
        return match ($slug) {
            'profile_completed'              => __('Profile completed', 'growth-orchestrator'),
            'has_product'                    => __('Has product', 'growth-orchestrator'),
            'has_subscription'               => __('Has subscription', 'growth-orchestrator'),
            'subscription_status'            => __('Subscription status', 'growth-orchestrator'),
            'subscription_days_until_expiry' => __('Days until subscription expires', 'growth-orchestrator'),
            default                          => $slug,
        };
    }

    /**
     * @param list<string> $slugs
     * @return array<string, string>
     */
    private static function operatorLabelsForSlugs(array $slugs): array
    {
        $out = [];
        foreach ($slugs as $op) {
            if (! is_string($op) || $op === '') {
                continue;
            }
            $out[$op] = self::operatorSlugLabel($op);
        }

        return $out;
    }

    private static function operatorSlugLabel(string $op): string
    {
        return match ($op) {
            'eq' => __('Equals', 'growth-orchestrator'),
            'neq' => __('Not equals', 'growth-orchestrator'),
            'gt' => __('Greater than', 'growth-orchestrator'),
            'gte' => __('Greater than or equal', 'growth-orchestrator'),
            'lt' => __('Less than', 'growth-orchestrator'),
            'lte' => __('Less than or equal', 'growth-orchestrator'),
            'in' => __('In list', 'growth-orchestrator'),
            'not_in' => __('Not in list', 'growth-orchestrator'),
            'empty' => __('Is empty', 'growth-orchestrator'),
            'not_empty' => __('Is not empty', 'growth-orchestrator'),
            'truthy' => __('Is truthy', 'growth-orchestrator'),
            'falsy' => __('Is falsy', 'growth-orchestrator'),
            'regex' => __('Matches regex', 'growth-orchestrator'),
            'starts_with' => __('Starts with', 'growth-orchestrator'),
            'ends_with' => __('Ends with', 'growth-orchestrator'),
            'contains' => __('Contains', 'growth-orchestrator'),
            default => $op,
        };
    }

    /**
     * @param list<string> $slugs
     * @return array<string, string>
     */
    private static function actionTypeLabelsForSlugs(array $slugs): array
    {
        $out = [];
        foreach ($slugs as $slug) {
            if (! is_string($slug) || $slug === '') {
                continue;
            }
            $out[$slug] = self::actionTypeSlugLabel($slug);
        }

        return $out;
    }

    private static function actionTypeSlugLabel(string $slug): string
    {
        return match ($slug) {
            'send_sms' => __('Send SMS', 'growth-orchestrator'),
            'send_email' => __('Send email', 'growth-orchestrator'),
            'send_discount' => __('Send discount', 'growth-orchestrator'),
            'webhook' => __('Webhook', 'growth-orchestrator'),
            'log' => __('Log', 'growth-orchestrator'),
            default => $slug,
        };
    }

    /**
     * @param list<string> $slugs
     * @return array<string, string>
     */
    /**
     * Jalali month names for the rules editor date picker (Persian admin).
     *
     * @return list<string>
     */
    private static function jalaliMonthNamesForEditor(): array
    {
        return [
            'فروردین',
            'اردیبهشت',
            'خرداد',
            'تیر',
            'مرداد',
            'شهریور',
            'مهر',
            'آبان',
            'آذر',
            'دی',
            'بهمن',
            'اسفند',
        ];
    }

    private static function recurrenceLabelsForSlugs(array $slugs): array
    {
        $out = [];
        foreach ($slugs as $slug) {
            if (! is_string($slug) || $slug === '') {
                continue;
            }
            $out[$slug] = self::recurrenceSlugLabel($slug);
        }

        return $out;
    }

    private static function recurrenceSlugLabel(string $slug): string
    {
        return match ($slug) {
            'hourly' => __('Hourly', 'growth-orchestrator'),
            'twicedaily' => __('Twice daily', 'growth-orchestrator'),
            'daily' => __('Daily', 'growth-orchestrator'),
            'weekly' => __('Weekly', 'growth-orchestrator'),
            default => $slug,
        };
    }

    private static function eventTypeLabel(EventType $type): string
    {
        return match ($type) {
            EventType::UserRegistered        => __('User registered', 'growth-orchestrator'),
            EventType::ProfileCompleted      => __('Profile completed', 'growth-orchestrator'),
            EventType::ProductCreated        => __('Product created', 'growth-orchestrator'),
            EventType::SubscriptionPurchased => __('Subscription purchased', 'growth-orchestrator'),
            EventType::SubscriptionExpired   => __('Subscription expired', 'growth-orchestrator'),
            EventType::Login                 => __('User login', 'growth-orchestrator'),
        };
    }

    public function onAdminMenu(): void
    {
        I18n::loadTextdomain();

        add_menu_page(
            __('Automation Rules', 'growth-orchestrator'),
            __('Rules', 'growth-orchestrator'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render'],
            'dashicons-clipboard',
            58
        );
    }

    public function render(): void
    {
        I18n::loadTextdomain();

        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'growth-orchestrator'));
        }

        $draft = get_transient(self::DRAFT_TRANSIENT_PREFIX . get_current_user_id());
        $textareaValue = (is_string($draft) && $draft !== '')
            ? RuleLabelTranslator::localizeDocumentJson($draft)
            : $this->model->getEditorJson();

        $parsed = $this->model->parseForDisplay($textareaValue);

        $context = new RulesPageContext(
            pageSlug: self::PAGE_SLUG,
            formActionUrl: admin_url('admin.php?page=' . self::PAGE_SLUG),
            textareaValue: $textareaValue,
            parseError: $parsed['error'],
            definitions: $parsed['definitions'],
            hasDraftTransient: is_string($draft) && $draft !== '',
            showSuccessNotice: isset($_GET['updated']) && (string) $_GET['updated'] === '1'
        );

        $this->view->render($context);
    }

    /**
     * Cache-bust admin assets from file modification time (falls back to plugin version).
     *
     * @param non-empty-string $relativePath Path under the plugin root (e.g. assets/admin/foo.css).
     */
    private static function assetFileVersion(string $relativePath): string
    {
        $path = \GROWTH_ORCH_PLUGIN_DIR . ltrim($relativePath, '/');
        $mtime = @filemtime($path);

        return $mtime !== false
            ? (string) $mtime
            : (\defined('GROWTH_ORCH_VERSION') ? (string) \GROWTH_ORCH_VERSION : '0');
    }

    private function errorCodeForThrowable(\Throwable $e): string
    {
        if ($e instanceof \InvalidArgumentException) {
            return 'gro_rules_invalid_json';
        }

        $msg = strtolower($e->getMessage());

        return str_contains($msg, 'json') ? 'gro_rules_invalid_json' : 'gro_rules_save_failed';
    }
}
