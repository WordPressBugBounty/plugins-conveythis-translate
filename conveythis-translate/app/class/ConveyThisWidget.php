<?php

// Creating the widget
class ConveyThisWidget extends \WP_Widget {

    function __construct() {
        parent::__construct(

        // Base ID of your widget
            'ConveyThis',

            // Widget name will appear in UI
            __('ConveyThis', 'conveythis-translate'),

            // Widget description
            array( 'description' => __( 'ConveyThis language switcher', 'conveythis-translate' ), )
        );
    }

    // Creating widget front-end
    public function widget( $args, $instance ) {
        $title = apply_filters( 'widget_title', $instance['title'] );

        // before and after widget arguments are defined by themes
        echo esc_html($args['before_widget']);
        if ( ! empty( $title ) )
            echo esc_html($args['before_title']) . esc_html($title) . esc_html($args['after_title']);

        // This is where you run the code and display the output
        echo do_shortcode("[conveythis_switcher]");
        echo esc_html($args['after_widget']);
    }

    // Widget Backend
    public function form( $instance ) {
        $title = !empty($instance['title']) ?  filter_var($instance['title'], FILTER_SANITIZE_STRING) : '';
        // Widget admin form
        ?>
        <p>
            <label for="<?php echo  esc_attr($this->get_field_id( 'title' )); ?>"><?php esc_html_e( 'Title:' ); ?></label>
            <input class="widefat" id="<?php echo  esc_attr($this->get_field_id( 'title' )); ?>" name="<?php echo  esc_attr($this->get_field_name( 'title' )); ?>" type="text" value="<?php echo  esc_attr( $title ); ?>" />
        </p>
        <?php
    }

    // Updating widget replacing old instances with new
    public function update( $new_instance, $old_instance ) {
        $instance = array();
        $instance['title'] = ( !empty( $new_instance['title'] ) ) ? wp_strip_all_tags( $new_instance['title'] ) : '';
        return $instance;
    }
}