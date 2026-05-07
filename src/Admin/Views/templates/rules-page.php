<?php

declare(strict_types=1);

/**
 * Template: Growth Orchestrator → Rules (JSON editor + overview).
 *
 * @var \GrowthOrchestrator\Admin\ViewModels\RulesPageContext $context
 */

defined('ABSPATH') || exit;

settings_errors('gro_rules');

if ($context->showSuccessNotice) {
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

    <?php if ($context->parseError !== null) : ?>
        <div class="notice notice-error">
            <p>
                <strong><?php echo esc_html__('JSON error (preview only; saved value unchanged):', 'growth-orchestrator'); ?></strong>
                <?php echo esc_html($context->parseError); ?>
            </p>
            <?php if ($context->hasDraftTransient) : ?>
                <p><?php echo esc_html__('Fix the JSON below and save again.', 'growth-orchestrator'); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <h2><?php echo esc_html__('Rules overview', 'growth-orchestrator'); ?></h2>
    <?php if ($context->parseError === null && $context->definitions !== []) : ?>
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
                <?php foreach ($context->definitions as $rule) : ?>
                    <tr>
                        <td><code><?php echo esc_html($rule->id); ?></code></td>
                        <td><?php echo esc_html($rule->label ?? '—'); ?></td>
                        <td><?php echo $rule->enabled ? '✓' : '—'; ?></td>
                        <td><?php echo (string) (int) $rule->priority; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php elseif ($context->parseError === null) : ?>
        <p><?php echo esc_html__('No rules in this document.', 'growth-orchestrator'); ?></p>
    <?php endif; ?>

    <hr style="margin: 24px 0;" />

    <h2><?php echo esc_html__('Rules JSON', 'growth-orchestrator'); ?></h2>
    <form method="post" action="<?php echo esc_url($context->formActionUrl); ?>">
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
            ><?php echo esc_textarea($context->textareaValue); ?></textarea>
        </p>
        <?php submit_button(__('Save rules', 'growth-orchestrator')); ?>
    </form>
</div>
