<?php
/**
 * Plugin Name:       Bertanex Flow
 * Plugin URI:        https://github.com/rezaelahidev/bertanex-flow
 * Description:       Send SMS and email automatically for WordPress and WooCommerce, and edit every flow yourself.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Bertanex
 * License:           GPL-2.0-or-later
 * Text Domain:       bertanex-flow
 * Domain Path: /languages
 *
 * @package BertanexFlow
 */

declare(strict_types=1);

namespace BertanexFlow;

use BertanexFlow\Consumer\ConsumerDefaultsResolver;
use BertanexFlow\Consumer\ConsumerRegistry;
use BertanexFlow\Db\RulesDbSchema;
use BertanexFlow\Loader\HooksLoader;

if (! defined('ABSPATH')) {
    exit;
}

if (PHP_VERSION_ID < 80100 || PHP_VERSION_ID >= 80500) {
    add_action(
        'admin_notices',
        static function (): void {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__(
                'Bertanex Flow requires PHP 8.1 through 8.4.',
                'bertanex-flow'
            );
            echo '</p></div>';
        }
    );

    return;
}

define('BERTANEX_FLOW_PLUGIN_FILE', __FILE__);
define('BERTANEX_FLOW_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BERTANEX_FLOW_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BERTANEX_FLOW_VERSION', '0.1.0');

if (is_readable(BERTANEX_FLOW_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once BERTANEX_FLOW_PLUGIN_DIR . 'vendor/autoload.php';
} else {
    require_once BERTANEX_FLOW_PLUGIN_DIR . 'src/Autoloader.php';
    Autoloader::register(BERTANEX_FLOW_PLUGIN_DIR . 'src', __NAMESPACE__ . '\\');
}

require_once BERTANEX_FLOW_PLUGIN_DIR . 'src/Autoloader.php';
Autoloader::register(BERTANEX_FLOW_PLUGIN_DIR . 'src', 'BertanexFlow\\');

$bertanex_flow_action_scheduler = BERTANEX_FLOW_PLUGIN_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
if (is_readable($bertanex_flow_action_scheduler)) {
    require_once $bertanex_flow_action_scheduler;
}

register_activation_hook(
    BERTANEX_FLOW_PLUGIN_FILE,
    static function (): void {
        RulesDbSchema::migrateLegacyNames();
        $resolver = new ConsumerDefaultsResolver(ConsumerRegistry::instance());
        RulesDbSchema::onPluginActivate($resolver->mergedRulesDocumentJson());
        \BertanexFlow\Core\Bootstrap\Kernel::install();
        update_option('bertanex_flow_onboarding_pending', '1', false);
    }
);

$container = new Container();
$hooks       = new HooksLoader();

$plugin = new Plugin($container, $hooks);
$plugin->run();
