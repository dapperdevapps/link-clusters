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
require_once ILC_PLUGIN_DIR . 'includes/class-ilc-gap-model.php';
require_once ILC_PLUGIN_DIR . 'includes/class-ilc-gap-finder.php';
require_once ILC_PLUGIN_DIR . 'includes/class-ilc-cluster-generation.php';
require_once ILC_PLUGIN_DIR . 'includes/class-ilc-url-discovery.php';
require_once ILC_PLUGIN_DIR . 'includes/class-ilc-ai-cluster-generator.php';

if ( is_admin() ) {
    require_once ILC_PLUGIN_DIR . 'admin/class-ilc-admin-menu.php';
    require_once ILC_PLUGIN_DIR . 'admin/class-ilc-admin-clusters-page.php';
    require_once ILC_PLUGIN_DIR . 'admin/class-ilc-admin-import-page.php';
    require_once ILC_PLUGIN_DIR . 'admin/class-ilc-admin-settings-page.php';
    require_once ILC_PLUGIN_DIR . 'admin/class-ilc-admin-bulk-builder-page.php';
    require_once ILC_PLUGIN_DIR . 'admin/class-ilc-admin-gap-page.php';
    require_once ILC_PLUGIN_DIR . 'admin/class-ilc-admin-cluster-generation-page.php';
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

    // Auto-insert logic based on builder mode
    ilc_register_auto_insert_hooks();

    // Elementor integration (load widget when Elementor is active)
    if ( ilc_is_elementor_active() ) {
        add_action( 'elementor/widgets/register', 'ilc_register_elementor_widgets' );
    }
}
add_action( 'plugins_loaded', 'ilc_init_plugin' );

/**
 * Check if Elementor is active.
 *
 * @return bool True if Elementor is loaded.
 */
function ilc_is_elementor_active() {
    return did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin' );
}

/**
 * Check if Bridge / Qode theme is active.
 *
 * @return bool True if Bridge theme or Qode framework is detected.
 */
function ilc_is_bridge_theme_active() {
    $theme    = wp_get_theme();
    $template = $theme->get_template(); // parent template
    $name     = $theme->get( 'Name' );

    // Check theme name or template for "bridge"
    if ( stripos( $name, 'bridge' ) !== false || stripos( $template, 'bridge' ) !== false ) {
        return true;
    }

    // Check for Qode framework constants
    if ( defined( 'QODE_ROOT' ) || defined( 'QODE_FRAMEWORK_ROOT' ) ) {
        return true;
    }

    return false;
}

/**
 * Get the current post ID reliably across different contexts.
 *
 * This helper function tries multiple methods to get the current post ID,
 * working around issues with Elementor, Bridge/Qode, and other builders
 * that may not set the global post context correctly.
 *
 * @return int The current post ID, or 0 if not found.
 */
function ilc_get_current_post_id() {
    // 1. Try get_the_ID() (works inside the loop)
    $post_id = get_the_ID();
    if ( $post_id ) {
        return $post_id;
    }

    // 2. Try get_queried_object_id() (works for main query)
    $post_id = get_queried_object_id();
    if ( $post_id ) {
        return $post_id;
    }

    // 3. Try global $post
    global $post;
    if ( isset( $post->ID ) ) {
        return $post->ID;
    }

    // 4. Bridge/Qode specific
    if ( function_exists( 'bridge_qode_get_page_id' ) ) {
        $post_id = bridge_qode_get_page_id();
        if ( $post_id ) {
            return $post_id;
        }
    }

    // 5. Elementor specific (document ID)
    if ( class_exists( '\Elementor\Plugin' ) ) {
        $document = \Elementor\Plugin::instance()->documents->get_current();
        if ( $document ) {
            return $document->get_main_id();
        }
    }

    return 0;
}

/**
 * Register auto-insert hooks based on builder mode setting.
 */
