<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ILC_Admin_Menu {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_scripts' ) );
    }

    public static function register_menu() {
        add_menu_page(
            __( 'Internal Link Clusters', 'internal-link-clusters' ),
            __( 'Internal Link Clusters', 'internal-link-clusters' ),
            'manage_options',
            'ilc-clusters',
            array( 'ILC_Admin_Clusters_Page', 'render' ),
            'dashicons-networking',
            56
        );

        add_submenu_page(
            'ilc-clusters',
            __( 'Clusters', 'internal-link-clusters' ),
            __( 'Clusters', 'internal-link-clusters' ),
            'manage_options',
            'ilc-clusters',
            array( 'ILC_Admin_Clusters_Page', 'render' )
        );

        add_submenu_page(
            'ilc-clusters',
            __( 'Import URLs', 'internal-link-clusters' ),
            __( 'Import URLs', 'internal-link-clusters' ),
            'manage_options',
            'ilc-import',
            array( 'ILC_Admin_Import_Page', 'render' )
        );

        add_submenu_page(
            'ilc-clusters',
            __( 'Settings', 'internal-link-clusters' ),
            __( 'Settings', 'internal-link-clusters' ),
            'manage_options',
            'ilc-settings',
            array( 'ILC_Admin_Settings_Page', 'render' )
        );
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @param string $hook Current admin page hook.
     */
    public static function enqueue_admin_scripts( $hook ) {
        // Load color picker on settings and clusters pages
        if ( strpos( $hook, 'ilc-settings' ) !== false || strpos( $hook, 'ilc-clusters' ) !== false ) {
            // Enqueue WordPress color picker
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_script( 'wp-color-picker' );

            // Enqueue custom script to initialize color pickers
            wp_add_inline_script(
                'wp-color-picker',
                'jQuery(document).ready(function($) {
                    $(".ilc-color-picker").wpColorPicker();
                    $(".ilc-color-picker-small").wpColorPicker();
                });'
            );

            // Enqueue icon picker (only on clusters page)
            if ( strpos( $hook, 'ilc-clusters' ) !== false ) {
                // Enqueue Font Awesome for icon picker (use CDN version for admin)
                wp_enqueue_style(
                    'ilc-fontawesome-admin',
                    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css',
                    array(),
                    '4.7.0'
                );

                wp_enqueue_style(
                    'ilc-icon-picker',
                    ILC_PLUGIN_URL . 'admin/css/ilc-icon-picker.css',
                    array( 'ilc-fontawesome-admin' ),
                    ILC_VERSION
                );

                wp_enqueue_script(
                    'ilc-icon-picker',
                    ILC_PLUGIN_URL . 'admin/js/ilc-icon-picker.js',
                    array( 'jquery' ),
                    ILC_VERSION,
                    true
                );

                // Localize script with translations
                wp_localize_script(
                    'ilc-icon-picker',
                    'ilcIconPickerL10n',
                    array(
                        'pickIcon'   => __( 'Pick Icon', 'internal-link-clusters' ),
                        'selectIcon' => __( 'Select Font Awesome Icon', 'internal-link-clusters' ),
                        'searchIcons' => __( 'Search icons...', 'internal-link-clusters' ),
                        'cancel'     => __( 'Cancel', 'internal-link-clusters' ),
                    )
                );
            }
        }
    }
}
