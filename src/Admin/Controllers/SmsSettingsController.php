<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Admin\Controllers;

use GrowthOrchestrator\Admin\Models\SmsSettingsModel;
use GrowthOrchestrator\Admin\Views\SmsSettingsPageView;
use GrowthOrchestrator\Loader\HooksLoader;

/**
 * Admin screen: SMS provider + Melipayamak credentials (Settings API).
 */
final class SmsSettingsController
{
    public function __construct(
        private readonly SmsSettingsModel $model,
        private readonly SmsSettingsPageView $view
    ) {
    }

    public function register(HooksLoader $hooks): void
    {
        $hooks->add_action($this, 'registerSettings', 'admin_init', 5, 0);
        $hooks->add_action($this, 'onAdminMenu', 'admin_menu', 11, 0);
    }

    public function registerSettings(): void
    {
        if (! is_admin()) {
            return;
        }

        $this->model->registerSettings();
    }

    public function onAdminMenu(): void
    {
        $loc = SmsSettingsModel::menuLocation();

        add_submenu_page(
            $loc['page_parent'],
            __('SMS settings', 'growth-orchestrator'),
            __('SMS', 'growth-orchestrator'),
            'manage_options',
            $loc['page_slug'],
            [$this, 'renderPage']
        );
    }

    public function renderPage(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'growth-orchestrator'));
        }

        $this->view->render();
    }
}
