<?php
/**
 * ILC_Shortcodes - Universal shortcodes for all page builders.
 *
 * These shortcodes work with:
 * - Elementor (Shortcode widget)
 * - WPBakery (Text Block)
 * - Bridge Theme (HTML block)
 * - Divi (Code module)
 * - Avada (Code block)
 * - Gutenberg (Shortcode block)
 * - Any other builder or theme
 *
 * @package Internal_Link_Clusters
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ILC_Shortcodes {

    /**
     * Initialize shortcodes.
     */
    public static function init() {
        // Shortcode A: Render a specific cluster by name/slug
        add_shortcode( 'rc_cluster', array( __CLASS__, 'shortcode_cluster' ) );

        // Shortcode B: Auto-display clusters for the current page
        add_shortcode( 'rc_cluster_auto', array( __CLASS__, 'shortcode_cluster_auto' ) );
    }

    /**
     * Shortcode: [rc_cluster name="cluster-slug"]
     *
     * Renders a specific cluster by its slug or name.
     * Use this when you want to manually place a specific cluster.
     *
     * @param array $atts Shortcode attributes.
     * @return string Cluster HTML or empty string.
     */
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

        // Use the renderer's helper method for clean, consistent rendering
        return ILC_Renderer::render_cluster_by_slug( sanitize_title( $atts['name'] ) );
    }

    /**
     * Shortcode: [rc_cluster_auto]
     *
     * Automatically displays the cluster associated with the current page.
     * Use this in page builder widgets/blocks for manual placement.
     *
     * @return string Cluster HTML or empty string.
     */
    public static function shortcode_cluster_auto() {
        // Use the renderer's helper method for clean, consistent rendering
        return ILC_Renderer::render_auto_clusters_for_current_post();
    }
}
