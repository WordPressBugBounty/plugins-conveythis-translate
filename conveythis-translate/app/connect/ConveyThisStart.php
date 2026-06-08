<?php
require_once( ABSPATH . 'wp-includes/pluggable.php' );

// Register and load the widget
function wp_register_widget() {
    register_widget( 'ConveyThisWidget' );
}
