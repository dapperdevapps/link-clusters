<?php
/*
Plugin Name: Internal Link Clusters
Description: Manage internal-link hub grids (clusters) and render them via shortcodes, with admin UI, styling controls, layout modes, and auto-insert.
Version: 0.4.0
Author: Your Name
Text Domain: internal-link-clusters
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'ILC_VERSION', '0.4.0' );
define( 'ILC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ILC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Includes
require_once ILC_PLUGIN_DIR . 'includes/class-ilc-installer.php';
require_once ILC_PLUGIN_DIR . 'includes/class-ilc-cluster-model.php';
require_once ILC_PLUGIN_DIR . 'includes/class-ilc-renderer.php';
require_once ILC_PLUGIN_DIR . 'includes/class-ilc-shortcodes.php';
require_once ILC_PLUGIN_DIR . 'includes/class-ilc-settings.php';

if ( is_admin() ) {
    require_once ILC_PLUGIN_DIR . 'admin/class-ilc-admin-menu.php';
    require_once ILC_PLUGIN_DIR . 'admin/class-ilc-admin-clusters-page.php';
    require_once ILC_PLUGIN_DIR . 'admin/class-ilc-admin-import-page.php';
    require_once ILC_PLUGIN_DIR . 'admin/class-ilc-admin-settings-page.php';
}

/**
 * Activation hook - install DB tables.
 */
function ilc_activate_plugin() {
    ILC_Installer::install();
}
register_activation_hook( __FILE__, 'ilc_activate_plugin' );

/**
 * Init plugin.
 */
function ilc_init_plugin() {
    // Shortcodes
    ILC_Shortcodes::init();

    // Frontend styles
    add_action( 'wp_enqueue_scripts', 'ilc_enqueue_styles' );

    // Admin
    if ( is_admin() ) {
        ILC_Admin_Menu::init();
    }

    // Auto-insert logic
    add_filter( 'the_content', 'ilc_maybe_auto_insert_cluster' );
}
add_action( 'plugins_loaded', 'ilc_init_plugin' );

/**
 * Enqueue frontend styles + custom styling variables.
 */
function ilc_enqueue_styles() {
    wp_enqueue_style(
        'ilc-rc-clusters',
        ILC_PLUGIN_URL . 'public/css/rc-clusters.css',
        array(),
        ILC_VERSION
    );

    if ( ! class_exists( 'ILC_Settings' ) ) {
        return;
    }

    $settings = ILC_Settings::get_settings();

    $bg        = $settings['style_bg'];
    $heading   = $settings['style_heading'];
    $subtitle  = $settings['style_subtitle'];
    $box_bg    = $settings['style_box_bg'];
    $box_text  = $settings['style_box_text'];
    $box_hover = $settings['style_box_hover_bg'];
    $border    = $settings['style_box_border'];
    $radius    = (int) $settings['style_radius'];

    $css  = ".rc-cluster{";
    if ( $bg )        $css .= "background-color: {$bg};";
    if ( $radius )    $css .= "border-radius: {$radius}px;";
    $css .= "}";

    if ( $heading )   $css .= ".rc-heading{color: {$heading};}";
    if ( $subtitle )  $css .= ".rc-subtitle{color: {$subtitle};}";

    $css .= ".rc-box{";
    if ( $box_bg )    $css .= "background-color: {$box_bg};";
    if ( $box_text )  $css .= "color: {$box_text};";
    if ( $border )    $css .= "border-color: {$border};";
    if ( $radius )    $css .= "border-radius: {$radius}px;";
    $css .= "}";

    if ( $box_hover ) {
        $css .= ".rc-box:hover,.rc-box:focus-visible{background-color: {$box_hover};}";
    }

    wp_add_inline_style( 'ilc-rc-clusters', $css );
}

/**
 * Auto-insert [rc_cluster_auto] into content based on settings.
 *
 * @param string $content
 * @return string
 */
function ilc_maybe_auto_insert_cluster( $content ) {
    if ( ! is_singular() ) {
        return $content;
    }

    if ( ! class_exists( 'ILC_Settings' ) ) {
        return $content;
    }

    $settings = ILC_Settings::get_settings();

    if ( empty( $settings['auto_insert_enabled'] ) ) {
        return $content;
    }

    $post_type = get_post_type();
    $allowed   = array();

    if ( ! empty( $settings['auto_insert_post_types'] ) ) {
        $pieces = explode( ',', $settings['auto_insert_post_types'] );
        foreach ( $pieces as $p ) {
            $p = trim( $p );
            if ( $p !== '' ) {
                $allowed[] = $p;
            }
        }
    }

    if ( ! empty( $allowed ) && ! in_array( $post_type, $allowed, true ) ) {
        return $content;
    }

    $cluster_html = do_shortcode( '[rc_cluster_auto]' );

    if ( ! $cluster_html ) {
        return $content;
    }

    return $content . "\n\n" . $cluster_html;
}
