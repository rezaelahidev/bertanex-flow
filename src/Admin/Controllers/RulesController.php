<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Admin\Controllers;

use GrowthOrchestrator\Admin\Models\RulesDocumentModel;
use GrowthOrchestrator\Admin\ViewModels\RulesPageContext;
use GrowthOrchestrator\Admin\Views\RulesPageView;
use GrowthOrchestrator\Event\EventType;
use GrowthOrchestrator\Loader\HooksLoader;
use GrowthOrchestrator\Rule\BuiltinConditionOperators;

/**
 * Controller: wires WordPress hooks, delegates persistence to {@see RulesDocumentModel}, renders via {@see RulesPageView}.
 */
final class RulesController
{
    public const PAGE_SLUG = 'growth-orchestrator';

    private const DRAFT_TRANSIENT_PREFIX = 'gro_rules_admin_draft_';

    public function __construct(
        private readonly RulesDocumentModel $model,
        private readonly RulesPageView $view
    ) {
    }

    public function register(HooksLoader $hooks): void
    {
        $hooks->add_action($this, 'onAdminMenu', 'admin_menu', 9, 0);
        $hooks->add_action($this, 'onAdminInit', 'admin_init', 1, 0);
        $hooks->add_action($this, 'enqueueAssets', 'admin_enqueue_scripts', 10, 1);
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

    public function enqueueAssets(string $hook_suffix): void
    {
        if ($hook_suffix !== 'toplevel_page_' . self::PAGE_SLUG) {
            return;
        }

        if (! \defined('GROWTH_ORCH_PLUGIN_URL') || ! \defined('GROWTH_ORCH_PLUGIN_DIR')) {
            return;
        }

        $base = GROWTH_ORCH_PLUGIN_URL . 'assets/admin/';

        $cssVer = self::assetFileVersion('assets/admin/rules-visual-editor.css');
        wp_enqueue_style(
            'gro-rules-visual-editor',
            $base . 'rules-visual-editor.css',
            [],
            $cssVer
        );

        if (is_rtl()) {
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
            [],
            self::assetFileVersion('assets/admin/rules-visual-editor.bundle.js'),
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
        $suggestedFields = apply_filters(
            'gro_rules_editor_suggested_condition_fields',
            [
                'profile_completed',
                'has_product',
                'has_subscription',
                'subscription_status',
                'subscription_days_until_expiry',
            ]
        );

        wp_localize_script(
            'gro-rules-visual-editor',
            'groRulesEditor',
            [
                'defaultVersion'   => '1.0.0',
                'recurrences'      => $recurrences,
                'events'           => $events,
                'operators'        => $operators,
                'operatorLabels'   => self::operatorLabelsForSlugs($operators),
                'actionTypes'      => $actionTypes,
                'actionTypeLabels' => self::actionTypeLabelsForSlugs($actionTypes),
                'recurrenceLabels' => self::recurrenceLabelsForSlugs($recurrences),
                'suggestedFields'  => $suggestedFields,
                'strings'          => [
                    'errInvalidRoot'   => __('JSON must be an object with a "rules" array.', 'growth-orchestrator'),
                    'noRules'          => __('No rules. Add one or paste JSON in the Raw JSON tab.', 'growth-orchestrator'),
                    'addRule'          => __('Add rule', 'growth-orchestrator'),
                    'versionLabel'     => __('Format version', 'growth-orchestrator'),
                    'advancedBadge'    => __('Advanced', 'growth-orchestrator'),
                    'advancedDesc'     => __('This rule uses triggers or nested conditions that the visual editor does not edit. Change it under Raw JSON; the card will reload after a successful parse.', 'growth-orchestrator'),
                    'placeholderLabel' => __('Label (optional)', 'growth-orchestrator'),
                    'untitledRuleSummary' => __('Untitled rule', 'growth-orchestrator'),
                    'ruleDetailsHeading'   => __('Rule details', 'growth-orchestrator'),
                    'enabledLabel'     => __('Enabled', 'growth-orchestrator'),
                    'priorityLabel'    => __('Priority', 'growth-orchestrator'),
                    'ruleIdShort'      => __('id', 'growth-orchestrator'),
                    'dupRule'          => __('Duplicate', 'growth-orchestrator'),
                    'up'               => __('Move up', 'growth-orchestrator'),
                    'down'             => __('Move down', 'growth-orchestrator'),
                    'removeRule'       => __('Remove rule', 'growth-orchestrator'),
                    'removeRuleShort'  => __('Remove', 'growth-orchestrator'),
                    'accordionExpand'   => __('Expand settings for rule %s', 'growth-orchestrator'),
                    'accordionCollapse' => __('Collapse settings for rule %s', 'growth-orchestrator'),
                    'accordionExpandCondition' => __('Expand condition %s', 'growth-orchestrator'),
                    'accordionCollapseCondition' => __('Collapse condition %s', 'growth-orchestrator'),
                    'accordionExpandAction' => __('Expand action %s', 'growth-orchestrator'),
                    'accordionCollapseAction' => __('Collapse action %s', 'growth-orchestrator'),
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
                    'paramsJson'       => __('params (JSON object)', 'growth-orchestrator'),
                    'removeAction'     => __('Remove action', 'growth-orchestrator'),
                    'addAction'        => __('Add action', 'growth-orchestrator'),
                    'regexPatternPlaceholder' => __('/^...$/', 'growth-orchestrator'),
                    'valueJsonPlaceholder' => __('Examples: true, false, 3, "text", []', 'growth-orchestrator'),
                ],
            ]
        );
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
            EventType::InstallmentDue        => __('Installment due', 'growth-orchestrator'),
            EventType::InstallmentOverdue    => __('Installment overdue', 'growth-orchestrator'),
            EventType::Login                 => __('User login', 'growth-orchestrator'),
        };
    }

    public function onAdminMenu(): void
    {
        add_menu_page(
            __('Growth Orchestrator', 'growth-orchestrator'),
            __('Growth Orchestrator', 'growth-orchestrator'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render'],
            'dashicons-clipboard',
            58
        );

        add_submenu_page(
            self::PAGE_SLUG,
            __('Rules', 'growth-orchestrator'),
            __('Rules', 'growth-orchestrator'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render']
        );
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'growth-orchestrator'));
        }

        $draft = get_transient(self::DRAFT_TRANSIENT_PREFIX . get_current_user_id());
        $textareaValue = (is_string($draft) && $draft !== '')
            ? $draft
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
