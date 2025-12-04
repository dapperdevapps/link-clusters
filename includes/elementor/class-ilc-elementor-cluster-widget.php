<?php
/**
 * Elementor Widget for Internal Link Clusters.
 *
 * Provides an Elementor widget that allows users to manually place
 * cluster grids within their Elementor layouts.
 *
 * @package Internal_Link_Clusters
 */

namespace ILC\Elementor;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ILC Elementor Cluster Widget.
 */
class ILC_Elementor_Cluster_Widget extends Widget_Base {

    /**
     * Get widget name.
     *
     * @return string Widget name.
     */
    public function get_name() {
        return 'ilc_cluster';
    }

    /**
     * Get widget title.
     *
     * @return string Widget title.
     */
    public function get_title() {
        return __( 'Internal Link Cluster', 'internal-link-clusters' );
    }

    /**
     * Get widget icon.
     *
     * @return string Widget icon.
     */
    public function get_icon() {
        return 'eicon-post-list';
    }

    /**
     * Get widget categories.
     *
     * @return array Widget categories.
     */
    public function get_categories() {
        return array( 'general' );
    }

    /**
     * Get widget keywords.
     *
     * @return array Widget keywords.
     */
    public function get_keywords() {
        return array( 'links', 'cluster', 'internal', 'seo', 'navigation' );
    }

    /**
     * Register widget controls.
     */
    protected function register_controls() {
        // Content Section
        $this->start_controls_section(
            'content_section',
            array(
                'label' => __( 'Cluster Settings', 'internal-link-clusters' ),
                'tab'   => Controls_Manager::TAB_CONTENT,
            )
        );

        // Mode selector
        $this->add_control(
            'cluster_mode',
            array(
                'label'   => __( 'Display Mode', 'internal-link-clusters' ),
                'type'    => Controls_Manager::SELECT,
                'default' => 'auto',
                'options' => array(
                    'auto'     => __( 'Auto (current page\'s cluster)', 'internal-link-clusters' ),
                    'specific' => __( 'Specific cluster by slug', 'internal-link-clusters' ),
                ),
            )
        );

        // Cluster slug (shown when specific mode is selected)
        $this->add_control(
            'cluster_slug',
            array(
                'label'       => __( 'Cluster Slug', 'internal-link-clusters' ),
                'type'        => Controls_Manager::TEXT,
                'placeholder' => __( 'e.g., epoxy-flooring', 'internal-link-clusters' ),
                'description' => __( 'Enter the slug of the cluster you want to display.', 'internal-link-clusters' ),
                'condition'   => array(
                    'cluster_mode' => 'specific',
                ),
            )
        );

        // Available clusters info
        $this->add_control(
            'clusters_info',
            array(
                'type'            => Controls_Manager::RAW_HTML,
                'raw'             => $this->get_clusters_list_html(),
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
                'condition'       => array(
                    'cluster_mode' => 'specific',
                ),
            )
        );

        $this->end_controls_section();

        // Style Section
        $this->start_controls_section(
            'style_section',
            array(
                'label' => __( 'Display Options', 'internal-link-clusters' ),
                'tab'   => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'style_note',
            array(
                'type'            => Controls_Manager::RAW_HTML,
                'raw'             => __( 'Cluster styles are configured in the Internal Link Clusters plugin settings.', 'internal-link-clusters' ),
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
            )
        );

        $this->end_controls_section();
    }

    /**
     * Get HTML list of available clusters for the control.
     *
     * @return string HTML content.
     */
    private function get_clusters_list_html() {
        if ( ! class_exists( 'ILC_Cluster_Model' ) ) {
            return __( 'Unable to load clusters.', 'internal-link-clusters' );
        }

        $clusters = \ILC_Cluster_Model::get_all_clusters();

        if ( empty( $clusters ) ) {
            return __( 'No clusters found. Create clusters in the Internal Link Clusters admin.', 'internal-link-clusters' );
        }

        $html = '<strong>' . __( 'Available clusters:', 'internal-link-clusters' ) . '</strong><br>';
        $html .= '<ul style="margin: 5px 0 0 15px; padding: 0; list-style: disc;">';
        
        $count = 0;
        foreach ( $clusters as $cluster ) {
            if ( $count >= 10 ) {
                $remaining = count( $clusters ) - 10;
                $html .= '<li><em>' . sprintf( __( '...and %d more', 'internal-link-clusters' ), $remaining ) . '</em></li>';
                break;
            }
            $html .= '<li><code>' . esc_html( $cluster->slug ) . '</code> - ' . esc_html( $cluster->name ) . '</li>';
            $count++;
        }
        
        $html .= '</ul>';

        return $html;
    }

    /**
     * Render widget output on the frontend.
     */
    protected function render() {
        $settings = $this->get_settings_for_display();
        $mode     = isset( $settings['cluster_mode'] ) ? $settings['cluster_mode'] : 'auto';

        if ( $mode === 'specific' ) {
            // Render specific cluster by slug
            $slug = isset( $settings['cluster_slug'] ) ? sanitize_title( $settings['cluster_slug'] ) : '';

            if ( empty( $slug ) ) {
                if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                    echo '<div class="elementor-alert elementor-alert-info">';
                    echo esc_html__( 'Please enter a cluster slug to display.', 'internal-link-clusters' );
                    echo '</div>';
                }
                return;
            }

            echo $this->render_cluster_by_slug( $slug );
        } else {
            // Auto mode - use renderer for better context handling in Elementor
            if ( class_exists( 'ILC_Renderer' ) && method_exists( 'ILC_Renderer', 'render_auto_clusters_for_current_post' ) ) {
                echo \ILC_Renderer::render_auto_clusters_for_current_post();
            } else {
                echo do_shortcode( '[rc_cluster_auto]' );
            }
        }
    }