function ilc_register_auto_insert_hooks() {
    if ( ! class_exists( 'ILC_Settings' ) ) {
        return;
    }

    $settings     = ILC_Settings::get_settings();
    $builder_mode = isset( $settings['builder_mode'] ) ? $settings['builder_mode'] : 'default';

    switch ( $builder_mode ) {
        case 'xtra':
            // XtraTheme (Codevz) specific hooks
            // Only register if the theme is active
            if ( function_exists( 'codevz_plus' ) || defined( 'JEstarter' ) ) {
                add_action( 'codevz_after_content', 'ilc_render_auto_clusters_xtra' );
            } else {
                // Fallback to the_content if Xtra isn't detected
                add_filter( 'the_content', 'ilc_maybe_auto_insert_cluster' );
            }
            break;

        case 'elementor':
            // Elementor specific hooks
            if ( ilc_is_elementor_active() ) {
                // Hook into Elementor's frontend content filter
                add_filter( 'elementor/frontend/the_content', 'ilc_append_clusters_elementor', 20 );
                // Also hook into the_content as fallback for non-Elementor pages
                add_filter( 'the_content', 'ilc_append_clusters_elementor', 20 );
                // Fallback: use wp_footer for Elementor-built pages where the_content doesn't fire
                add_action( 'wp_footer', 'ilc_render_clusters_in_footer_elementor', 5 );
            } else {
                // Fallback to the_content if Elementor isn't active
                add_filter( 'the_content', 'ilc_maybe_auto_insert_cluster' );
            }
            break;

        case 'bridge':
            // Bridge / Qode theme specific hooks
            if ( ilc_is_bridge_theme_active() ) {
                // Primary: append clusters to the_content for Bridge pages
                // This places clusters at the bottom of the main content inside the container
                add_filter( 'the_content', 'ilc_append_clusters_to_content_bridge', 20 );
                // Also hook into Bridge's after-container action for templates that don't use the_content properly
                add_action( 'bridge_qode_action_page_after_container', 'ilc_output_clusters_bridge_after_container', 20 );
            } else {
                // Fallback to the_content if Bridge isn't active
                add_filter( 'the_content', 'ilc_maybe_auto_insert_cluster' );
            }
            break;

        case 'default':
        default:
            // Default/Generic mode - use standard the_content filter
            add_filter( 'the_content', 'ilc_maybe_auto_insert_cluster' );
            break;
    }
}

/**
 * Render clusters for XtraTheme mode.
 * Used with codevz_after_content action.
 */
function ilc_render_auto_clusters_xtra() {
    if ( ! is_singular() ) {
        return;
    }

    if ( ! class_exists( 'ILC_Settings' ) ) {
        return;
    }

    $settings = ILC_Settings::get_settings();

    if ( empty( $settings['auto_insert_enabled'] ) ) {
        return;
    }

    $post_type = get_post_type();
    $allowed   = ilc_get_allowed_post_types( $settings );

    if ( ! empty( $allowed ) && ! in_array( $post_type, $allowed, true ) ) {
        return;
    }

    $cluster_html = do_shortcode( '[rc_cluster_auto]' );

    if ( $cluster_html ) {
        echo $cluster_html;
    }
}

/**
 * Track rendered Elementor clusters to prevent duplicate output.
 * Shared between ilc_append_clusters_elementor() and ilc_render_clusters_in_footer_elementor().
 *
 * @return array Reference to the rendered posts array.
 */
function &ilc_elementor_rendered_tracker() {
    static $rendered = array();
    return $rendered;
}

/**
 * Append clusters to content for Elementor mode.
 *
 * @param string $content The post content.
 * @return string Modified content with clusters appended.
 */
