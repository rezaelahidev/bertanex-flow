<?php
/**
 * Plugin Name:       Marketing Automation
 * Plugin URI:        https://example.com
 * Description:       Event-driven marketing automation: rules, SMS, and extensible hooks.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Your Company
 * License:           GPL-2.0-or-later
 * Text Domain:       marketing-automation
 *
 * @package MarketingAutomation
 */

declare(strict_types=1);

namespace MarketingAutomation;

use MarketingAutomation\Loader\HooksLoader;

if (! defined('ABSPATH')) {
    exit;
}

define('MA_PLUGIN_FILE', __FILE__);
define('MA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MA_PLUGIN_VERSION', '0.1.0');

if (is_readable(MA_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once MA_PLUGIN_DIR . 'vendor/autoload.php';
} else {
    require_once MA_PLUGIN_DIR . 'src/Autoloader.php';
    Autoloader::register(MA_PLUGIN_DIR . 'src', __NAMESPACE__ . '\\');
}

$ma_action_scheduler = MA_PLUGIN_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
if (is_readable($ma_action_scheduler)) {
    require_once $ma_action_scheduler;
}

$container = new Container();
$hooks       = new HooksLoader();

$plugin = new Plugin($container, $hooks);
$plugin->run();