    /**
     * Render a cluster by its slug.
     *
     * @param string $slug Cluster slug.
     * @return string Rendered cluster HTML.
     */
    private function render_cluster_by_slug( $slug ) {
        if ( ! class_exists( 'ILC_Cluster_Model' ) || ! class_exists( 'ILC_Renderer' ) ) {
            if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                return '<div class="elementor-alert elementor-alert-warning">' . 
                       esc_html__( 'Internal Link Clusters plugin classes not loaded.', 'internal-link-clusters' ) . 
                       '</div>';
            }
            return '';
        }

        $cluster = \ILC_Cluster_Model::get_cluster_by_identifier( $slug );

        if ( ! $cluster ) {
            if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                return '<div class="elementor-alert elementor-alert-warning">' . 
                       sprintf( 
                           /* translators: %s: cluster slug */
                           esc_html__( 'Cluster "%s" not found.', 'internal-link-clusters' ), 
                           esc_html( $slug ) 
                       ) . 
                       '</div>';
            }
            return '';
        }

        if ( empty( $cluster->is_active ) ) {
            if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                return '<div class="elementor-alert elementor-alert-info">' . 
                       esc_html__( 'This cluster is inactive.', 'internal-link-clusters' ) . 
                       '</div>';
            }
            return '';
        }

        // Use the robust helper for post ID detection in Elementor context
        $current_post_id = function_exists( 'ilc_get_current_post_id' )
            ? ilc_get_current_post_id()
            : get_the_ID();
        $current_url     = $current_post_id ? get_permalink( $current_post_id ) : '';

        return \ILC_Renderer::render_cluster( $cluster, $current_url, $current_post_id );
    }

    /**
     * Render widget output in the editor.
     */
    protected function content_template() {
        ?>
        <#
        var mode = settings.cluster_mode || 'auto';
        var slug = settings.cluster_slug || '';
        #>
        <div class="ilc-elementor-widget-preview">
            <# if ( mode === 'specific' && ! slug ) { #>
                <div class="elementor-alert elementor-alert-info">
                    <?php echo esc_html__( 'Please enter a cluster slug to display.', 'internal-link-clusters' ); ?>
                </div>
            <# } else { #>
                <div class="elementor-alert elementor-alert-info">
                    <# if ( mode === 'auto' ) { #>
                        <?php echo esc_html__( 'Auto mode: Will display the cluster for the current page.', 'internal-link-clusters' ); ?>
                    <# } else { #>
                        <?php echo esc_html__( 'Displaying cluster:', 'internal-link-clusters' ); ?> <strong>{{{ slug }}}</strong>
                    <# } #>
                </div>
            <# } #>
        </div>
        <?php
    }
}

