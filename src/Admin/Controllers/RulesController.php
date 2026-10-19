<?php

declare(strict_types=1);

namespace BertanexFlow\Admin\Controllers;

use BertanexFlow\Admin\RtlAdminTypography;
use BertanexFlow\Admin\PluginDefaultsReset;
use BertanexFlow\Admin\Models\RulesAdminConfig;
use BertanexFlow\Admin\Models\RulesDocumentModel;
use BertanexFlow\Admin\Models\RulesEditorSettingsModel;
use BertanexFlow\Admin\Models\SmsSettingsModel;
use BertanexFlow\Admin\Models\SmsTemplatesModel;
use BertanexFlow\Admin\ViewModels\RulesPageContext;
use BertanexFlow\Admin\Views\RulesPageView;
use BertanexFlow\Consumer\ConsumerRegistry;
use BertanexFlow\Event\EventType;
use BertanexFlow\I18n;
use BertanexFlow\Loader\HooksLoader;
use BertanexFlow\Rule\BuiltinConditionOperators;
use BertanexFlow\Rule\RuleLabelTranslator;

/**
 * Controller: wires WordPress hooks, delegates persistence to {@see RulesDocumentModel}, renders via {@see RulesPageView}.
 */
final class RulesController
{
    public const PAGE_SLUG = 'bertanex-flow';

    public const USER_META_PANEL = 'gro_rules_last_panel';

    private const DRAFT_TRANSIENT_PREFIX = 'gro_rules_admin_draft_';

    public function __construct(
        private readonly RulesDocumentModel $model,
        private readonly RulesPageView $view,
        private readonly RulesEditorSettingsModel $editorSettings,
        private readonly SmsSettingsModel $smsSettings,
        private readonly SmsTemplatesModel $smsTemplates,
        private readonly PluginDefaultsReset $defaultsReset
    ) {
    }

    public function register(HooksLoader $hooks): void
    {
        $hooks->add_action($this, 'onAdminMenu', 'admin_menu', 9, 0);
        $hooks->add_action($this, 'registerEditorSettings', 'admin_init', 5, 0);
        $hooks->add_action($this, 'redirectLegacySmsMenu', 'admin_init', 2, 0);
        $hooks->add_action($this, 'onAdminInit', 'admin_init', 1, 0);
        $hooks->add_action($this, 'enqueueAssets', 'admin_enqueue_scripts', 10, 1);
        $hooks->add_filter($this, 'adminBodyClass', 'admin_body_class', 10, 1);
        $hooks->add_action($this, 'onAjaxSaveRules', 'wp_ajax_gro_rules_save', 0, 0);
        $hooks->add_action($this, 'onAjaxSavePanel', 'wp_ajax_gro_rules_panel', 0, 0);
        $hooks->add_action($this, 'onAdminPostResetDefaults', 'admin_post_gro_reset_defaults', 0, 0);
    }

    public function onAdminPostResetDefaults(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to reset plugin settings.', 'bertanex-flow'));
        }

        check_admin_referer('gro_reset_defaults');

        $phrase = isset($_POST['gro_reset_phrase']) ? (string) wp_unslash($_POST['gro_reset_phrase']) : '';
        if ($phrase !== 'reset-all') {
            wp_safe_redirect(
                add_query_arg(
                    [
                        'page'         => self::PAGE_SLUG,
                        'gro_reset'    => 'error',
                        'gro_open_tab' => 'reset',
                    ],
                    admin_url('admin.php')
                )
            );
            exit;
        }

        $page = isset($_REQUEST['page']) ? sanitize_key((string) $_REQUEST['page']) : '';
        if ($page !== self::PAGE_SLUG) {
            $page = self::PAGE_SLUG;
        }

        try {
            $this->defaultsReset->resetAll();
        } catch (\Throwable $e) {
            wp_safe_redirect(
                add_query_arg(
                    [
                        'page'      => $page,
                        'gro_reset' => 'error',
                        'gro_open_tab' => 'reset',
                    ],
                    admin_url('admin.php')
                )
            );
            exit;
        }

