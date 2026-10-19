<?php

declare(strict_types=1);

namespace BertanexFlow\Admin\Views;

use BertanexFlow\Admin\Models\SmsSettingsModel;

/**
 * View: SMS settings page wrapper (form uses Settings API fields registered on the model).
 */
final class SmsSettingsPageView
{
    public function render(): void
    {
        $template = __DIR__ . '/templates/sms-settings-page.php';

        if (! is_readable($template)) {
            echo '<div class="wrap"><p>' . esc_html__('SMS settings template missing.', 'bertanex-flow') . '</p></div>';

            return;
        }

        $settingsGroup = SmsSettingsModel::SETTINGS_GROUP;
        include $template;
    }
}
