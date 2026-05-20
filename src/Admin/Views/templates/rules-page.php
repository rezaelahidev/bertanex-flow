<?php

declare(strict_types=1);

/**
 * Template: Automation rules editor (JSON + visual builder overview).
 *
 * @var \GrowthOrchestrator\Admin\ViewModels\RulesPageContext $context
 */

defined('ABSPATH') || exit;

$configGroup = \GrowthOrchestrator\Admin\Models\RulesAdminConfig::SETTINGS_GROUP;
$ruleCount   = $context->parseError === null ? count($context->definitions) : 0;
$enabledCount = 0;
if ($context->parseError === null) {
    foreach ($context->definitions as $rule) {
        if ($rule->enabled) {
            ++$enabledCount;
        }
    }
}

settings_errors('gro_rules');
settings_errors($configGroup);

if ($context->showSuccessNotice) {
    echo '<div class="notice notice-success is-dismissible"><p>' .
        esc_html__('Rules saved.', 'growth-orchestrator') .
        '</p></div>';
}

if (isset($_GET['settings-updated'])) {
    echo '<script>document.documentElement.dataset.groOpenRulesTab = "config";</script>';
}

?>
<div class="wrap gro-rules-admin">
    <header class="gro-page-header">
        <div class="gro-page-header__titles">
            <h1 class="gro-page-header__title"><?php echo esc_html__('Automation Rules', 'growth-orchestrator'); ?></h1>
            <p class="gro-page-header__lede">
                <?php echo esc_html__(
                    'Design IF / THEN automations, manage SMS delivery, and tune the visual builder from one workspace.',
                    'growth-orchestrator'
                ); ?>
            </p>
        </div>
        <?php if ($context->parseError === null) : ?>
            <dl class="gro-page-stats" aria-label="<?php echo esc_attr__('Rules summary', 'growth-orchestrator'); ?>">
                <div class="gro-page-stats__item">
                    <dt><?php echo esc_html__('Total rules', 'growth-orchestrator'); ?></dt>
                    <dd><?php echo esc_html((string) $ruleCount); ?></dd>
                </div>
                <div class="gro-page-stats__item gro-page-stats__item--accent">
                    <dt><?php echo esc_html__('Enabled', 'growth-orchestrator'); ?></dt>
                    <dd><?php echo esc_html((string) $enabledCount); ?></dd>
                </div>
            </dl>
        <?php endif; ?>
    </header>

    <?php if ($context->parseError !== null) : ?>
        <div class="gro-alert gro-alert--error" data-gro-rules-parse-error="1" role="alert">
            <strong><?php echo esc_html__('JSON error (preview only; saved value unchanged):', 'growth-orchestrator'); ?></strong>
            <p><?php echo esc_html($context->parseError); ?></p>
            <?php if ($context->hasDraftTransient) : ?>
                <p><?php echo esc_html__('Fix the JSON below and save again.', 'growth-orchestrator'); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($context->parseError === null && $context->definitions !== []) : ?>
        <section class="gro-overview" aria-label="<?php echo esc_attr__('Rules overview', 'growth-orchestrator'); ?>">
            <ul class="gro-overview-list">
                <?php foreach ($context->definitions as $rule) : ?>
                    <li class="gro-overview-card">
                        <div class="gro-overview-card__main">
                            <code class="gro-overview-card__id"><?php echo esc_html($rule->id); ?></code>
                            <span class="gro-overview-card__label"><?php echo esc_html($rule->label ?? __('Untitled', 'growth-orchestrator')); ?></span>
                        </div>
                        <div class="gro-overview-card__meta">
                            <span class="gro-pill <?php echo $rule->enabled ? 'gro-pill--success' : 'gro-pill--muted'; ?>">
                                <?php echo $rule->enabled
                                    ? esc_html__('Enabled', 'growth-orchestrator')
                                    : esc_html__('Disabled', 'growth-orchestrator'); ?>
                            </span>
                            <span class="gro-overview-card__priority">
                                <?php
                                echo esc_html(
                                    sprintf(
                                        /* translators: %d: priority number */
                                        __('Priority %d', 'growth-orchestrator'),
                                        (int) $rule->priority
                                    )
                                );
                                ?>
                            </span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php elseif ($context->parseError === null) : ?>
        <div class="gro-empty-state">
            <p><?php echo esc_html__('No rules in this document yet. Use the visual builder to add your first rule.', 'growth-orchestrator'); ?></p>
        </div>
    <?php endif; ?>

    <section class="gro-workspace" aria-labelledby="gro-workspace-heading">
        <div class="gro-workspace__head">
            <h2 id="gro-workspace-heading" class="gro-workspace__title"><?php echo esc_html__('Rule editor', 'growth-orchestrator'); ?></h2>
            <p class="gro-workspace__hint">
                <?php echo esc_html__(
                    'Visual builder for common flows, raw JSON for advanced condition groups, and configuration for layout and SMS.',
                    'growth-orchestrator'
                ); ?>
            </p>
        </div>

        <nav class="gro-tabs" role="tablist" aria-label="<?php echo esc_attr__('Rules editor mode', 'growth-orchestrator'); ?>">
            <button type="button" role="tab" class="gro-tabs__tab gro-tabs__tab--active" data-gro-tab="visual" aria-selected="true" id="gro-rules-tab-visual">
                <span class="gro-tabs__label"><?php echo esc_html__('Visual builder', 'growth-orchestrator'); ?></span>
            </button>
            <button type="button" role="tab" class="gro-tabs__tab" data-gro-tab="raw" aria-selected="false" id="gro-rules-tab-raw">
                <span class="gro-tabs__label"><?php echo esc_html__('Raw JSON', 'growth-orchestrator'); ?></span>
            </button>
            <button type="button" role="tab" class="gro-tabs__tab" data-gro-tab="config" aria-selected="false" id="gro-rules-tab-config">
                <span class="gro-tabs__label"><?php echo esc_html__('Configuration', 'growth-orchestrator'); ?></span>
            </button>
        </nav>

        <div class="gro-workspace__body">
            <div
                id="gro-rules-panel-config"
                class="gro-panel gro-panel--config"
                role="tabpanel"
                tabindex="0"
                aria-labelledby="gro-rules-tab-config"
                aria-hidden="true"
                hidden
            >
                <form method="post" action="<?php echo esc_url(admin_url('options.php')); ?>" class="gro-config-form" autocomplete="off">
                    <?php settings_fields($configGroup); ?>

                    <div class="gro-config-grid">
                        <div class="gro-settings-card">
                            <header class="gro-settings-card__header">
                                <h3 class="gro-settings-card__title"><?php echo esc_html__('Visual builder', 'growth-orchestrator'); ?></h3>
                                <p class="gro-settings-card__subtitle"><?php echo esc_html__('List limits and column layout', 'growth-orchestrator'); ?></p>
                            </header>
                            <div class="gro-settings-card__body">
                                <?php \GrowthOrchestrator\Admin\Views\SettingsSectionRenderer::render($configGroup, 'gro_rules_editor_panel_section'); ?>
                            </div>
                        </div>

                        <div class="gro-settings-card gro-settings-card--sms">
                            <header class="gro-settings-card__header">
                                <h3 class="gro-settings-card__title"><?php echo esc_html__('SMS delivery', 'growth-orchestrator'); ?></h3>
                                <p class="gro-settings-card__subtitle"><?php echo esc_html__('Provider and Melipayamak credentials', 'growth-orchestrator'); ?></p>
                            </header>
                            <div class="gro-settings-card__body">
                                <?php \GrowthOrchestrator\Admin\Views\SettingsSectionRenderer::render($configGroup, 'gro_sms_provider_section'); ?>
                                <?php \GrowthOrchestrator\Admin\Views\SettingsSectionRenderer::render($configGroup, 'gro_sms_melipayamak_section'); ?>
                            </div>
                        </div>
                    </div>

                    <footer class="gro-config-form__footer">
                        <?php submit_button(__('Save configuration', 'growth-orchestrator'), 'primary', 'submit', false); ?>
                        <?php if (\GrowthOrchestrator\Support\FeatureFlags::twilioEnabled()) : ?>
                            <p class="gro-config-form__note description">
                                <?php echo esc_html__(
                                    'Twilio can also be set via wp-config (GROWTH_ORCH_TWILIO_*) or the gro_twilio_sms_config filter.',
                                    'growth-orchestrator'
                                ); ?>
                            </p>
                        <?php endif; ?>
                    </footer>
                </form>
            </div>

            <form method="post" action="<?php echo esc_url($context->formActionUrl); ?>" id="gro-rules-editor-form" class="gro-editor-form">
                <?php wp_nonce_field('gro_rules_save'); ?>
                <input type="hidden" name="gro_rules_save" value="1" />

                <div id="gro-rules-panel-visual" class="gro-panel gro-panel--visual" role="tabpanel" tabindex="0" aria-labelledby="gro-rules-tab-visual" aria-hidden="false">
                    <div id="gro-rules-visual-root" class="gro-rules-visual-mount"></div>
                    <noscript>
                        <p class="gro-alert gro-alert--warn"><?php echo esc_html__('Enable JavaScript to use the visual rule builder.', 'growth-orchestrator'); ?></p>
                    </noscript>
                </div>

                <div id="gro-rules-panel-raw" class="gro-panel gro-panel--raw" role="tabpanel" tabindex="0" aria-labelledby="gro-rules-tab-raw" aria-hidden="true" hidden>
                    <label class="gro-raw-json-label" for="gro_rules_json">
                        <span class="gro-raw-json-label__title"><?php echo esc_html__('Rules document (JSON)', 'growth-orchestrator'); ?></span>
                        <span class="gro-raw-json-label__hint"><?php echo esc_html__('Full document; validate before saving.', 'growth-orchestrator'); ?></span>
                    </label>
                    <textarea
                        name="gro_rules_json"
                        id="gro_rules_json"
                        rows="24"
                        class="large-text code gro-rules-json-textarea"
                        spellcheck="false"
                    ><?php echo esc_textarea($context->textareaValue); ?></textarea>
                </div>

                <footer class="gro-editor-form__footer">
                    <?php submit_button(__('Save all rules', 'growth-orchestrator'), 'primary', 'gro_rules_save_all', true, ['id' => 'gro-rules-save-all', 'disabled' => 'disabled']); ?>
                </footer>
            </form>
        </div>
    </section>
</div>
