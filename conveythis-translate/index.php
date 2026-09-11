<?php
/*
Plugin Name: ConveyThis Translate
Plugin URI: https://www.conveythis.com/?utm_source=widget&utm_medium=wordpress
Description: Translate your WordPress site into over 100 languages using professional and instant machine translation technology. ConveyThis will help provide you with an SEO-friendy, multilingual website in minutes with no coding required.
Version: 270.7
Requires at least: 5.3
Requires PHP: 7.4
Author: ConveyThis Translate Team
Author URI: https://www.conveythis.com/?utm_source=widget&utm_medium=wordpress
Text Domain: conveythis-translate
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

/**
 * PHP 7.4 compatibility.
 *
 * ConveyThisSEO::sitemap_add_translated_urls() calls str_contains(), which is
 * PHP 8.0+. That method is hooked to Yoast / Rank Math / SEOPress sitemap
 * generation, so on PHP 7.x the site itself keeps working but building a
 * sitemap fatals. Per wordpress.org/about/stats, 23% of WordPress installs
 * still run PHP below 8.0 and 17% are on 7.4 alone, so this is polyfilled
 * rather than locking those sites out of updates entirely.
 *
 * With this in place the real floor is PHP 7.4, set by the arrow function in
 * ConveyThis.php:5363 — which is what the Requires PHP header now declares.
 */
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle)
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}
/**
 * Config
 */
require_once plugin_dir_path(__FILE__) .  "config.php";

/**
 * Add file templ
 */
require_once plugin_dir_path(__FILE__) .  "app/connect/DebugTest.php";
require_once plugin_dir_path(__FILE__) .  "app/connect/ConveyThisStart.php";

/**
 * Class
 */
require_once plugin_dir_path(__FILE__) . 'app/class/ConveyThis.php';
require_once plugin_dir_path(__FILE__) . 'app/class/ConveyThisWidget.php';
require_once plugin_dir_path(__FILE__) . 'app/class/ConveyThisAdminNotices.php';
require_once plugin_dir_path(__FILE__) . 'app/class/ConveyThisCache.php';
require_once plugin_dir_path(__FILE__) . 'app/class/ConveyThisCron.php';
require_once plugin_dir_path(__FILE__) . 'app/class/ConveyThisSEO.php';
require_once plugin_dir_path(__FILE__) . 'app/class/ConveyThisHelper.php';
require_once plugin_dir_path(__FILE__) . 'app/class/ConveyThisCompetitorCheck.php';

/**
 * Plugin
 */
register_activation_hook( __FILE__, array( 'ConveyThis', 'plugin_activate' ) );
register_deactivation_hook( __FILE__, array( 'ConveyThis', 'plugin_deactivate' ) );
register_uninstall_hook( __FILE__, array( 'ConveyThis', 'plugin_uninstall' ) );

add_action('plugins_loaded', array('ConveyThisCompetitorCheck', 'check_conflicts'));
add_action('admin_notices', array('ConveyThisCompetitorCheck', 'admin_notice'));
add_action( 'plugins_loaded', array( 'ConveyThis', 'Instance' ), 10 );
add_action('admin_notices', array('ConveyThis', 'show_activation_message'));
add_action( 'admin_bar_menu',  array( 'ConveyThis', 'modify_admin_bar' ), 999);

/**
 * Cron
 */
// Start method
add_action('ConveyThisClearCache', array('ConveyThisCron', 'ClearCache'));
// Settings
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( 'ConveyThis', 'settings_link') );
// Connect cron
add_filter('cron_schedules', array('ConveyThisCron', 'ConveyThisСustomСronSchedule'));

register_activation_hook(__FILE__, array('ConveyThisCron', 'ConveyThisActivationCron'));
register_deactivation_hook(__FILE__, array('ConveyThisCron', 'ConveyThisDeactivationCron'));



