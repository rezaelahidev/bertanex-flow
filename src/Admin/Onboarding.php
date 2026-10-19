<?php

declare(strict_types=1);

namespace BertanexFlow\Admin;

use BertanexFlow\Admin\Controllers\RulesController;
use BertanexFlow\Admin\Models\SmsSettingsModel;
use BertanexFlow\Sms\MelipayamakSmsConfig;
use BertanexFlow\Support\FeatureFlags;

/**
 * One-time setup guide shown after the plugin is activated.
 */
final class Onboarding
{
    public const OPTION = 'bertanex_flow_onboarding_pending';

    public function register(): void
    {
        add_action('admin_init', [$this, 'redirect']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('wp_ajax_gro_onboarding_save', [$this, 'save']);
    }

    public static function isPending(): bool
    {
        return (string) get_option(self::OPTION, '') === '1';
    }

    public function redirect(): void
    {
        if (! self::isPending() || ! current_user_can('manage_options') || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }
        if (isset($_GET['page']) && sanitize_key((string) $_GET['page']) === RulesController::PAGE_SLUG) {
            return;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || headers_sent()) {
            return;
        }

        wp_safe_redirect(add_query_arg(
            [
                'page' => RulesController::PAGE_SLUG,
                'gro_open_tab' => 'visual',
            ],
            admin_url('admin.php')
        ));
        exit;
    }

    public function enqueue(string $hook): void
    {
        if ($hook !== 'toplevel_page_' . RulesController::PAGE_SLUG || ! self::isPending()) {
            return;
        }

        wp_enqueue_style('wp-components');
        $handle = 'gro-onboarding';
        wp_enqueue_script(
            $handle,
            plugins_url('assets/admin/onboarding.js', BERTANEX_FLOW_PLUGIN_FILE),
            ['wp-element', 'wp-components', 'wp-i18n'],
            (string) filemtime(BERTANEX_FLOW_PLUGIN_DIR . 'assets/admin/onboarding.js'),
            true
        );

        $providers = [
            ['value' => 'auto', 'label' => __('Automatic', 'bertanex-flow')],
            ['value' => 'melipayamak', 'label' => __('Melipayamak', 'bertanex-flow')],
            ['value' => 'logging', 'label' => __('Logging only (development)', 'bertanex-flow')],
        ];
        if (FeatureFlags::twilioEnabled()) {
            $providers[] = ['value' => 'twilio', 'label' => __('Twilio', 'bertanex-flow')];
        }

        $templates = [
            ['id' => EmailTemplateType::WORDPRESS_TEMPLATE, 'title' => __('WordPress default', 'bertanex-flow')],
        ];
        $posts = get_posts(
            [
                'post_type' => EmailTemplateType::POST_TYPE,
                'post_status' => 'publish',
                'posts_per_page' => 50,
                'orderby' => 'title',
                'order' => 'ASC',
            ]
        );
        foreach ($posts as $post) {
            if ($post instanceof \WP_Post) {
                $templates[] = [
                    'id' => (string) $post->post_name,
                    'title' => $post->post_title !== '' ? $post->post_title : (string) $post->post_name,
                ];
            }
        }

        wp_localize_script($handle, 'groOnboarding', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gro_onboarding_save'),
            'provider' => (string) get_option(SmsSettingsModel::OPTION_PROVIDER, 'auto'),
            'username' => (string) get_option(MelipayamakSmsConfig::OPTION_USERNAME, ''),
            'from' => (string) get_option(MelipayamakSmsConfig::OPTION_FROM, ''),
            'email' => EmailTemplateType::activeSlug(),
            'providers' => $providers,
            'templates' => $templates,
            'labels' => [
                'setupTitle' => __('Set up Flow', 'bertanex-flow'),
                'welcomeTitle' => __('Welcome to Bertanex Flow', 'bertanex-flow'),
                'welcomeBody' => __('Set how messages are delivered. You can change these later in Configuration and Email templates.', 'bertanex-flow'),
                'stepWelcome' => __('Welcome', 'bertanex-flow'),
                'smsTitle' => __('SMS delivery', 'bertanex-flow'),
                'smsSummary' => __('Choose the provider, username, and sender line.', 'bertanex-flow'),
                'provider' => __('Provider', 'bertanex-flow'),
                'username' => __('Username', 'bertanex-flow'),
                'password' => __('Password', 'bertanex-flow'),
                'passwordHint' => __('Leave blank to keep the current password.', 'bertanex-flow'),
                'from' => __('Sender line (from)', 'bertanex-flow'),
                'emailTitle' => __('Email template', 'bertanex-flow'),
                'emailSummary' => __('Choose the layout used for automation emails.', 'bertanex-flow'),
                'emailHelp' => __('WordPress plain email is used unless you choose a designed template.', 'bertanex-flow'),
                'template' => __('Template', 'bertanex-flow'),
                'back' => __('Back', 'bertanex-flow'),
                'next' => __('Next', 'bertanex-flow'),
                'finish' => __('Finish', 'bertanex-flow'),
                'saving' => __('Saving…', 'bertanex-flow'),
                'saveError' => __('Could not save the setup.', 'bertanex-flow'),
            ],
        ]);

        add_action('admin_footer', static function (): void {
            echo '<div id="gro-onboarding"></div>';
        });
    }

    public function save(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to save this setup.', 'bertanex-flow')], 403);
        }
        check_ajax_referer('gro_onboarding_save', 'nonce');

        $provider = isset($_POST['provider']) ? sanitize_key((string) wp_unslash($_POST['provider'])) : 'auto';
        $allowed = FeatureFlags::twilioEnabled() ? ['auto', 'melipayamak', 'twilio', 'logging'] : ['auto', 'melipayamak', 'logging'];
        if (! in_array($provider, $allowed, true)) {
            $provider = 'auto';
        }
        update_option(SmsSettingsModel::OPTION_PROVIDER, $provider, false);
        update_option(MelipayamakSmsConfig::OPTION_USERNAME, sanitize_text_field((string) wp_unslash($_POST['username'] ?? '')), false);
        $password = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';
        if ($password !== '') {
            update_option(MelipayamakSmsConfig::OPTION_PASSWORD, $password, false);
        }
        update_option(MelipayamakSmsConfig::OPTION_FROM, sanitize_text_field((string) wp_unslash($_POST['from'] ?? '')), false);

        $email = strtolower((string) wp_unslash($_POST['email'] ?? EmailTemplateType::WORDPRESS_TEMPLATE));
        if (preg_match('/^[a-z0-9_\-]+$/', $email)) {
            $known = $email === EmailTemplateType::WORDPRESS_TEMPLATE;
            if (! $known) {
                $found = get_posts(
                    [
                        'post_type' => EmailTemplateType::POST_TYPE,
                        'name' => $email,
                        'post_status' => 'publish',
                        'posts_per_page' => 1,
                        'fields' => 'ids',
                    ]
                );
                $known = $found !== [];
            }
            if ($known) {
                update_option(EmailTemplateType::ACTIVE_OPTION, $email, false);
            }
        }

        if ((string) ($_POST['finish'] ?? '') === '1') {
            delete_option(self::OPTION);
        }

        wp_send_json_success(['pending' => self::isPending()]);
    }
}
