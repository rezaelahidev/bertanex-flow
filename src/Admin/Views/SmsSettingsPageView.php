<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Admin\Views;

use GrowthOrchestrator\Admin\Models\SmsSettingsModel;

/**
 * View: SMS settings page wrapper (form uses Settings API fields registered on the model).
 */
final class SmsSettingsPageView
{
    public function render(): void
    {
        $template = __DIR__ . '/templates/sms-settings-page.php';

        if (! is_readable($template)) {
            echo '<div class="wrap"><p>' . esc_html__('SMS settings template missing.', 'growth-orchestrator') . '</p></div>';

            return;
        }

        $settingsGroup = SmsSettingsModel::SETTINGS_GROUP;
        include $template;
    }
}
