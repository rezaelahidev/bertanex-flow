<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Admin\Models;

use GrowthOrchestrator\Admin\Controllers\RulesController;
use GrowthOrchestrator\Sms\MelipayamakSmsConfig;

/**
 * Registers SMS provider options (Settings API) and field renderers.
 */
final class SmsSettingsModel
{
    public const SETTINGS_GROUP = 'growth_orchestrator_sms';

    public const OPTION_PROVIDER = 'gro_sms_transport_provider';

    public function registerSettings(): void
    {
        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_PROVIDER,
            [
                'type'              => 'string',
                'default'           => 'auto',
                'sanitize_callback' => static function ($value): string {
                    $allowed = ['auto', 'melipayamak', 'twilio', 'logging'];
                    $v       = is_string($value) ? $value : '';

                    return in_array($v, $allowed, true) ? $v : 'auto';
                },
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            MelipayamakSmsConfig::OPTION_USERNAME,
            [
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => static fn ($v): string => sanitize_text_field(is_string($v) ? $v : ''),
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            MelipayamakSmsConfig::OPTION_PASSWORD,
            [
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => [$this, 'sanitizeApiPassword'],
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            MelipayamakSmsConfig::OPTION_FROM,
            [
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => static fn ($v): string => sanitize_text_field(is_string($v) ? $v : ''),
            ]
        );

        add_settings_section(
            'gro_sms_provider_section',
            __('SMS provider', 'growth-orchestrator'),
            static function (): void {
                echo '<p class="description">' .
                    esc_html__(
                        'Choose which transport delivers queued SMS. “Automatic” uses Melipayamak when configured, then Twilio, otherwise the logging (no-op) driver.',
                        'growth-orchestrator'
                    ) .
                    '</p>';
            },
            self::SETTINGS_GROUP
        );

        add_settings_field(
            self::OPTION_PROVIDER,
            __('Provider', 'growth-orchestrator'),
            [$this, 'renderProviderField'],
            self::SETTINGS_GROUP,
            'gro_sms_provider_section',
            ['label_for' => self::OPTION_PROVIDER]
        );

        add_settings_section(
            'gro_sms_melipayamak_section',
            __('Melipayamak (Payamak-Panel)', 'growth-orchestrator'),
            static function (): void {
                echo '<p class="description">' .
                    esc_html__(
                        'Credentials for rest.payamak-panel.com SendSMS. You can also set GROWTH_ORCH_MELIPAYAMAK_* constants in wp-config.php. Leave password empty to keep the saved value.',
                        'growth-orchestrator'
                    ) .
                    '</p>';
            },
            self::SETTINGS_GROUP
        );

        add_settings_field(
            MelipayamakSmsConfig::OPTION_USERNAME,
            __('Username', 'growth-orchestrator'),
            [$this, 'renderUsernameField'],
            self::SETTINGS_GROUP,
            'gro_sms_melipayamak_section',
            ['label_for' => MelipayamakSmsConfig::OPTION_USERNAME]
        );

        add_settings_field(
            MelipayamakSmsConfig::OPTION_PASSWORD,
            __('Password', 'growth-orchestrator'),
            [$this, 'renderPasswordField'],
            self::SETTINGS_GROUP,
            'gro_sms_melipayamak_section',
            ['label_for' => MelipayamakSmsConfig::OPTION_PASSWORD]
        );

        add_settings_field(
            MelipayamakSmsConfig::OPTION_FROM,
            __('Sender line (from)', 'growth-orchestrator'),
            [$this, 'renderFromField'],
            self::SETTINGS_GROUP,
            'gro_sms_melipayamak_section',
            ['label_for' => MelipayamakSmsConfig::OPTION_FROM]
        );
    }

    /** @param mixed $value */
    public function sanitizeApiPassword($value): string
    {
        if (! is_string($value) || $value === '') {
            return (string) get_option(MelipayamakSmsConfig::OPTION_PASSWORD, '');
        }

        return wp_unslash($value);
    }

    public function renderProviderField(): void
    {
        $current = (string) get_option(self::OPTION_PROVIDER, 'auto');
        $id      = self::OPTION_PROVIDER;

        ?>
        <select name="<?php echo esc_attr(self::OPTION_PROVIDER); ?>" id="<?php echo esc_attr($id); ?>">
            <option value="auto" <?php selected($current, 'auto'); ?>><?php esc_html_e('Automatic', 'growth-orchestrator'); ?></option>
            <option value="melipayamak" <?php selected($current, 'melipayamak'); ?>><?php esc_html_e('Melipayamak', 'growth-orchestrator'); ?></option>
            <option value="twilio" <?php selected($current, 'twilio'); ?>><?php esc_html_e('Twilio', 'growth-orchestrator'); ?></option>
            <option value="logging" <?php selected($current, 'logging'); ?>><?php esc_html_e('Logging only (development)', 'growth-orchestrator'); ?></option>
        </select>
        <?php
    }

    public function renderUsernameField(): void
    {
        $v = (string) get_option(MelipayamakSmsConfig::OPTION_USERNAME, '');
        ?>
        <input type="text"
            class="regular-text"
            name="<?php echo esc_attr(MelipayamakSmsConfig::OPTION_USERNAME); ?>"
            id="<?php echo esc_attr(MelipayamakSmsConfig::OPTION_USERNAME); ?>"
            value="<?php echo esc_attr($v); ?>"
            autocomplete="off"
        />
        <?php
    }

    public function renderPasswordField(): void
    {
        ?>
        <input type="password"
            class="regular-text"
            name="<?php echo esc_attr(MelipayamakSmsConfig::OPTION_PASSWORD); ?>"
            id="<?php echo esc_attr(MelipayamakSmsConfig::OPTION_PASSWORD); ?>"
            value=""
            autocomplete="new-password"
            placeholder="<?php echo esc_attr__('Leave blank to keep the current password', 'growth-orchestrator'); ?>"
        />
        <?php
    }

    public function renderFromField(): void
    {
        $v = (string) get_option(MelipayamakSmsConfig::OPTION_FROM, '');
        ?>
        <input type="text"
            class="regular-text"
            name="<?php echo esc_attr(MelipayamakSmsConfig::OPTION_FROM); ?>"
            id="<?php echo esc_attr(MelipayamakSmsConfig::OPTION_FROM); ?>"
            value="<?php echo esc_attr($v); ?>"
            placeholder="<?php echo esc_attr__('e.g. short line number from panel', 'growth-orchestrator'); ?>"
        />
        <?php
    }

    /** @return array{page_parent: string, page_slug: string} */
    public static function menuLocation(): array
    {
        return [
            'page_parent' => RulesController::PAGE_SLUG,
            'page_slug'   => 'growth-orchestrator-sms',
        ];
    }
}
