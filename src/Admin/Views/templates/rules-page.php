<?php

declare(strict_types=1);

/**
 * Template: Automation rules editor (automations, patterns, configuration).
 *
 * @var \BertanexFlow\Admin\ViewModels\RulesPageContext $context
 */

defined('ABSPATH') || exit;

$configGroup    = \BertanexFlow\Admin\Models\RulesAdminConfig::SETTINGS_GROUP;
$patternsGroup  = \BertanexFlow\Admin\Models\RulesAdminConfig::PATTERNS_SETTINGS_GROUP;
$panelKeys = ['config', 'reset', 'patterns', 'email', 'runs', 'visual'];
$activeTab = 'config';
if (isset($_GET['gro_open_tab'])) {
    $requested = sanitize_key((string) $_GET['gro_open_tab']);
    if (in_array($requested, $panelKeys, true)) {
        $activeTab = $requested;
        update_user_meta(get_current_user_id(), \BertanexFlow\Admin\Controllers\RulesController::USER_META_PANEL, $activeTab);
    }
}

settings_errors('gro_rules');
settings_errors($configGroup);
settings_errors($patternsGroup);

if ($context->showSuccessNotice) {
    echo '<div class="notice notice-success is-dismissible"><p>' .
        esc_html__('Rules saved.', 'bertanex-flow') .
        '</p></div>';
}

if (isset($_GET['gro_reset']) && (string) $_GET['gro_reset'] === '1') {
    echo '<div class="notice notice-success is-dismissible"><p>' .
        esc_html__(
            'All customizations were reset to plugin defaults. Rules, message patterns, panel layout, and SMS settings were restored.',
            'bertanex-flow'
        ) .
        '</p></div>';
}

if (isset($_GET['gro_reset']) && (string) $_GET['gro_reset'] === 'error') {
    echo '<div class="notice notice-error is-dismissible"><p>' .
        esc_html__(
            'Could not reset to defaults. Check that the default rules file exists and try again.',
            'bertanex-flow'
        ) .
        '</p></div>';
}

echo '<script>document.documentElement.dataset.groOpenRulesTab = "' . esc_js($activeTab) . '";</script>';

