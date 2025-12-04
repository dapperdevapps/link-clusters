<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ILC_Shortcodes {

    public static function init() {
        add_shortcode( 'rc_cluster', array( __CLASS__, 'shortcode_cluster' ) );
        add_shortcode( 'rc_cluster_auto', array( __CLASS__, 'shortcode_cluster_auto' ) );
    }

    public static function shortcode_cluster( $atts ) {
        $atts = shortcode_atts(
            array(
                'name' => '',
            ),
            $atts,
            'rc_cluster'
        );

        if ( empty( $atts['name'] ) ) {
            return '';
        }

        $cluster = ILC_Cluster_Model::get_cluster_by_identifier( $atts['name'] );

        if ( ! $cluster ) {
            return '';
        }

        // Use the robust helper for post ID detection across different builders
        $current_post_id = function_exists( 'ilc_get_current_post_id' )
            ? ilc_get_current_post_id()
            : get_queried_object_id();
        $current_url     = $current_post_id ? get_permalink( $current_post_id ) : '';

        return ILC_Renderer::render_cluster( $cluster, $current_url, $current_post_id );
    }

    public static function shortcode_cluster_auto() {
        // Use the robust helper for post ID detection across different builders
        $current_post_id = function_exists( 'ilc_get_current_post_id' )
            ? ilc_get_current_post_id()
            : get_queried_object_id();

        $current_url = $current_post_id ? get_permalink( $current_post_id ) : self::get_current_url();

        $cluster = ILC_Cluster_Model::get_cluster_for_page( $current_post_id, $current_url );

        if ( ! $cluster ) {
            return '';
        }

        return ILC_Renderer::render_cluster( $cluster, $current_url, $current_post_id );
    }

    protected static function get_current_url() {
        $scheme = is_ssl() ? 'https://' : 'http://';
        $host   = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : '';
        $uri    = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';

        return $scheme . $host . $uri;
    }
}
