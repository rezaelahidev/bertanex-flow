<?php
/**
 * Bertanex Flow — SMS settings (Settings API).
 *
 * @var string $settingsGroup Option group slug (passed by {@see SmsSettingsPageView}).
 */

defined('ABSPATH') || exit;

/** @var string $settingsGroup */
?>
<div class="wrap">
    <h1><?php echo esc_html__('Bertanex Flow — SMS', 'bertanex-flow'); ?></h1>

    <form method="post" action="options.php" autocomplete="off">
        <?php
        settings_fields($settingsGroup);
        do_settings_sections($settingsGroup);
        submit_button(__('Save SMS settings', 'bertanex-flow'));
        ?>
    </form>

    <p class="description">
        <?php
        if (\BertanexFlow\Support\FeatureFlags::twilioEnabled()) {
            esc_html_e(
                'Twilio remains configurable via wp-config constants (BERTANEX_FLOW_TWILIO_*) or the gro_twilio_sms_config filter when that provider is selected. ' .
                'Melipayamak uses the REST endpoint rest.payamak-panel.com (SendSMS) with sslverify enabled.',
                'bertanex-flow'
            );
        } else {
            esc_html_e(
                'Melipayamak uses the REST endpoint rest.payamak-panel.com (SendSMS) with sslverify enabled.',
                'bertanex-flow'
            );
        }
        ?>
    </p>
</div>