function ilc_append_clusters_elementor( $content ) {
    // Prevent running in admin or non-singular contexts
    if ( is_admin() ) {
        return $content;
    }

    if ( ! is_singular() ) {
        return $content;
    }

    // Prevent double-rendering (shared with ilc_render_clusters_in_footer_elementor)
    $rendered = &ilc_elementor_rendered_tracker();
    $post_id  = function_exists( 'ilc_get_current_post_id' ) ? ilc_get_current_post_id() : get_the_ID();
    if ( isset( $rendered[ $post_id ] ) ) {
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
    $allowed   = ilc_get_allowed_post_types( $settings );

    if ( ! empty( $allowed ) && ! in_array( $post_type, $allowed, true ) ) {
        return $content;
    }

    // Use renderer method for better context handling
    if ( class_exists( 'ILC_Renderer' ) && method_exists( 'ILC_Renderer', 'render_auto_clusters_for_current_post' ) ) {
        $cluster_html = ILC_Renderer::render_auto_clusters_for_current_post();
    } else {
        $cluster_html = do_shortcode( '[rc_cluster_auto]' );
    }

    if ( empty( $cluster_html ) ) {
        return $content;
    }

    $rendered[ $post_id ] = true;

    return $content . "\n\n" . $cluster_html;
}

/**
 * Render clusters in footer for Elementor mode.
 * This is a fallback for Elementor-built pages where the_content filter doesn't fire.
 */
function ilc_render_clusters_in_footer_elementor() {
    // Prevent running in admin
    if ( is_admin() ) {
        return;
    }

    if ( ! is_singular() ) {
        return;
    }

    // Check if already rendered via the_content filter
    $rendered = &ilc_elementor_rendered_tracker();
    $post_id  = function_exists( 'ilc_get_current_post_id' ) ? ilc_get_current_post_id() : get_the_ID();
    if ( isset( $rendered[ $post_id ] ) ) {
        return;
    }

    if ( ! class_exists( 'ILC_Settings' ) ) {
        return;
    }

    $settings = ILC_Settings::get_settings();

    if ( empty( $settings['auto_insert_enabled'] ) ) {
        return;
    }

    $post_type = get_post_type();
    $allowed   = ilc_get_allowed_post_types( $settings );

    if ( ! empty( $allowed ) && ! in_array( $post_type, $allowed, true ) ) {
        return;
    }

    // Use renderer method for better context handling
    if ( class_exists( 'ILC_Renderer' ) && method_exists( 'ILC_Renderer', 'render_auto_clusters_for_current_post' ) ) {
        $cluster_html = ILC_Renderer::render_auto_clusters_for_current_post();
    } else {
        $cluster_html = do_shortcode( '[rc_cluster_auto]' );
    }

    if ( ! empty( $cluster_html ) ) {
        $rendered[ $post_id ] = true;
        // Output with a wrapper for proper placement
        echo '<div class="ilc-footer-clusters">' . $cluster_html . '</div>';
    }
}

/**
 * Track rendered Bridge clusters to prevent duplicate output.
 *
 * @return array Reference to the rendered posts array.
 */
function &ilc_bridge_rendered_tracker() {
    static $rendered = array();
    return $rendered;
}

/**
 * Append clusters to content for Bridge / Qode theme mode.
 *
 * @param string $content The post content.
 * @return string Modified content with clusters appended.
 */
function ilc_append_clusters_to_content_bridge( $content ) {
    // Prevent running in admin
    if ( is_admin() ) {
        return $content;
    }

    if ( ! is_singular() ) {
        return $content;
    }

    // Prevent double-rendering (shared with ilc_output_clusters_bridge_after_container)
    $rendered = &ilc_bridge_rendered_tracker();
    $post_id  = function_exists( 'ilc_get_current_post_id' ) ? ilc_get_current_post_id() : get_the_ID();
    if ( isset( $rendered[ $post_id ] ) ) {
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
    $allowed   = ilc_get_allowed_post_types( $settings );

    if ( ! empty( $allowed ) && ! in_array( $post_type, $allowed, true ) ) {
        return $content;
    }

    // Use ILC_Renderer if available, otherwise fall back to shortcode
    if ( class_exists( 'ILC_Renderer' ) && method_exists( 'ILC_Renderer', 'render_auto_clusters_for_current_post' ) ) {
        $cluster_html = ILC_Renderer::render_auto_clusters_for_current_post();
    } else {
        $cluster_html = do_shortcode( '[rc_cluster_auto]' );
    }

    if ( empty( $cluster_html ) ) {
        return $content;
    }

    $rendered[ $post_id ] = true;

    // Append clusters at bottom of main content
    return $content . "\n\n" . $cluster_html;
}

/**
 * Output clusters after Bridge container.
 * Used with bridge_qode_action_page_after_container action.
 *
 * This function serves as a fallback for Bridge templates that don't
 * use the_content properly. Duplicate rendering is prevented via
 * the shared ilc_bridge_rendered_tracker().
 */
function ilc_output_clusters_bridge_after_container() {
    if ( is_admin() ) {
        return;
    }

    if ( ! is_singular() ) {
        return;
    }

    // Prevent double-rendering (shared with ilc_append_clusters_to_content_bridge)
    $rendered = &ilc_bridge_rendered_tracker();
    $post_id  = function_exists( 'ilc_get_current_post_id' ) ? ilc_get_current_post_id() : get_the_ID();
    if ( isset( $rendered[ $post_id ] ) ) {
        return;
    }

    if ( ! class_exists( 'ILC_Settings' ) ) {
        return;
    }

    $settings = ILC_Settings::get_settings();

    if ( empty( $settings['auto_insert_enabled'] ) ) {
        return;
    }

    $post_type = get_post_type();
    $allowed   = ilc_get_allowed_post_types( $settings );

    if ( ! empty( $allowed ) && ! in_array( $post_type, $allowed, true ) ) {
        return;
    }

    // Use ILC_Renderer if available, otherwise fall back to shortcode
    if ( class_exists( 'ILC_Renderer' ) && method_exists( 'ILC_Renderer', 'render_auto_clusters_for_current_post' ) ) {
        $cluster_html = ILC_Renderer::render_auto_clusters_for_current_post();
    } else {
        $cluster_html = do_shortcode( '[rc_cluster_auto]' );
    }

    if ( ! empty( $cluster_html ) ) {
        $rendered[ $post_id ] = true;
        echo $cluster_html;
    }
}

/**
 * Get allowed post types from settings.
 *
 * @param array $settings Plugin settings.
 * @return array Array of allowed post type names.
 */
function ilc_get_allowed_post_types( $settings ) {
    $allowed = array();

    if ( ! empty( $settings['auto_insert_post_types'] ) ) {
        $pieces = explode( ',', $settings['auto_insert_post_types'] );
        foreach ( $pieces as $p ) {
            $p = trim( $p );
            if ( $p !== '' ) {
                $allowed[] = $p;
            }
        }
    }

    return $allowed;
}

/**
 * Register Elementor widgets.
 *
 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
 */
function ilc_register_elementor_widgets( $widgets_manager ) {
    // Load the widget class
    require_once ILC_PLUGIN_DIR . 'includes/elementor/class-ilc-elementor-cluster-widget.php';

    // Register the widget
    $widgets_manager->register( new \ILC\Elementor\ILC_Elementor_Cluster_Widget() );
}

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

    // Enqueue Font Awesome if enabled
    if ( class_exists( 'ILC_Settings' ) ) {
        $settings = ILC_Settings::get_settings();
        if ( ! empty( $settings['icon_enable_fontawesome'] ) ) {
            wp_enqueue_style(
                'ilc-fontawesome',
                'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
                array(),
                '6.4.0'
            );
        }
    }

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
 * Used for Default builder mode.
 *
 * @param string $content The post content.
 * @return string Modified content with clusters appended.
 */
function ilc_maybe_auto_insert_cluster( $content ) {
    // Prevent running in admin
    if ( is_admin() ) {
        return $content;
    }

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
    $allowed   = ilc_get_allowed_post_types( $settings );

    if ( ! empty( $allowed ) && ! in_array( $post_type, $allowed, true ) ) {
        return $content;
    }

    $cluster_html = do_shortcode( '[rc_cluster_auto]' );

    if ( ! $cluster_html ) {
        return $content;
    }

    return $content . "\n\n" . $cluster_html;
}