        wp_safe_redirect(
            add_query_arg(
                [
                    'page'         => $page,
                    'gro_reset'    => '1',
                    'gro_open_tab' => 'reset',
                ],
                admin_url('admin.php')
            )
        );
        exit;
    }

    public function registerEditorSettings(): void
    {
        if (! is_admin()) {
            return;
        }

        $this->editorSettings->registerSettings();
        $this->smsSettings->registerSettings();
        $this->smsTemplates->registerSettings();
        (new \BertanexFlow\Admin\Models\EmailTemplatesModel())->registerSettings();

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

        $optionPage = (string) $_POST['option_page'];
        if (
            $optionPage !== RulesAdminConfig::SETTINGS_GROUP
            && $optionPage !== RulesAdminConfig::PATTERNS_SETTINGS_GROUP
            && $optionPage !== \BertanexFlow\Admin\Models\EmailTemplatesModel::SETTINGS_GROUP
        ) {
            return $location;
        }

        $tab = isset($_POST['gro_open_rules_tab']) ? sanitize_key(wp_unslash((string) $_POST['gro_open_rules_tab'])) : '';

        if (in_array($tab, ['config', 'reset', 'patterns', 'email', 'runs'], true)) {
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
            wp_die(esc_html__('You do not have permission to save rules.', 'bertanex-flow'));
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
                    __('Rules could not be saved: %s', 'bertanex-flow'),
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
                ['message' => esc_html__('You do not have permission to save rules.', 'bertanex-flow')],
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
            'message' => __('Rules saved.', 'bertanex-flow'),
        ]);
    }

    public function onAjaxSavePanel(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to save this panel.', 'bertanex-flow')], 403);
        }

        check_ajax_referer('gro_rules_save', 'nonce');

        $tab = isset($_POST['tab']) ? sanitize_key((string) wp_unslash($_POST['tab'])) : '';
        if (! in_array($tab, ['visual', 'patterns', 'email', 'runs', 'config', 'reset'], true)) {
            wp_send_json_error(['message' => __('Unknown panel.', 'bertanex-flow')], 400);
        }

        update_user_meta(get_current_user_id(), self::USER_META_PANEL, $tab);
        wp_send_json_success(['tab' => $tab]);
    }

    public function adminBodyClass(string $classes): string
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && $screen->id === 'toplevel_page_' . self::PAGE_SLUG) {
            $classes .= ' is-fullscreen-mode';
        }

        return $classes;
    }

    public function enqueueAssets(string $hook_suffix): void
    {
        wp_register_style('gro-admin-menu-icon', false, [], null);
        wp_enqueue_style('gro-admin-menu-icon');
        wp_add_inline_style(
            'gro-admin-menu-icon',
            '#adminmenu .toplevel_page_bertanex-flow div.wp-menu-image{display:flex;align-items:center;justify-content:center;}'
            . '#adminmenu .toplevel_page_bertanex-flow div.wp-menu-image img{width:20px;height:20px;padding:0;object-fit:contain;}'
        );

        if ($hook_suffix !== 'toplevel_page_' . self::PAGE_SLUG) {
            return;
        }

        if (! \defined('BERTANEX_FLOW_PLUGIN_URL') || ! \defined('BERTANEX_FLOW_PLUGIN_DIR')) {
            return;
        }

        I18n::loadTextdomain();

        $base = BERTANEX_FLOW_PLUGIN_URL . 'assets/admin/';

        wp_enqueue_style('dashicons');
        wp_enqueue_style('wp-components');
        wp_enqueue_style('wp-edit-site');

        $cssVer = self::assetFileVersion('assets/admin/rules-visual-editor.css');
        wp_enqueue_style(
            'gro-rules-visual-editor',
            $base . 'rules-visual-editor.css',
            ['wp-edit-site', 'wp-components'],
            $cssVer
        );

        if (is_rtl()) {
            RtlAdminTypography::enqueue('gro-rules-visual-editor');

            wp_enqueue_style(
                'gro-rules-visual-editor-rtl',
                $base . 'rules-visual-editor-rtl.css',
                ['gro-rules-visual-editor'],
                self::assetFileVersion('assets/admin/rules-visual-editor-rtl.css')
            );
        }

        wp_enqueue_script(
            'gro-rules-visual-editor',
            $base . 'rules-visual-editor.bundle.js',
            ['wp-element', 'wp-components'],
            self::assetFileVersion('assets/admin/rules-visual-editor.bundle.js'),
            true
        );

        wp_enqueue_script(
            'gro-sidebar-save',
            $base . 'sidebar-save.js',
            [],
            self::assetFileVersion('assets/admin/sidebar-save.js'),
            true
        );

        wp_enqueue_script(
            'gro-confirm-dialog',
            $base . 'confirm-dialog.js',
            ['wp-element', 'wp-components'],
            self::assetFileVersion('assets/admin/confirm-dialog.js'),
            true
        );

        wp_enqueue_script(
            'gro-sms-templates-settings',
            $base . 'sms-templates-settings.js',
            ['wp-element', 'wp-components', 'wp-i18n'],
            self::assetFileVersion('assets/admin/sms-templates-settings.js'),
            true
        );

        $events = ConsumerRegistry::instance()->eventChoices();

        if ($events === []) {
            foreach (EventType::cases() as $case) {
                $events[] = [
                    'value' => $case->value,
                    'label' => $case->value,
                ];
            }
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
        $suggestedFieldSlugs = ConsumerRegistry::instance()->suggestedConditionFields();

        $suggestedFields = self::conditionFieldsForSlugs($suggestedFieldSlugs);

        wp_localize_script(
            'gro-rules-visual-editor',
            'groRulesEditor',
            [
                'saveAjaxUrl'      => admin_url('admin-ajax.php'),
                'saveAction'       => 'gro_rules_save',
                'panelAction'      => 'gro_rules_panel',
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
                    'errInvalidRoot'   => __('JSON must be an object with a "rules" array.', 'bertanex-flow'),
                    'noRules'          => __('No rules yet. Add your first automation.', 'bertanex-flow'),
                    'automationsTitle' => __('Automation Rules', 'bertanex-flow'),
                    'automationsLede'  => __('Build automations, then manage SMS patterns, email templates, and delivery settings separately.', 'bertanex-flow'),
                    'totalRules'       => __('Total rules', 'bertanex-flow'),
                    'enabledRules'     => __('Enabled', 'bertanex-flow'),
                    'rulesSummary'     => __('Rules summary', 'bertanex-flow'),
                    'addRule'          => __('Add rule', 'bertanex-flow'),
                    'versionLabel'     => __('Format version', 'bertanex-flow'),
                    'advancedBadge'    => __('Advanced', 'bertanex-flow'),
                    'advancedDesc'     => __('This rule uses triggers or nested conditions this editor cannot change. It stays read-only and runs as stored.', 'bertanex-flow'),
                    'placeholderLabel' => __('Label (optional)', 'bertanex-flow'),
                    'untitledRuleSummary' => __('Untitled rule', 'bertanex-flow'),
                    'ruleDetailsHeading'   => __('Edit rule', 'bertanex-flow'),
                    'enabledLabel'     => __('Enabled', 'bertanex-flow'),
                    'priorityLabel'    => __('Priority', 'bertanex-flow'),
                    'ruleIdShort'      => __('id', 'bertanex-flow'),
                    'dupRule'          => __('Duplicate', 'bertanex-flow'),
                    'up'               => __('Move up', 'bertanex-flow'),
                    'down'             => __('Move down', 'bertanex-flow'),
                    'removeRule'       => __('Remove rule', 'bertanex-flow'),
                    'removeRuleShort'  => __('Remove', 'bertanex-flow'),
                    'openRuleModal'     => __('Edit rule %s', 'bertanex-flow'),
                    'openConditionModal' => __('Edit condition %s', 'bertanex-flow'),
                    'openActionModal'   => __('Edit action %s', 'bertanex-flow'),
                    'modalClose'        => __('Close', 'bertanex-flow'),
                    'saveRule'          => __('Save rule', 'bertanex-flow'),
                    'saveAll'           => __('Save all', 'bertanex-flow'),
                    'saving'            => __('Saving…', 'bertanex-flow'),
                    'saveSuccess'       => __('Rules saved.', 'bertanex-flow'),
                    'saveFailed'        => __('Rules could not be saved: %s', 'bertanex-flow'),
                    'statusLabel'       => __('Status', 'bertanex-flow'),
                    'labelCol'          => __('Label', 'bertanex-flow'),
                    'idCol'             => __('ID', 'bertanex-flow'),
                    'removeConditionAria' => __('Remove condition %s from this rule', 'bertanex-flow'),
                    'removeActionAria' => __('Remove action %s from this rule', 'bertanex-flow'),
                    'removeRuleCollapsedAria' => __('Remove %s permanently from this list', 'bertanex-flow'),
                    'ruleListHeading' => __('Configured rules', 'bertanex-flow'),
                    'disabledLabel'   => __('Disabled', 'bertanex-flow'),
                    'collapsedIdLabel'      => __('Rule ID:', 'bertanex-flow'),
                    'collapsedPriorityLabel' => __('Priority:', 'bertanex-flow'),
                    'confirmRemove'    => __('Remove this rule?', 'bertanex-flow'),
                    'confirmRemoveAdv' => __('Remove this rule from the document?', 'bertanex-flow'),
                    'ruleSection'      => __('Rule', 'bertanex-flow'),
                    'triggerHeading'   => __('When to run', 'bertanex-flow'),
                    'triggerNone'      => __('Evaluate without event filter', 'bertanex-flow'),
                    'triggerNoneHint'  => __('Runs whenever rules are checked', 'bertanex-flow'),
                    'triggerEvents'    => __('On automation events', 'bertanex-flow'),
                    'triggerSchedule'  => __('On a schedule (cron)', 'bertanex-flow'),
                    'triggerScheduleHint' => __('WordPress cron recurrence', 'bertanex-flow'),
                    'customEventsLabel'=> __('Additional event names (comma-separated)', 'bertanex-flow'),
                    'eventsSelectedCount' => __('%1$s of %2$s events selected', 'bertanex-flow'),
                    'eventsSelectedHint' => __('Rule runs when any selected event fires.', 'bertanex-flow'),
                    'eventsNoneSelectedHint' => __('Select at least one event or add custom names below.', 'bertanex-flow'),
                    'selectAllEvents'  => __('Select all', 'bertanex-flow'),
                    'clearEvents'      => __('Clear', 'bertanex-flow'),
                    'eventsGridLabel'  => __('Automation events', 'bertanex-flow'),
                    'eventsCatalogEmpty' => __('No automation events are registered yet. Add custom event names below.', 'bertanex-flow'),
                    'hookLabel'        => __('Cron hook', 'bertanex-flow'),
                    'recurrenceLabel'  => __('Recurrence', 'bertanex-flow'),
                    'conditionsHeading'=> __('Conditions (all must match)', 'bertanex-flow'),
                    'fieldCol'         => __('Field', 'bertanex-flow'),
                    'opCol'            => __('Operator', 'bertanex-flow'),
                    'patternCol'       => __('Pattern (regex)', 'bertanex-flow'),
                    'valueCol'         => __('Value (JSON)', 'bertanex-flow'),
                    'valueColShort'    => __('Value', 'bertanex-flow'),
                    'customField'      => __('Custom…', 'bertanex-flow'),
                    'fieldPlaceholder' => __('State field key', 'bertanex-flow'),
                    'removeRow'        => __('Remove', 'bertanex-flow'),
                    'addCondition'     => __('Add condition', 'bertanex-flow'),
                    'actionsHeading'   => __('Actions', 'bertanex-flow'),
                    'actionType'       => __('Type', 'bertanex-flow'),
                    'templateId'       => __('template_id', 'bertanex-flow'),
                    'delaySeconds'     => __('delay_seconds', 'bertanex-flow'),
                    'delayLabel'       => __('Delay', 'bertanex-flow'),
                    'delayRunImmediately' => __('Run immediately when the rule matches', 'bertanex-flow'),
                    'delayModeImmediateHint' => __('When the rule matches', 'bertanex-flow'),
                    'delayModeSchedule'    => __('Schedule', 'bertanex-flow'),
                    'delayModeScheduleHint' => __('Pick date and time after now', 'bertanex-flow'),
                    'delayTimeNoneShort'   => __('No extra delay', 'bertanex-flow'),
                    'delayClockOnDay'      => __('At time on that day', 'bertanex-flow'),
                    'delayClockHint'       => __('Combine with the date above — e.g. in 1 day at 11:00', 'bertanex-flow'),
                    'delayExtraTimeHint'   => __('Optional extra wait after the date and clock time above.', 'bertanex-flow'),
                    'delayAfterMatch'      => __('after the rule matches', 'bertanex-flow'),
                    'delayPreviewFromMatch' => __('%1$s %2$s', 'bertanex-flow'),
                    'delayPreviewDaysAt'   => __('In %1$s at %2$s %3$s', 'bertanex-flow'),
                    'delayPreviewDaysOnly' => __('In %1$s %2$s', 'bertanex-flow'),
                    'delayPreviewDateAt'   => __('On %1$s at %2$s %3$s', 'bertanex-flow'),
                    'delayPreviewOnDate'   => __('On %1$s %2$s', 'bertanex-flow'),
                    'delayPreviewTodayAt'  => __('On the same day at %1$s %2$s', 'bertanex-flow'),
                    'delayPreviewPlusExtra' => __('(plus %s more)', 'bertanex-flow'),
                    'delaySummaryDaysAt'   => __('In %1$s at %2$s', 'bertanex-flow'),
                    'delaySummaryAt'       => __('At %s', 'bertanex-flow'),
                    'delayDateLegend'  => __('Date', 'bertanex-flow'),
                    'delayDateToday'   => __('Today', 'bertanex-flow'),
                    'delayDateInDays'  => __('In', 'bertanex-flow'),
                    'delayDateDaysLabel' => __('days', 'bertanex-flow'),
                    'delayDateOn'      => __('On', 'bertanex-flow'),
                    'delayTimeLegend'  => __('Time', 'bertanex-flow'),
                    'delayTimeNone'    => __('Same time (date offset only)', 'bertanex-flow'),
                    'delayTimeSeconds' => __('seconds', 'bertanex-flow'),
                    'delayTimeMinutes' => __('minutes', 'bertanex-flow'),
                    'delayTimeHours'   => __('hours', 'bertanex-flow'),
                    'delayTimeAt'      => __('At', 'bertanex-flow'),
                    'delayPreviewLabel' => __('Preview:', 'bertanex-flow'),
                    'delayImmediate'   => __('Immediately when the rule matches', 'bertanex-flow'),
                    'delayImmediateShort' => __('Immediate', 'bertanex-flow'),
                    'delayPreviewAt'   => __('Scheduled at %1$s %2$s (about %3$s from now)', 'bertanex-flow'),
                    'delayPreviewSeconds' => __('About %s seconds after the rule matches', 'bertanex-flow'),
                    'delayPreviewRelative' => __('About %s after the rule matches', 'bertanex-flow'),
                    'delayOneDay'      => __('1 day', 'bertanex-flow'),
                    'delayDays'        => __('%s days', 'bertanex-flow'),
                    'delayOneHour'     => __('1 hour', 'bertanex-flow'),
                    'delayHours'       => __('%s hours', 'bertanex-flow'),
                    'delayOneMinute'   => __('1 minute', 'bertanex-flow'),
                    'delayMinutes'     => __('%s minutes', 'bertanex-flow'),
                    'delayOneSecond'   => __('1 second', 'bertanex-flow'),
                    'delaySecondsUnit' => __('%s seconds', 'bertanex-flow'),
                    'jalaliDay'        => __('Day', 'bertanex-flow'),
                    'jalaliMonth'      => __('Month', 'bertanex-flow'),
                    'jalaliYear'       => __('Year', 'bertanex-flow'),
                    'paramsJson'       => __('params (JSON object)', 'bertanex-flow'),
                    'removeAction'     => __('Remove action', 'bertanex-flow'),
                    'addAction'        => __('Add action', 'bertanex-flow'),
                    'regexPatternPlaceholder' => __('/^...$/', 'bertanex-flow'),
                    'valueJsonPlaceholder' => __('Examples: true, false, 3, "text", []', 'bertanex-flow'),
                    'valueSelectPlaceholder' => __('Select a value…', 'bertanex-flow'),
                    'customValue'          => __('Custom value (JSON)…', 'bertanex-flow'),
                    'conditionsLimitReached' => __('Maximum number of conditions reached (%s).', 'bertanex-flow'),
                    'actionsLimitReached'    => __('Maximum number of actions reached (%s).', 'bertanex-flow'),
                    'templateSelect'         => __('SMS template', 'bertanex-flow'),
                    'templateCustom'         => __('Custom template ID…', 'bertanex-flow'),
                    'templatePreview'        => __('Preview:', 'bertanex-flow'),
                    'templateConfigHint'     => __('Edit message text under Message patterns.', 'bertanex-flow'),
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
        return ConsumerRegistry::instance()->conditionFieldDefinitions();
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
        return $slug;
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
            'eq' => __('Equals', 'bertanex-flow'),
            'neq' => __('Not equals', 'bertanex-flow'),
            'gt' => __('Greater than', 'bertanex-flow'),
            'gte' => __('Greater than or equal', 'bertanex-flow'),
            'lt' => __('Less than', 'bertanex-flow'),
            'lte' => __('Less than or equal', 'bertanex-flow'),
            'in' => __('In list', 'bertanex-flow'),
            'not_in' => __('Not in list', 'bertanex-flow'),
            'empty' => __('Is empty', 'bertanex-flow'),
            'not_empty' => __('Is not empty', 'bertanex-flow'),
            'truthy' => __('Is truthy', 'bertanex-flow'),
            'falsy' => __('Is falsy', 'bertanex-flow'),
            'regex' => __('Matches regex', 'bertanex-flow'),
            'starts_with' => __('Starts with', 'bertanex-flow'),
            'ends_with' => __('Ends with', 'bertanex-flow'),
            'contains' => __('Contains', 'bertanex-flow'),
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
            'send_sms' => __('Send SMS', 'bertanex-flow'),
            'send_email' => __('Send email', 'bertanex-flow'),
            'send_discount' => __('Send discount', 'bertanex-flow'),
            'webhook' => __('Webhook', 'bertanex-flow'),
            'log' => __('Log', 'bertanex-flow'),
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
            'hourly' => __('Hourly', 'bertanex-flow'),
            'twicedaily' => __('Twice daily', 'bertanex-flow'),
            'daily' => __('Daily', 'bertanex-flow'),
            'weekly' => __('Weekly', 'bertanex-flow'),
            default => $slug,
        };
    }

    public function onAdminMenu(): void
    {
        I18n::loadTextdomain();

        add_menu_page(
            __('Automation Rules', 'bertanex-flow'),
            __('Flow', 'bertanex-flow'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render'],
            \BERTANEX_FLOW_PLUGIN_URL . 'assets/admin/bertanex-flow-logo.svg',
            61
        );
    }

    public function render(): void
    {
        I18n::loadTextdomain();

        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'bertanex-flow'));
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
        $path = \BERTANEX_FLOW_PLUGIN_DIR . ltrim($relativePath, '/');
        $mtime = @filemtime($path);

        return $mtime !== false
            ? (string) $mtime
            : (\defined('BERTANEX_FLOW_VERSION') ? (string) \BERTANEX_FLOW_VERSION : '0');
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
