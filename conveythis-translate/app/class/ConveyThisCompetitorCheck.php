<?php
class ConveyThisCompetitorCheck
{
    protected static $conflicts = [];
    protected static $plugin_names = [];

    public static function check_conflicts() {
        $competitors = [
            'gtranslate/gtranslate.php' => 'GTranslate',
            'weglot/weglot.php' => 'Weglot',
            'localizejs/localizejs.php' => 'Localize',
            'lokalise/lokalise.php' => 'Lokalise',
            'polylang/polylang.php' => 'Polylang',
        ];

        $active_plugins = get_option('active_plugins', []);

        foreach ($competitors as $path => $name) {
            if (in_array($path, $active_plugins, true)) {
                self::$conflicts[] = $path;
                self::$plugin_names[$path] = $name;
            }
        }
    }

    public static function admin_notice() {
        if (!current_user_can('activate_plugins')) return;
        if (empty(self::$conflicts)) return;

        // Handle deactivation action
        if (isset($_GET['conveythis-deactivate']) && check_admin_referer('conveythis_deactivate_plugins')) {
            foreach (self::$conflicts as $plugin) {
                deactivate_plugins($plugin);
            }
            echo '<div class="notice notice-success is-dismissible"><p>Conflicting plugins have been deactivated successfully.</p></div>';
            self::$conflicts = [];
            return;
        }

        echo '<div class="notice notice-warning is-dismissible" style="border-left: 4px solid #ffc107; padding: 15px; background: #fff8e1;">';
        echo '<h4 style="margin-top: 0; margin-bottom: 10px;"><strong>Potential Plugin Conflicts Detected</strong></h4>';
        echo '<p>The following translation plugins are active and may conflict with <strong>ConveyThis</strong>:</p>';
        echo '<ul style="margin-bottom: 10px;">';
        foreach (self::$conflicts as $plugin) {
            $name = self::$plugin_names[$plugin] ?? $plugin;
            echo '<li><strong>' . esc_html($name) . '</strong></li>';
        }
        echo '</ul>';
        echo '<p>Please consider deactivating them to ensure full compatibility with ConveyThis.</p>';
        $deactivate_url = wp_nonce_url(add_query_arg('conveythis-deactivate', 'true'), 'conveythis_deactivate_plugins');
        echo '<p><a href="' . esc_url($deactivate_url) . '" class="button button-secondary">Deactivate Conflicting Plugins</a></p>';
        echo '</div>';
    }
}