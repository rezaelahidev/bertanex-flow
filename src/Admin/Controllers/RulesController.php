<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Admin\Controllers;

use GrowthOrchestrator\Admin\Models\RulesDocumentModel;
use GrowthOrchestrator\Admin\ViewModels\RulesPageContext;
use GrowthOrchestrator\Admin\Views\RulesPageView;
use GrowthOrchestrator\Loader\HooksLoader;

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

    private function errorCodeForThrowable(\Throwable $e): string
    {
        if ($e instanceof \InvalidArgumentException) {
            return 'gro_rules_invalid_json';
        }

        $msg = strtolower($e->getMessage());

        return str_contains($msg, 'json') ? 'gro_rules_invalid_json' : 'gro_rules_save_failed';
    }
}
