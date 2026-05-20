<?php
/**
 * Plugin Name:       Growth Orchestrator
 * Plugin URI:        https://github.com/rezaelahidev/growth-orchestrator
 * Description:       Event-driven growth automation: rules, SMS, queues, and extensible hooks.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Your Company
 * License:           GPL-2.0-or-later
 * Text Domain:       growth-orchestrator
 * Domain Path: /languages
 *
 * @package GrowthOrchestrator
 */

declare(strict_types=1);

namespace GrowthOrchestrator;

use GrowthOrchestrator\Db\RulesDbSchema;
use GrowthOrchestrator\Loader\HooksLoader;

if (! defined('ABSPATH')) {
    exit;
}

if (PHP_VERSION_ID < 80100 || PHP_VERSION_ID >= 80500) {
    add_action(
        'admin_notices',
        static function (): void {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__(
                'Growth Orchestrator requires PHP 8.1 through 8.4.',
                'growth-orchestrator'
            );
            echo '</p></div>';
        }
    );

    return;
}

define('GROWTH_ORCH_PLUGIN_FILE', __FILE__);
define('GROWTH_ORCH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GROWTH_ORCH_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GROWTH_ORCH_VERSION', '0.1.0');

if (is_readable(GROWTH_ORCH_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once GROWTH_ORCH_PLUGIN_DIR . 'vendor/autoload.php';
} else {
    require_once GROWTH_ORCH_PLUGIN_DIR . 'src/Autoloader.php';
    Autoloader::register(GROWTH_ORCH_PLUGIN_DIR . 'src', __NAMESPACE__ . '\\');
}

require_once GROWTH_ORCH_PLUGIN_DIR . 'omdbox-theme-compat.php';

$growth_orch_action_scheduler = GROWTH_ORCH_PLUGIN_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
if (is_readable($growth_orch_action_scheduler)) {
    require_once $growth_orch_action_scheduler;
}

register_activation_hook(
    GROWTH_ORCH_PLUGIN_FILE,
    static function (): void {
        RulesDbSchema::onPluginActivate(GROWTH_ORCH_PLUGIN_DIR . 'rules/default.rules.json');
    }
);

$container = new Container();
$hooks       = new HooksLoader();

$plugin = new Plugin($container, $hooks);
$plugin->run();
