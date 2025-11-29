<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ILC_Settings {

    const OPTION_KEY = 'ilc_settings';

    public static function get_settings() {
        $defaults = array(
            'auto_insert_enabled'    => 0,
            'auto_insert_post_types' => 'page,post',
            'layout_mode'            => 'contained', // contained | fullwidth
            // Styling defaults
            'style_bg'           => '#f7f7f7',
            'style_heading'      => '',
            'style_subtitle'     => '#555555',
            'style_box_bg'       => '#ffffff',
            'style_box_text'     => '#222222',
            'style_box_hover_bg' => '#f0f0f0',
            'style_box_border'   => '#e0e0e0',
            'style_radius'       => '12',
        );

        $stored = get_option( self::OPTION_KEY, array() );

        if ( ! is_array( $stored ) ) {
            $stored = array();
        }

        return array_merge( $defaults, $stored );
    }

    public static function update_settings( $data ) {
        $settings = self::get_settings();

        $settings['auto_insert_enabled']    = ! empty( $data['auto_insert_enabled'] ) ? 1 : 0;
        $settings['auto_insert_post_types'] = isset( $data['auto_insert_post_types'] ) ? sanitize_text_field( $data['auto_insert_post_types'] ) : $settings['auto_insert_post_types'];

        // Layout mode
        $layout_mode = isset( $data['layout_mode'] ) ? sanitize_text_field( $data['layout_mode'] ) : $settings['layout_mode'];
        if ( ! in_array( $layout_mode, array( 'contained', 'fullwidth' ), true ) ) {
            $layout_mode = 'contained';
        }
        $settings['layout_mode'] = $layout_mode;

        // Styling
        $settings['style_bg']           = isset( $data['style_bg'] ) ? sanitize_hex_color( $data['style_bg'] ) : $settings['style_bg'];
        $settings['style_heading']      = isset( $data['style_heading'] ) ? sanitize_hex_color( $data['style_heading'] ) : $settings['style_heading'];
        $settings['style_subtitle']     = isset( $data['style_subtitle'] ) ? sanitize_hex_color( $data['style_subtitle'] ) : $settings['style_subtitle'];
        $settings['style_box_bg']       = isset( $data['style_box_bg'] ) ? sanitize_hex_color( $data['style_box_bg'] ) : $settings['style_box_bg'];
        $settings['style_box_text']     = isset( $data['style_box_text'] ) ? sanitize_hex_color( $data['style_box_text'] ) : $settings['style_box_text'];
        $settings['style_box_hover_bg'] = isset( $data['style_box_hover_bg'] ) ? sanitize_hex_color( $data['style_box_hover_bg'] ) : $settings['style_box_hover_bg'];
        $settings['style_box_border']   = isset( $data['style_box_border'] ) ? sanitize_hex_color( $data['style_box_border'] ) : $settings['style_box_border'];
        $settings['style_radius']       = isset( $data['style_radius'] ) ? preg_replace( '/[^0-9]/', '', $data['style_radius'] ) : $settings['style_radius'];

        update_option( self::OPTION_KEY, $settings );
    }
}
