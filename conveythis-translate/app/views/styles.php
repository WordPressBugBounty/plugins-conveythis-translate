<?php
require_once  CONVEY_PLUGIN_ROOT_PATH . 'app/class/Variables.php';
$variables = new Variables();

wp_enqueue_style('conveythis-confetti', plugins_url('../widget/css/confetti.min.css', __FILE__), array(), CONVEYTHIS_PLUGIN_VERSION );
wp_enqueue_style('conveythis-dropdown', plugins_url('../widget/css/dropdown.min.css', __FILE__), array(), CONVEYTHIS_PLUGIN_VERSION );
wp_enqueue_style('conveythis-input', plugins_url('../widget/css/input.min.css', __FILE__), array(), CONVEYTHIS_PLUGIN_VERSION );
wp_enqueue_style('conveythis-transition', plugins_url('../widget/css/transition.min.css',__FILE__), array(), CONVEYTHIS_PLUGIN_VERSION );
wp_enqueue_style('conveythis-style', plugins_url('../widget/css/style.css',__FILE__), array(), CONVEYTHIS_PLUGIN_VERSION );
wp_enqueue_style('conveythis-bootstrap-css', '//cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css', array(), '5.0.2');
wp_enqueue_style('conveythis-toastr', '//cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css', array(), '2.1.3');
wp_enqueue_style('conveythis-slider', plugins_url('../widget/css/slider.min.css', __FILE__), array(), CONVEYTHIS_PLUGIN_VERSION);

wp_enqueue_script('conveythis-dropdown', plugins_url('../widget/js/dropdown.min.js', __FILE__), array(), CONVEYTHIS_PLUGIN_VERSION, true);
wp_enqueue_script('conveythis-toastr', '//cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js', array(), '2.1.3', false);
wp_enqueue_script('conveythis-bootstrap-js', '//cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.min.js', array(), '5.0.2', false);
wp_enqueue_script('conveythis-pusher', '//js.pusher.com/7.2/pusher.min.js', array(), '7.2.0', false);
wp_enqueue_script('conveythis-sweetalert', '//cdn.jsdelivr.net/npm/sweetalert2@11', array(), '11.11.0', false);
wp_enqueue_script('conveythis-transition', plugins_url('../widget/js/transition.min.js', __FILE__), array('jquery'), CONVEYTHIS_PLUGIN_VERSION, true);
wp_enqueue_script('conveythis-slider', plugins_url('../widget/js/slider.min.js', __FILE__), array(), CONVEYTHIS_PLUGIN_VERSION, false);
//FOR CUSTOM CSS
wp_enqueue_style('codemirror-css', CONVEYTHIS_APP_URL . '/templates/backpage/template/css/codemirror.min.css', array(), '5.63.1');
wp_enqueue_script('codemirror-js', CONVEYTHIS_APP_URL . '/templates/backpage/template/js/codemirror.min.js', array(), '5.63.1', true);
wp_enqueue_script('codemirror-css-mode', CONVEYTHIS_APP_URL . '/templates/backpage/template/js/css.min.js', array('codemirror-js'), '5.63.1', true);
wp_enqueue_script('codemirror-placeholder', CONVEYTHIS_APP_URL . '/templates/backpage/template/js/placeholder.min.js', array('codemirror-js'), '5.65.16', true);

//wp_enqueue_script('conveythis-plugin', CONVEYTHIS_JAVASCRIPT_PLUGIN_URL."/conveythis-preview.js", [], '6.3', false); old

//wp_enqueue_script('conveythis-plugin', DEV_CONVEYTHIS_JAVASCRIPT_PLUGIN_URL . "/conveythis.js?api_key=". $variables->api_key ."&preview=1", [], 65, false);
wp_enqueue_script('conveythis-plugin', CONVEYTHIS_JAVASCRIPT_PLUGIN_URL . "/conveythis.js?api_key=". $variables->api_key ."&preview=1", [], 65, false);
//wp_enqueue_script('conveythis-plugin', 'http://localhost/working_folder/cdn_conveythis/dist/conveythis.js?api_key=' . $variables->api_key . '&preview=1', [], 65, false);
wp_enqueue_script('conveythis-settings', plugins_url('../widget/js/settings.js', __FILE__), array('jquery'), CONVEYTHIS_PLUGIN_VERSION, true);
wp_localize_script('conveythis-settings', 'conveythis_plugin_ajax', array('ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('conveythis_ajax_save')));

wp_enqueue_script('conveythis-loader', plugins_url('../widget/js/' . (CONVEYTHIS_LOADER? "loader" : "loader-pause") . '.js', __FILE__), array(), CONVEYTHIS_PLUGIN_VERSION, true);