?>
<div class="wrap gro-rules-admin<?php echo is_rtl() ? ' gro-rules-admin--rtl' : ''; ?>">
    <div class="edit-site-layout">
    <div class="edit-site-layout__content">
    <div class="edit-site-layout__sidebar-region">
        <div class="edit-site-layout__sidebar">
            <div class="edit-site-sidebar__screen-wrapper">
                <div class="edit-site-sidebar-navigation-screen">
                    <div class="edit-site-sidebar-navigation-screen__title-icon">
                        <a class="gro-flow-logo" href="<?php echo esc_url(admin_url()); ?>">
                            <img
                                src="<?php echo esc_url(\BERTANEX_FLOW_PLUGIN_URL . 'assets/admin/bertanex-flow-logo.svg'); ?>"
                                width="24"
                                height="24"
                                alt="<?php echo esc_attr__('Bertanex Flow', 'bertanex-flow'); ?>"
                            />
                        </a>
                        <h1 class="edit-site-sidebar-navigation-screen__title"><?php echo esc_html__('Flow', 'bertanex-flow'); ?></h1>
                    </div>
                    <nav class="gro-tabs edit-site-sidebar-navigation-screen__main" role="tablist" aria-label="<?php echo esc_attr__('Rules editor mode', 'bertanex-flow'); ?>">
                        <?php
                        $nav = [
                            'visual' => __('Automations', 'bertanex-flow'),
                            'patterns' => __('SMS patterns', 'bertanex-flow'),
                            'email' => __('Email templates', 'bertanex-flow'),
                            'runs' => __('Runs', 'bertanex-flow'),
                            'config' => __('Configuration', 'bertanex-flow'),
                            'reset' => __('Reset', 'bertanex-flow'),
                        ];
                        foreach ($nav as $tab => $navLabel) :
                            $on = $activeTab === $tab;
                            ?>
                            <button type="button" role="tab" class="components-item edit-site-sidebar-navigation-item" data-gro-tab="<?php echo esc_attr($tab); ?>" aria-selected="<?php echo $on ? 'true' : 'false'; ?>" <?php echo $on ? 'aria-current="true"' : ''; ?> id="gro-rules-tab-<?php echo esc_attr($tab); ?>">
                                <?php echo esc_html($navLabel); ?>
                            </button>
                        <?php endforeach; ?>
                    </nav>
                </div>
                <div class="edit-site-save-hub">
                    <button
                        type="button"
                        class="components-button edit-site-save-hub__button"
                        id="gro-sidebar-save"
                        aria-disabled="true"
                        data-label-save="<?php echo esc_attr__('Save', 'bertanex-flow'); ?>"
                        data-label-saving="<?php echo esc_attr__('Saving…', 'bertanex-flow'); ?>"
                        data-label-saved="<?php echo esc_attr__('Saved', 'bertanex-flow'); ?>"
                    ><?php echo esc_html__('Saved', 'bertanex-flow'); ?></button>
                </div>
            </div>
        </div>
    </div>
    <div class="edit-site-layout__canvas-container">
    <div class="edit-site-layout__canvas">
    <div class="edit-site-resizable-frame__inner-content gro-editor-main">
    <?php if ($context->parseError !== null) : ?>
        <div class="gro-alert gro-alert--error" data-gro-rules-parse-error="1" role="alert">
            <strong><?php echo esc_html__('JSON error (preview only; saved value unchanged):', 'bertanex-flow'); ?></strong>
            <p><?php echo esc_html($context->parseError); ?></p>
            <?php if ($context->hasDraftTransient) : ?>
                <p><?php echo esc_html__('Use Reset to defaults on the Reset tab, or restore valid rules another way.', 'bertanex-flow'); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <section class="gro-workspace">
        <div class="gro-workspace__body">
            <div
                id="gro-rules-panel-patterns"
                data-gro-panel="patterns"
                class="gro-panel gro-panel--patterns"
                role="tabpanel"
                tabindex="0"
                aria-labelledby="gro-rules-tab-patterns"
                aria-hidden="<?php echo $activeTab === 'patterns' ? 'false' : 'true'; ?>"
                <?php echo $activeTab === 'patterns' ? '' : 'hidden'; ?>
            >
                <form method="post" action="<?php echo esc_url(admin_url('options.php')); ?>" class="gro-patterns-form" autocomplete="off">
                    <?php settings_fields($patternsGroup); ?>
                    <input type="hidden" name="gro_open_rules_tab" value="patterns" />

                    <div class="gro-patterns-shell">
                        <?php \BertanexFlow\Admin\Views\SettingsSectionRenderer::render($patternsGroup, 'gro_sms_templates_section'); ?>
                    </div>

                </form>
            </div>

            <div
                id="gro-rules-panel-email"
                data-gro-panel="email"
                class="gro-panel gro-panel--patterns"
                role="tabpanel"
                tabindex="0"
                aria-labelledby="gro-rules-tab-email"
                aria-hidden="<?php echo $activeTab === 'email' ? 'false' : 'true'; ?>"
                <?php echo $activeTab === 'email' ? '' : 'hidden'; ?>
            >
                <?php (new \BertanexFlow\Admin\EmailTemplateType())->renderDirectory(); ?>
            </div>

            <div
                id="gro-rules-panel-runs"
                data-gro-panel="runs"
                class="gro-panel"
                role="tabpanel"
                tabindex="0"
                aria-labelledby="gro-rules-tab-runs"
                aria-hidden="<?php echo $activeTab === 'runs' ? 'false' : 'true'; ?>"
                <?php echo $activeTab === 'runs' ? '' : 'hidden'; ?>
            >
                <?php do_action('bertanex_flow_render_activity'); ?>
            </div>

            <div
                id="gro-rules-panel-config"
                data-gro-panel="config"
                class="gro-panel gro-panel--config"
                role="tabpanel"
                tabindex="0"
                aria-labelledby="gro-rules-tab-config"
                aria-hidden="<?php echo $activeTab === 'config' ? 'false' : 'true'; ?>"
                <?php echo $activeTab === 'config' ? '' : 'hidden'; ?>
            >
                <h2 class="gro-panel-title"><?php echo esc_html__('Configuration', 'bertanex-flow'); ?></h2>
                <p class="gro-panel-lede"><?php echo esc_html__('SMS delivery settings for queued messages.', 'bertanex-flow'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('options.php')); ?>" class="gro-config-form" autocomplete="off">
                    <?php settings_fields($configGroup); ?>
                    <input type="hidden" name="gro_open_rules_tab" value="config" />

                    <div class="gro-config-grid">
                        <div class="card">
                            <h2><?php echo esc_html__('SMS delivery', 'bertanex-flow'); ?></h2>
                            <p class="description"><?php echo esc_html__('Provider and Melipayamak credentials', 'bertanex-flow'); ?></p>
                            <div>
                                <?php \BertanexFlow\Admin\Views\SettingsSectionRenderer::render($configGroup, 'gro_sms_provider_section'); ?>
                                <?php \BertanexFlow\Admin\Views\SettingsSectionRenderer::render($configGroup, 'gro_sms_melipayamak_section'); ?>
                            </div>
                        </div>
                    </div>

                    <?php if (\BertanexFlow\Support\FeatureFlags::twilioEnabled()) : ?>
                            <p class="gro-config-form__note description">
                                <?php echo esc_html__(
                                    'Twilio can also be set via wp-config (BERTANEX_FLOW_TWILIO_*) or the gro_twilio_sms_config filter.',
                                    'bertanex-flow'
                                ); ?>
                            </p>
                    <?php endif; ?>
                </form>
            </div>

            <div
                id="gro-rules-panel-reset"
                data-gro-panel="reset"
                class="gro-panel gro-panel--config"
                role="tabpanel"
                tabindex="0"
                aria-labelledby="gro-rules-tab-reset"
                aria-hidden="<?php echo $activeTab === 'reset' ? 'false' : 'true'; ?>"
                <?php echo $activeTab === 'reset' ? '' : 'hidden'; ?>
            >
                <h2 class="gro-panel-title" id="gro-reset-defaults-heading"><?php echo esc_html__('Reset to defaults', 'bertanex-flow'); ?></h2>
                <p class="gro-panel-lede">
                    <?php echo esc_html__(
                        'Remove all saved customizations and restore the plugin’s original state.',
                        'bertanex-flow'
                    ); ?>
                </p>
                <section class="card" aria-labelledby="gro-reset-defaults-heading">
                    <p class="gro-reset-defaults__lede">
                        <?php echo esc_html__(
                            'This will reset automation rules, SMS message patterns, automation list limits, column layout, and Melipayamak settings. Credentials defined in wp-config or filters are not changed.',
                            'bertanex-flow'
                        ); ?>
                    </p>
                    <form
                        method="post"
                        action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                        class="gro-reset-defaults__form"
                        data-gro-confirm="<?php echo esc_attr__(
                            'Reset all Bertanex Flow customizations to defaults? This cannot be undone.',
                            'bertanex-flow'
                        ); ?>"
                        data-gro-confirm-ok="<?php echo esc_attr__('Reset all to defaults', 'bertanex-flow'); ?>"
                        data-gro-confirm-phrase="reset-all"
                        data-gro-confirm-phrase-label="<?php echo esc_attr__('Type reset-all to confirm.', 'bertanex-flow'); ?>"
                    >
                        <?php wp_nonce_field('gro_reset_defaults'); ?>
                        <input type="hidden" name="action" value="gro_reset_defaults" />
                        <input type="hidden" name="gro_reset_phrase" value="" />
                        <input type="hidden" name="page" value="<?php echo esc_attr(\BertanexFlow\Admin\Controllers\RulesController::PAGE_SLUG); ?>" />
                        <?php submit_button(
                            __('Reset all to defaults', 'bertanex-flow'),
                            'delete gro-reset-defaults__submit',
                            'gro_reset_defaults_submit',
                            false
                        ); ?>
                    </form>
                </section>
            </div>

            <form method="post" action="<?php echo esc_url($context->formActionUrl); ?>" id="gro-rules-editor-form" class="gro-editor-form" <?php echo $activeTab === 'visual' ? '' : 'hidden'; ?>>
                <?php wp_nonce_field('gro_rules_save'); ?>
                <input type="hidden" name="gro_rules_save" value="1" />

                <div id="gro-rules-panel-visual" data-gro-panel="visual" class="gro-panel gro-panel--visual" role="tabpanel" tabindex="0" aria-labelledby="gro-rules-tab-visual" aria-hidden="<?php echo $activeTab === 'visual' ? 'false' : 'true'; ?>" <?php echo $activeTab === 'visual' ? '' : 'hidden'; ?>>
                    <div id="gro-rules-visual-root" class="gro-rules-visual-mount"></div>
                    <noscript>
                        <p class="gro-alert gro-alert--warn"><?php echo esc_html__('Enable JavaScript to use the automations editor.', 'bertanex-flow'); ?></p>
                    </noscript>
                </div>

                <textarea
                    name="gro_rules_json"
                    id="gro_rules_json"
                    class="gro-rules-json-hidden"
                    hidden
                    aria-hidden="true"
                    tabindex="-1"
                    spellcheck="false"
                ><?php echo esc_textarea($context->textareaValue); ?></textarea>

            </form>
        </div>
    </section>
    </div>
    </div>
    </div>
    </div>
    </div>
</div>
