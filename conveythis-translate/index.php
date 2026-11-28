<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/*
Plugin Name: ConveyThis AI Multilingual Translation
Plugin URI: https://www.conveythis.com/?utm_source=widget&utm_medium=wordpress
Description: Translate your WordPress site into over 100 languages using professional and instant machine translation technology. ConveyThis will help provide you with an SEO-friendy, multilingual website in minutes with no coding required.
Version: 271.6
Requires at least: 5.3
Requires PHP: 7.4
Author: ConveyThis Translate Team
Author URI: https://www.conveythis.com/?utm_source=widget&utm_medium=wordpress
Text Domain: conveythis-translate
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

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
require_once plugin_dir_path(__FILE__) . 'app/class/ConveyThisSlugMap.php';
require_once plugin_dir_path(__FILE__) . 'app/class/ConveyThisSEO.php';
require_once plugin_dir_path(__FILE__) . 'app/class/ConveyThisHelper.php';
require_once plugin_dir_path(__FILE__) . 'app/class/ConveyThisCompetitorCheck.php';
require_once plugin_dir_path(__FILE__) . 'app/class/ConveyThisWooEmails.php';
require_once plugin_dir_path(__FILE__) . 'app/class/ConveyThisChurn.php';

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

// Deactivation churn survey: ask the merchant why on the Plugins screen, then
// ping ConveyThis. Never blocks deactivation.
ConveyThisChurn::register(__FILE__);

/**
 * Cron
 */
// Start method
add_action('ConveyThisClearCache', array('ConveyThisCron', 'ClearCache'));
// Page cache refresh when translations change on the ConveyThis side
add_action('ConveyThisTranslationsVersionCheck', array('ConveyThisCron', 'TranslationsVersionCheck'));
add_action('init', array('ConveyThisCron', 'EnsureTranslationsVersionCheck'));
// One translated address per page (Trello #2256): the API's list, hourly
add_action(ConveyThisSlugMap::CRON_HOOK, array('ConveyThisCron', 'SlugMapRefresh'));
add_action('init', array('ConveyThisCron', 'EnsureSlugMapRefresh'));
// Settings
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( 'ConveyThis', 'settings_link') );
// Connect cron
add_filter('cron_schedules', array('ConveyThisCron', 'ConveyThisСustomСronSchedule'));

register_activation_hook(__FILE__, array('ConveyThisCron', 'ConveyThisActivationCron'));
register_deactivation_hook(__FILE__, array('ConveyThisCron', 'ConveyThisDeactivationCron'));



