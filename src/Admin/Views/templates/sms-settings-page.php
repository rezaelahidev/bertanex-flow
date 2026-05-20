<?php
/**
 * Growth Orchestrator — SMS settings (Settings API).
 *
 * @var string $settingsGroup Option group slug (passed by {@see SmsSettingsPageView}).
 */

defined('ABSPATH') || exit;

/** @var string $settingsGroup */
?>
<div class="wrap">
    <h1><?php echo esc_html__('Growth Orchestrator — SMS', 'growth-orchestrator'); ?></h1>

    <form method="post" action="options.php" autocomplete="off">
        <?php
        settings_fields($settingsGroup);
        do_settings_sections($settingsGroup);
        submit_button(__('Save SMS settings', 'growth-orchestrator'));
        ?>
    </form>

    <p class="description">
        <?php
        if (\GrowthOrchestrator\Support\FeatureFlags::twilioEnabled()) {
            esc_html_e(
                'Twilio remains configurable via wp-config constants (GROWTH_ORCH_TWILIO_*) or the gro_twilio_sms_config filter when that provider is selected. ' .
                'Melipayamak uses the REST endpoint rest.payamak-panel.com (SendSMS) with sslverify enabled.',
                'growth-orchestrator'
            );
        } else {
            esc_html_e(
                'Melipayamak uses the REST endpoint rest.payamak-panel.com (SendSMS) with sslverify enabled.',
                'growth-orchestrator'
            );
        }
        ?>
    </p>
</div>
