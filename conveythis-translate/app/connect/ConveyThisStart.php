<?php
require_once( ABSPATH . 'wp-includes/pluggable.php' );

if ( isset($_POST['set_api_key']) && $_POST['set_api_key'] == 1 )
{
	if ( isset( $_POST['csrf'] ) ) {
		if ( wp_verify_nonce( $_POST['csrf'], 'submit_action' ) ) {
			$api_key = sanitize_text_field( $_POST['api_key'] );
			update_option('api_key', $_POST['api_key']);
		} else {
			wp_send_json_error( 'WPNonce validation error' );
		}
	} else {
		wp_send_json_error( 'Permission denied' );
	}
}



if( isset($_POST['ready_user']) ) //phpcs:ignore
{
    update_option('conveythis_new_user', 0);
}

// Register and load the widget
function wp_register_widget() {
    register_widget( 'ConveyThisWidget' );
}