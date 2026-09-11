<?php
require_once  CONVEY_PLUGIN_ROOT_PATH . 'app/class/Variables.php';
$variables = new Variables();

/**
 * Cache-busting version for the plugin's OWN assets.
 *
 * Everything below used to be enqueued with CONVEYTHIS_PLUGIN_VERSION, which is
 * hard-coded to the release number in config.php. That means the URL only
 * changes when the release number changes — so any CSS or JS edit shipped
 * inside the same version is invisible to every browser that already cached it.
 * That cost a full round of "nothing changed" during the admin redesign: the
 * PHP edits took effect immediately, the stylesheet edits did not.
 *
 * filemtime() ties the query string to the file itself: edit the file, the URL
 * changes, the browser refetches. It falls back to the release number if the
 * file is somehow unreadable.
 */
if (!function_exists('conveythis_asset_ver')) {
function conveythis_asset_ver($relative_path) {
    $full = CONVEY_PLUGIN_ROOT_PATH . 'app/' . ltrim($relative_path, '/');
    $mtime = @filemtime($full);
    return $mtime ? (string) $mtime : CONVEYTHIS_PLUGIN_VERSION;
}
}

wp_enqueue_style('conveythis-confetti', plugins_url('../widget/css/confetti.min.css', __FILE__), array(), conveythis_asset_ver('widget/css/confetti.min.css') );
wp_enqueue_style('conveythis-dropdown', plugins_url('../widget/css/dropdown.min.css', __FILE__), array(), conveythis_asset_ver('widget/css/dropdown.min.css') );
wp_enqueue_style('conveythis-input', plugins_url('../widget/css/input.min.css', __FILE__), array(), conveythis_asset_ver('widget/css/input.min.css') );
wp_enqueue_style('conveythis-transition', plugins_url('../widget/css/transition.min.css',__FILE__), array(), conveythis_asset_ver('widget/css/transition.min.css') );
wp_enqueue_style('conveythis-style', plugins_url('../widget/css/style.css',__FILE__), array(), conveythis_asset_ver('widget/css/style.css') );
wp_enqueue_style('conveythis-bootstrap-css', '//cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css', array(), '5.0.2');
wp_enqueue_style('conveythis-toastr', '//cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css', array(), '2.1.3');
wp_enqueue_style('conveythis-slider', plugins_url('../widget/css/slider.min.css', __FILE__), array(), conveythis_asset_ver('widget/css/slider.min.css'));

// ── ConveyThis WordPress admin UI pilot — TEST BUILD ─────────────────────────
// Enqueued last so it can restyle what the sheets above set, without any
// !important. Every rule inside is scoped to .ct-wp-pilot — the class on this
// plugin's own .wrap — so nothing here can reach wp-admin or another plugin.
// Local file: adds no framework and no external request.
wp_enqueue_style('conveythis-admin-pilot', plugins_url('../widget/css/ct-admin-pilot.css', __FILE__), array(), conveythis_asset_ver('widget/css/ct-admin-pilot.css'));

wp_enqueue_script('conveythis-dropdown', plugins_url('../widget/js/dropdown.min.js', __FILE__), array(), conveythis_asset_ver('widget/js/dropdown.min.js'), true);
wp_enqueue_script('conveythis-toastr', '//cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js', array(), '2.1.3', false);
wp_enqueue_script('conveythis-bootstrap-js', '//cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.min.js', array(), '5.0.2', false);
wp_enqueue_script('conveythis-pusher', '//js.pusher.com/7.2/pusher.min.js', array(), '7.2.0', false);
wp_enqueue_script('conveythis-sweetalert', '//cdn.jsdelivr.net/npm/sweetalert2@11', array(), '11.11.0', false);
wp_enqueue_script('conveythis-transition', plugins_url('../widget/js/transition.min.js', __FILE__), array('jquery'), conveythis_asset_ver('widget/js/transition.min.js'), true);
wp_enqueue_script('conveythis-slider', plugins_url('../widget/js/slider.min.js', __FILE__), array(), conveythis_asset_ver('widget/js/slider.min.js'), false);
//FOR CUSTOM CSS
wp_enqueue_style('codemirror-css', CONVEYTHIS_APP_URL . '/templates/backpage/template/css/codemirror.min.css', array(), '5.63.1');
wp_enqueue_script('codemirror-js', CONVEYTHIS_APP_URL . '/templates/backpage/template/js/codemirror.min.js', array(), '5.63.1', true);
wp_enqueue_script('codemirror-css-mode', CONVEYTHIS_APP_URL . '/templates/backpage/template/js/css.min.js', array('codemirror-js'), '5.63.1', true);
wp_enqueue_script('codemirror-placeholder', CONVEYTHIS_APP_URL . '/templates/backpage/template/js/placeholder.min.js', array('codemirror-js'), '5.65.16', true);

//wp_enqueue_script('conveythis-plugin', CONVEYTHIS_JAVASCRIPT_PLUGIN_URL."/conveythis-preview.js", [], '6.3', false); old

//wp_enqueue_script('conveythis-plugin', DEV_CONVEYTHIS_JAVASCRIPT_PLUGIN_URL . "/conveythis.js?api_key=". $variables->api_key ."&preview=1", [], 65, false);
wp_enqueue_script('conveythis-plugin', CONVEYTHIS_JAVASCRIPT_PLUGIN_URL . "/conveythis.js?api_key=". $variables->api_key ."&preview=1", [], 65, false);
//wp_enqueue_script('conveythis-plugin', 'http://localhost/working_folder/cdn_conveythis/dist/conveythis.js?api_key=' . $variables->api_key . '&preview=1', [], 65, false);
wp_enqueue_script('conveythis-settings', plugins_url('../widget/js/settings.js', __FILE__), array('jquery'), conveythis_asset_ver('widget/js/settings.js'), true);
wp_localize_script('conveythis-settings', 'conveythis_plugin_ajax', array('ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('conveythis_ajax_save')));

wp_enqueue_script('conveythis-loader', plugins_url('../widget/js/' . (CONVEYTHIS_LOADER? "loader" : "loader-pause") . '.js', __FILE__), array(), conveythis_asset_ver('widget/js/' . (CONVEYTHIS_LOADER? "loader" : "loader-pause") . '.js'), true);

