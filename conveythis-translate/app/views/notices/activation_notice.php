<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$plugin_path = admin_url('options-general.php?page=convey_this');

?>
<div class="error settings-error notice is-dismissible" style="margin-left: 2px">
    <p>
        <?php
        // Translators: %1$s is the opening anchor tag, %2$s is the closing anchor tag.
        echo sprintf( esc_html__( 'ConveyThis Translate is installed but not set up yet. Please configure ConveyThis on the %1$sconfiguration page.%2$s Setting it up only takes 1 minute! ', 'conveythis' ), '<a href="' . esc_url( $plugin_path ) . '">', '</a>' );
        ?>
    </p>
</div>
