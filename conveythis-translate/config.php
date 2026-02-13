<?php

if(!defined( 'ABSPATH' )){
    exit;
}

$dev_server = 'default';

//$dev_server = 4;

$allowed_app_servers = array(
    1 => 'https://dev-app-1.conveythis.com',
    2 => 'https://dev-app-2.conveythis.com',
    3 => 'https://dev-app-3.conveythis.com',
    4 => 'https://dev-app-4.conveythis.com',
    5 => 'https://dev-app-5.conveythis.com',
    'default' => 'https://app.conveythis.com'
);

$allowed_api_servers = array(
    1 => 'https://dev-api-1.conveythis.com',
    2 => 'https://dev-api-2.conveythis.com',
    3 => 'https://dev-api-3.conveythis.com',
    4 => 'https://dev-api-4.conveythis.com',
    5 => 'https://dev-api-5.conveythis.com',
    'default' => 'https://api.conveythis.com'
);

$allowed_cdn_servers = array(
    1 => 'https://dev-app-1.conveythis.com/cdn/dist',
    2 => 'https://dev-app-2.conveythis.com/cdn/dist',
    3 => 'https://dev-app-3.conveythis.com/cdn/dist',
    4 => 'https://dev-app-4.conveythis.com/cdn/dist',
    5 => 'https://dev-app-5.conveythis.com/cdn/dist',
    'default' => '//cdn.conveythis.com/javascript'
);

$allowed_api_proxy_servers_US = array(
    1 => 'https://dev-api-1.conveythis.com',
    2 => 'https://dev-api-2.conveythis.com',
    3 => 'https://dev-api-3.conveythis.com',
    4 => 'https://dev-api-4.conveythis.com',
    5 => 'https://dev-api-5.conveythis.com',
    'default' => 'https://api-proxy.conveythis.com'
);

$allowed_api_proxy_servers_EU = array(
    1 => 'https://dev-api-1.conveythis.com',
    2 => 'https://dev-api-2.conveythis.com',
    3 => 'https://dev-api-3.conveythis.com',
    4 => 'https://dev-api-4.conveythis.com',
    5 => 'https://dev-api-5.conveythis.com',
    'default' => 'https://proxy-eu.conveythis.com'
);

define('CONVEYTHIS_APP_URL', $allowed_app_servers[$dev_server]);
define('CONVEYTHIS_API_URL', $allowed_api_servers[$dev_server]);
define('CONVEYTHIS_API_PROXY_URL', $allowed_api_proxy_servers_US[$dev_server]);
define('CONVEYTHIS_API_PROXY_URL_FOR_EU', $allowed_api_proxy_servers_EU[$dev_server]);
define('CONVEYTHIS_JAVASCRIPT_PLUGIN_URL', $allowed_cdn_servers[$dev_server]);

define('CONVEYTHIS_LOADER', true);
define('CONVEYTHIS_PLUGIN_VERSION', '269.4');
define('CONVEY_PLUGIN_ROOT_PATH', plugin_dir_path( __FILE__ ));
define('CONVEY_PLUGIN_PATH', plugin_dir_url(__FILE__));
define('CONVEY_PLUGIN_DIR', plugins_url('', __FILE__));
define('CONVEYTHIS_VIEWS',  plugin_dir_path( __FILE__ ) . 'app/views/notices');
define('CONVEYTHIS_URL', 'https://www.conveythis.com/');
define('CONVEYTHIS_JAVASCRIPT_LIGHT_PLUGIN_URL', '//cdn.conveythis.com/javascriptLight/3');

if (!defined('CONVEYTHIS_CACHE_ROOT_PATH')){
    define('CONVEYTHIS_CACHE_ROOT_PATH', WP_CONTENT_DIR . '/cache/');
}
define('CONVEYTHIS_CACHE_PATH', CONVEYTHIS_CACHE_ROOT_PATH . 'conveythis/');
define('CONVEYTHIS_CACHE_SLUG_PATH', CONVEYTHIS_CACHE_PATH . 'slug.json');
define('CONVEYTHIS_CACHE_TRANSLATIONS_PATH', CONVEYTHIS_CACHE_PATH . 'translations/');
define('API_AUTH_TOKEN', '85T8DGNtV88g4wvceVyHym69Yu3v5ZmN');

