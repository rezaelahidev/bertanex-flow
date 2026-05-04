<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Admin;

use GrowthOrchestrator\Loader\HooksLoader;
use GrowthOrchestrator\Rule\JsonRulesParser;

/**
 * Growth Orchestrator rules editor: authoritative storage is the `{prefix}growth_orchestrator_rules` table.
 */
final class RulesAdminPage
{
    private const PAGE_SLUG = 'growth-orchestrator';

    public function __construct(
        private readonly RulesDocumentService $documents,
        private readonly JsonRulesParser $parser = new JsonRulesParser()
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
            $this->documents->saveValidatedDocument($raw);
        } catch (\Throwable $e) {
            $code = $e instanceof \InvalidArgumentException || str_contains(strtolower($e->getMessage()), 'json')
                ? 'gro_rules_invalid_json'
                : 'gro_rules_save_failed';

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
                'gro_rules_admin_draft_' . get_current_user_id(),
                $raw,
                HOUR_IN_SECONDS
            );

            return;
        }

        delete_transient('gro_rules_admin_draft_' . get_current_user_id());

        $redirect = add_query_arg(
            [
                'page'    => self::PAGE_SLUG,
                'updated' => '1',
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect);
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

        $draftTransient = get_transient('gro_rules_admin_draft_' . get_current_user_id());
        if (is_string($draftTransient) && $draftTransient !== '') {
            $textareaValue = $draftTransient;
        } else {
            $textareaValue = $this->documents->getEditorJson();
        }

        $parseError = null;
        $parsedRows = [];

        try {
            $parsedRows = $this->parser->parse($textareaValue);
        } catch (\Throwable $e) {
            $parseError = $e->getMessage();
        }

        settings_errors('gro_rules');

        if (isset($_GET['updated']) && (string) $_GET['updated'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>' .
                esc_html__('Rules saved.', 'growth-orchestrator') .
                '</p></div>';
        }

        ?>

        <div class="wrap gro-rules-admin">
            <h1><?php echo esc_html__('Growth Orchestrator — Rules', 'growth-orchestrator'); ?></h1>
            <p class="description">
                <?php echo esc_html__(
                    'Rules are stored in the growth_orchestrator_rules database table. When the table is empty, this editor mirrors the legacy option/plugin JSON until first save hydrates persistence.',
                    'growth-orchestrator'
                ); ?>
            </p>

            <?php if ($parseError !== null) : ?>
                <div class="notice notice-error">
                    <p>
                        <strong><?php echo esc_html__('JSON error (preview only; saved value unchanged):', 'growth-orchestrator'); ?></strong>
                        <?php echo esc_html($parseError); ?>
                    </p>
                    <?php if (is_string($draftTransient) && $draftTransient !== '') : ?>
                        <p><?php echo esc_html__('Fix the JSON below and save again.', 'growth-orchestrator'); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <h2><?php echo esc_html__('Rules overview', 'growth-orchestrator'); ?></h2>
            <?php if ($parseError === null && $parsedRows !== []) : ?>
                <table class="widefat striped" style="max-width: 960px;">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('ID', 'growth-orchestrator'); ?></th>
                            <th><?php echo esc_html__('Label', 'growth-orchestrator'); ?></th>
                            <th><?php echo esc_html__('Enabled', 'growth-orchestrator'); ?></th>
                            <th><?php echo esc_html__('Priority', 'growth-orchestrator'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($parsedRows as $rule) : ?>
                            <tr>
                                <td><code><?php echo esc_html($rule->id); ?></code></td>
                                <td><?php echo esc_html($rule->label ?? '—'); ?></td>
                                <td><?php echo $rule->enabled ? '✓' : '—'; ?></td>
                                <td><?php echo (string) (int) $rule->priority; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php elseif ($parseError === null) : ?>
                <p><?php echo esc_html__('No rules in this document.', 'growth-orchestrator'); ?></p>
            <?php endif; ?>

            <hr style="margin: 24px 0;" />

            <h2><?php echo esc_html__('Rules JSON', 'growth-orchestrator'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG)); ?>">
                <?php wp_nonce_field('gro_rules_save'); ?>
                <input type="hidden" name="gro_rules_save" value="1" />
                <p>
                    <label for="gro_rules_json" class="screen-reader-text">
                        <?php echo esc_html__('Rules JSON', 'growth-orchestrator'); ?>
                    </label>
                    <textarea
                        name="gro_rules_json"
                        id="gro_rules_json"
                        rows="24"
                        class="large-text code"
                        style="width: 100%; max-width: 960px; font-family: monospace;"
                        spellcheck="false"
                    ><?php echo esc_textarea($textareaValue); ?></textarea>
                </p>
                <?php submit_button(__('Save rules', 'growth-orchestrator')); ?>
            </form>
        </div>
        <?php
    }
}
