<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WordPress deactivation churn survey — the plugin side of the ConveyThis churn
 * tracking (Service\Churn on app.conveythis.com).
 *
 * On the Plugins screen it asks the merchant why they are deactivating (the WP
 * twin of the dashboard Unpublish modal), then pings app.conveythis.com/wp-churn/
 * so the reason lands in the same churn list as every other off. The ping is
 * best-effort and never blocks deactivation.
 *
 * Self-registering: index.php calls ConveyThisChurn::register(__FILE__). The
 * modal itself lives in app/widget/js/churn-deactivate.js.
 */
class ConveyThisChurn
{
    const ACTION = 'conveythis_churn';

    /** @var string absolute path of the main plugin file */
    private static $pluginFile = '';

    public static function register($pluginFile)
    {
        self::$pluginFile = (string) $pluginFile;
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue'));
        add_action('wp_ajax_' . self::ACTION, array(__CLASS__, 'ajax'));
    }

    /** Load the modal only on the Plugins screen. */
    public static function enqueue($hook)
    {
        if ($hook !== 'plugins.php') {
            return;
        }

        $ver = defined('CONVEYTHIS_PLUGIN_VERSION') ? CONVEYTHIS_PLUGIN_VERSION : '1';
        $base = defined('CONVEY_PLUGIN_DIR') ? CONVEY_PLUGIN_DIR : plugins_url('', self::$pluginFile);
        $src = rtrim($base, '/') . '/app/widget/js/churn-deactivate.js';

        wp_enqueue_script('conveythis-churn-deactivate', $src, array(), $ver, true);
        wp_localize_script('conveythis-churn-deactivate', 'ConveyThisChurn', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce(self::ACTION),
            'plugin'  => plugin_basename(self::$pluginFile),
            // Keys MUST match Service\Churn\DeactivationLog::REASONS on the app.
            'reasons' => array(
                array('troubleshooting',  __('Just troubleshooting, I will turn it back on', 'conveythis-translate')),
                array('too_expensive',    __('Too expensive or ran out of free words', 'conveythis-translate')),
                array('quality',          __('Translation quality was not good enough', 'conveythis-translate')),
                array('broke_site',       __('It did not work right on my site (layout, speed, conflict)', 'conveythis-translate')),
                array('hard_setup',       __('Too hard to set up', 'conveythis-translate')),
                array('switched_tool',    __('Switching to another tool', 'conveythis-translate')),
                array('no_longer_needed', __('No longer need it, or the site is closing', 'conveythis-translate')),
                array('other',            __('Other', 'conveythis-translate')),
            ),
            'i18n' => array(
                'title'       => __('Before you deactivate ConveyThis', 'conveythis-translate'),
                'subtitle'    => __('What is the main reason? This helps us improve (optional).', 'conveythis-translate'),
                'skip'        => __('Skip', 'conveythis-translate'),
                'submit'      => __('Deactivate', 'conveythis-translate'),
                'placeholder' => __('Anything you would like to add? (optional)', 'conveythis-translate'),
            ),
        ));
    }

    /** Receive the reason and forward it to ConveyThis, best-effort. */
    public static function ajax()
    {
        check_ajax_referer(self::ACTION, 'nonce');

        if (!current_user_can('activate_plugins')) {
            wp_send_json_error('forbidden', 403);
        }

        $reason  = isset($_POST['reason']) ? sanitize_text_field(wp_unslash($_POST['reason'])) : '';
        $comment = isset($_POST['comment']) ? sanitize_textarea_field(wp_unslash($_POST['comment'])) : '';
        $apiKey  = (string) get_option('api_key', '');

        if ($apiKey !== '' && defined('CONVEYTHIS_APP_URL')) {
            $host = wp_parse_url(home_url(), PHP_URL_HOST);
            wp_remote_post(rtrim(CONVEYTHIS_APP_URL, '/') . '/wp-churn/', array(
                'timeout'  => 4,
                'blocking' => false, // fire-and-forget: the deactivation must not wait on us
                'body'     => array(
                    'api_key' => $apiKey,
                    'domain'  => is_string($host) ? $host : '',
                    'reason'  => $reason,
                    'comment' => $comment,
                ),
            ));
        }

        wp_send_json_success();
    }
}
