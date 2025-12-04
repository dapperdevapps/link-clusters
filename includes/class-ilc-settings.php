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
            'builder_mode'           => 'default', // default | xtra | elementor
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
            // SEO defaults
            'seo_schema_enabled'     => 1,
            'seo_add_title_attr'     => 1,
            'seo_default_rel'        => '', // empty = follow, 'nofollow' = nofollow
            'seo_open_new_tab'        => 0,
            'seo_add_aria_label'     => 1,
            'seo_max_links'           => '', // empty = no limit
            // Icon defaults
            'icon_position'            => 'left', // left, right, above
            'icon_color_default'       => '', // empty = inherit text color
            'icon_enable_fontawesome' => 1, // Enable Font Awesome loading
            // Gap Finder defaults
            'gap_post_types'   => 'page,post', // Post types to scan for internal linking gaps
            'gap_search_title' => 1, // Whether to search in post titles (in addition to content)
            // AI Cluster Generation defaults
            'ai_cluster_enabled'      => 0,
            'ai_cluster_api_key'      => '',
            'ai_cluster_api_endpoint' => '',
            'ai_cluster_model'        => '',
            'ai_cluster_max_urls'     => 200,
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

        // Builder mode
        $builder_mode = isset( $data['builder_mode'] ) ? sanitize_text_field( $data['builder_mode'] ) : $settings['builder_mode'];
        if ( ! in_array( $builder_mode, array( 'default', 'xtra', 'elementor', 'bridge' ), true ) ) {
            $builder_mode = 'default';
        }
        $settings['builder_mode'] = $builder_mode;

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

        // SEO settings
        $settings['seo_schema_enabled'] = ! empty( $data['seo_schema_enabled'] ) ? 1 : 0;
        $settings['seo_add_title_attr'] = ! empty( $data['seo_add_title_attr'] ) ? 1 : 0;
        $settings['seo_default_rel']    = isset( $data['seo_default_rel'] ) ? sanitize_text_field( $data['seo_default_rel'] ) : $settings['seo_default_rel'];
        $settings['seo_open_new_tab']   = ! empty( $data['seo_open_new_tab'] ) ? 1 : 0;
        $settings['seo_add_aria_label'] = ! empty( $data['seo_add_aria_label'] ) ? 1 : 0;
        $settings['seo_max_links']      = isset( $data['seo_max_links'] ) ? preg_replace( '/[^0-9]/', '', $data['seo_max_links'] ) : $settings['seo_max_links'];

        // Icon settings
        $icon_position = isset( $data['icon_position'] ) ? sanitize_text_field( $data['icon_position'] ) : 'left';
        if ( ! in_array( $icon_position, array( 'left', 'right', 'above' ), true ) ) {
            $icon_position = 'left';
        }
        $settings['icon_position']            = $icon_position;
        $settings['icon_color_default']       = isset( $data['icon_color_default'] ) ? sanitize_hex_color( $data['icon_color_default'] ) : '';
        $settings['icon_enable_fontawesome']  = ! empty( $data['icon_enable_fontawesome'] ) ? 1 : 0;

        // Gap Finder settings
        $settings['gap_post_types']   = isset( $data['gap_post_types'] ) ? sanitize_text_field( $data['gap_post_types'] ) : $settings['gap_post_types'];
        $settings['gap_search_title'] = ! empty( $data['gap_search_title'] ) ? 1 : 0;

        // AI Cluster Generation settings
        $settings['ai_cluster_enabled']      = ! empty( $data['ai_cluster_enabled'] ) ? 1 : 0;
        $settings['ai_cluster_api_key']      = isset( $data['ai_cluster_api_key'] ) ? sanitize_text_field( $data['ai_cluster_api_key'] ) : $settings['ai_cluster_api_key'];
        $settings['ai_cluster_api_endpoint'] = isset( $data['ai_cluster_api_endpoint'] ) ? esc_url_raw( $data['ai_cluster_api_endpoint'] ) : $settings['ai_cluster_api_endpoint'];
        $settings['ai_cluster_model']        = isset( $data['ai_cluster_model'] ) ? sanitize_text_field( $data['ai_cluster_model'] ) : $settings['ai_cluster_model'];
        $settings['ai_cluster_max_urls']     = isset( $data['ai_cluster_max_urls'] ) ? absint( $data['ai_cluster_max_urls'] ) : $settings['ai_cluster_max_urls'];

        // Ensure max_urls has a sensible default
        if ( $settings['ai_cluster_max_urls'] < 1 ) {
            $settings['ai_cluster_max_urls'] = 200;
        }

        update_option( self::OPTION_KEY, $settings );
    }
}
