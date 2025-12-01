<?php
/**
 * ILC_Admin_Cluster_Generation_Page - Admin UI for AI-powered cluster generation.
 *
 * Provides a dedicated screen where users can:
 * - Scan site URLs and use AI to suggest clusters (primary flow)
 * - Review suggested clusters and create them
 *
 * @package Internal_Link_Clusters
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ILC_Admin_Cluster_Generation_Page {

    /**
     * Render the Cluster Generation admin page.
     */
    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $step = isset( $_POST['ilc_cluster_gen_step'] ) ? sanitize_text_field( wp_unslash( $_POST['ilc_cluster_gen_step'] ) ) : 'input';

        // Handle form submissions
        if ( $step === 'generate_ai' ) {
            self::handle_generate_ai();
            return;
        }

        if ( $step === 'create_clusters' ) {
            self::handle_create_clusters();
            return;
        }

        // Default: Show initial input form
        self::render_input_form();
    }

    /**
     * Render the initial input form with AI generation option.
     *
     * @param string $error_message Optional error message to display.
     */
    private static function render_input_form( $error_message = '' ) {
        // Get AI config status
        $ai_status    = ILC_AI_Cluster_Generator::get_config_status();
        $scan_summary = ILC_URL_Discovery::get_scan_summary();
        $settings     = ILC_Settings::get_settings();
        $max_urls     = absint( $settings['ai_cluster_max_urls'] );
        if ( $max_urls < 1 ) {
            $max_urls = 200;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Cluster Generation', 'internal-link-clusters' ); ?></h1>

            <?php if ( ! empty( $error_message ) ) : ?>
                <div class="notice notice-error">
                    <p><?php echo esc_html( $error_message ); ?></p>
                </div>
            <?php endif; ?>

            <!-- AI Cluster Generation Section -->
            <div class="ilc-generation-section" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin-bottom: 20px;">
                <h2 style="margin-top: 0;">
                    <span class="dashicons dashicons-lightbulb" style="color: #0073aa;"></span>
                    <?php esc_html_e( 'AI Cluster Generation', 'internal-link-clusters' ); ?>
                </h2>

                <p class="description">
                    <?php esc_html_e( 'Use AI to automatically analyze your site URLs and suggest meaningful clusters based on content structure and topics.', 'internal-link-clusters' ); ?>
                </p>

                <?php if ( ! $ai_status['configured'] ) : ?>
                    <!-- AI Not Configured Warning -->
                    <div class="notice notice-warning inline" style="margin: 15px 0;">
                        <p>
                            <strong><?php esc_html_e( 'AI Not Configured', 'internal-link-clusters' ); ?></strong><br>
                            <?php echo esc_html( $ai_status['message'] ); ?>
                        </p>
                        <p>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=ilc-settings' ) ); ?>" class="button">
                                <?php esc_html_e( 'Configure AI Settings', 'internal-link-clusters' ); ?>
                            </a>
                        </p>
                    </div>
                <?php else : ?>
                    <!-- AI Configured - Show Scan Info -->
                    <div class="ilc-scan-summary" style="background: #f0f6fc; border: 1px solid #c3c4c7; border-left: 4px solid #0073aa; border-radius: 4px; padding: 15px; margin: 15px 0;">
                        <strong><?php esc_html_e( 'Content to analyze:', 'internal-link-clusters' ); ?></strong>
                        <ul style="margin: 10px 0 0 20px;">
                            <?php foreach ( $scan_summary['post_types'] as $type => $info ) : ?>
                                <?php if ( $info['count'] > 0 ) : ?>
                                    <li>
                                        <strong><?php echo esc_html( $info['label'] ); ?>:</strong>
                                        <?php
                                        printf(
                                            /* translators: %d: number of items */
                                            esc_html( _n( '%d item', '%d items', $info['count'], 'internal-link-clusters' ) ),
                                            $info['count']
                                        );
                                        ?>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                        <p style="margin: 10px 0 0; font-weight: 600;">
                            <?php
                            $total = $scan_summary['total_count'];
                            if ( $total > $max_urls ) {
                                printf(
                                    /* translators: %1$d: total URLs, %2$d: max URLs to send */
                                    esc_html__( 'Total: %1$d URLs (will send first %2$d to AI)', 'internal-link-clusters' ),
                                    $total,
                                    $max_urls
                                );
                            } else {
                                printf(
                                    /* translators: %d: total URL count */
                                    esc_html__( 'Total: %d URLs will be analyzed', 'internal-link-clusters' ),
                                    $total
                                );
                            }
                            ?>
                        </p>
                    </div>

                    <form method="post">
                        <?php wp_nonce_field( 'ilc_cluster_gen_generate_ai' ); ?>
                        <input type="hidden" name="ilc_cluster_gen_step" value="generate_ai">

                        <p class="submit" style="margin-top: 15px;">
                            <button type="submit" class="button button-primary button-hero" <?php echo $scan_summary['total_count'] < 2 ? 'disabled' : ''; ?>>
                                <span class="dashicons dashicons-admin-site-alt3" style="margin-top: 4px;"></span>
                                <?php esc_html_e( 'Generate AI Cluster Suggestions', 'internal-link-clusters' ); ?>
                            </button>
                        </p>

                        <?php if ( $scan_summary['total_count'] < 2 ) : ?>
                            <p class="description" style="color: #d63638;">
                                <?php esc_html_e( 'You need at least 2 published posts or pages to generate clusters.', 'internal-link-clusters' ); ?>
                            </p>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Configuration Info -->
            <?php if ( $ai_status['configured'] ) : ?>
                <div class="ilc-config-info" style="background: #f7f7f7; border: 1px solid #e0e0e0; border-radius: 4px; padding: 15px;">
                    <p style="margin: 0;">
                        <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
                        <strong><?php esc_html_e( 'AI Configuration:', 'internal-link-clusters' ); ?></strong>
                        <?php
                        printf(
                            /* translators: %1$s: model name, %2$d: max URLs */
                            esc_html__( 'Using model "%1$s" with max %2$d URLs per request.', 'internal-link-clusters' ),
                            esc_html( $settings['ai_cluster_model'] ? $settings['ai_cluster_model'] : 'default' ),
                            $max_urls
                        );
                        ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=ilc-settings' ) ); ?>"><?php esc_html_e( 'Change settings', 'internal-link-clusters' ); ?></a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Handle AI generation step.
     */
    private static function handle_generate_ai() {
        // Verify nonce
        if ( ! check_admin_referer( 'ilc_cluster_gen_generate_ai' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'internal-link-clusters' ) );
        }

        // Check AI configuration
        if ( ! ILC_AI_Cluster_Generator::is_enabled() ) {
            self::render_input_form( __( 'AI cluster generation is not configured. Please configure it in Settings first.', 'internal-link-clusters' ) );
            return;
        }

        // Get settings for max URLs
        $settings = ILC_Settings::get_settings();
        $max_urls = absint( $settings['ai_cluster_max_urls'] );
        if ( $max_urls < 1 ) {
            $max_urls = 200;
        }

        // Discover URLs with titles
        $url_items = ILC_URL_Discovery::get_all_site_urls_with_titles( null, array(
            'limit' => $max_urls,
        ) );

        if ( empty( $url_items ) ) {
            self::render_input_form( __( 'No published content found on your site.', 'internal-link-clusters' ) );
            return;
        }

        // Call AI to suggest clusters
        $clusters = ILC_AI_Cluster_Generator::suggest_clusters_from_urls( $url_items );

        if ( is_wp_error( $clusters ) ) {
            self::render_input_form( ILC_AI_Cluster_Generator::get_error_message( $clusters ) );
            return;
        }

        if ( empty( $clusters ) ) {
            self::render_input_form( __( 'AI did not return any cluster suggestions. Your site may not have enough related content, or try again.', 'internal-link-clusters' ) );
            return;
        }

        // Build a URL to title map for display
        $url_title_map = array();
        foreach ( $url_items as $item ) {
            $url_title_map[ $item['url'] ] = $item['title'];
        }

        // Show the review form
        self::render_review_form( $clusters, count( $url_items ), $url_title_map );
    }

    /**
     * Render the cluster review form.
     *
     * @param array $clusters      AI-suggested clusters.
     * @param int   $total_urls    Total URLs processed.
     * @param array $url_title_map Map of URL to title for display.
     */
    private static function render_review_form( $clusters, $total_urls, $url_title_map = array() ) {
        // Calculate stats
        $total_clusters   = count( $clusters );
        $total_urls_in    = 0;
        foreach ( $clusters as $cluster ) {
            $total_urls_in += count( $cluster['urls'] );
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Cluster Generation - Review AI Suggestions', 'internal-link-clusters' ); ?></h1>

            <div class="notice notice-success">
                <p>
                    <strong><?php esc_html_e( 'AI Analysis Complete!', 'internal-link-clusters' ); ?></strong>
                    <?php
                    printf(
                        /* translators: %1$d: cluster count, %2$d: URL count, %3$d: total URLs */
                        esc_html__( 'The AI suggested %1$d clusters containing %2$d URLs (from %3$d analyzed).', 'internal-link-clusters' ),
                        $total_clusters,
                        $total_urls_in,
                        $total_urls
                    );
                    ?>
                </p>
            </div>

            <!-- Stats Summary -->
            <div class="ilc-gen-stats" style="margin: 15px 0; padding: 15px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
                <strong><?php esc_html_e( 'Summary:', 'internal-link-clusters' ); ?></strong>
                <span style="margin-left: 15px;">
                    <?php
                    printf(
                        /* translators: %d: cluster count */
                        esc_html__( 'Clusters: %d', 'internal-link-clusters' ),
                        $total_clusters
                    );
                    ?>
                </span>
                <span style="margin-left: 15px;">
                    <?php
                    printf(
                        /* translators: %d: URLs in clusters */
                        esc_html__( 'URLs in clusters: %d', 'internal-link-clusters' ),
                        $total_urls_in
                    );
                    ?>
                </span>
                <?php if ( $total_urls_in < $total_urls ) : ?>
                    <span style="margin-left: 15px; color: #666;">
                        <?php
                        printf(
                            /* translators: %d: unassigned URL count */
                            esc_html__( '(%d URLs not assigned)', 'internal-link-clusters' ),
                            $total_urls - $total_urls_in
                        );
                        ?>
                    </span>
                <?php endif; ?>
            </div>

            <form method="post">
                <?php wp_nonce_field( 'ilc_cluster_gen_create' ); ?>
                <input type="hidden" name="ilc_cluster_gen_step" value="create_clusters">

                <p class="description" style="margin-bottom: 15px;">
                    <?php esc_html_e( 'Review the AI-suggested clusters below. You can edit names, slugs, and uncheck URLs you want to exclude. Only checked URLs will be added to each cluster.', 'internal-link-clusters' ); ?>
                </p>

                <?php
                $cluster_index = 0;
                foreach ( $clusters as $cluster ) :
                    $cluster_index++;
                    $url_count = count( $cluster['urls'] );
                ?>
                    <div class="ilc-cluster-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; margin-bottom: 20px; padding: 0;">
                        <!-- Cluster Header -->
                        <div class="ilc-cluster-header" style="background: #f7f7f7; padding: 15px; border-bottom: 1px solid #ccd0d4; display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
                            <div style="flex: 1; min-width: 200px;">
                                <label style="font-weight: 600; display: block; margin-bottom: 5px;">
                                    <?php esc_html_e( 'Cluster Name', 'internal-link-clusters' ); ?>
                                </label>
                                <input
                                    type="text"
                                    name="clusters[<?php echo esc_attr( $cluster_index ); ?>][name]"
                                    value="<?php echo esc_attr( $cluster['name'] ); ?>"
                                    class="regular-text"
                                    style="width: 100%;"
                                >
                            </div>
                            <div style="flex: 1; min-width: 200px;">
                                <label style="font-weight: 600; display: block; margin-bottom: 5px;">
                                    <?php esc_html_e( 'Slug', 'internal-link-clusters' ); ?>
                                </label>
                                <input
                                    type="text"
                                    name="clusters[<?php echo esc_attr( $cluster_index ); ?>][slug]"
                                    value="<?php echo esc_attr( $cluster['slug'] ); ?>"
                                    class="regular-text"
                                    style="width: 100%;"
                                >
                            </div>
                            <div style="text-align: center; min-width: 80px;">
                                <span class="ilc-url-count" style="display: inline-block; background: #0073aa; color: #fff; padding: 5px 12px; border-radius: 20px; font-weight: 600;">
                                    <?php
                                    printf(
                                        /* translators: %d: URL count */
                                        esc_html__( '%d URLs', 'internal-link-clusters' ),
                                        $url_count
                                    );
                                    ?>
                                </span>
                            </div>
                            <div>
                                <button type="button" class="button ilc-toggle-urls" data-target="cluster-urls-<?php echo esc_attr( $cluster_index ); ?>">
                                    <span class="dashicons dashicons-arrow-down-alt2" style="margin-top: 3px;"></span>
                                    <span class="ilc-toggle-text"><?php esc_html_e( 'Show URLs', 'internal-link-clusters' ); ?></span>
                                </button>
                            </div>
                        </div>

                        <!-- URLs List (collapsed by default) -->
                        <div id="cluster-urls-<?php echo esc_attr( $cluster_index ); ?>" class="ilc-cluster-urls" style="display: none; padding: 15px; max-height: 400px; overflow-y: auto;">
                            <table class="widefat" style="border: none;">
                                <thead>
                                    <tr>
                                        <th style="width: 30px;">
                                            <input type="checkbox" class="ilc-select-all" data-cluster="<?php echo esc_attr( $cluster_index ); ?>" checked>
                                        </th>
                                        <th><?php esc_html_e( 'Page', 'internal-link-clusters' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $cluster['urls'] as $url_index => $url ) : ?>
                                        <?php
                                        $title = isset( $url_title_map[ $url ] ) ? $url_title_map[ $url ] : '';
                                        ?>
                                        <tr>
                                            <td>
                                                <input
                                                    type="checkbox"
                                                    name="clusters[<?php echo esc_attr( $cluster_index ); ?>][urls][<?php echo esc_attr( $url_index ); ?>][include]"
                                                    value="1"
                                                    class="ilc-url-checkbox ilc-cluster-<?php echo esc_attr( $cluster_index ); ?>"
                                                    checked
                                                >
                                                <input
                                                    type="hidden"
                                                    name="clusters[<?php echo esc_attr( $cluster_index ); ?>][urls][<?php echo esc_attr( $url_index ); ?>][url]"
                                                    value="<?php echo esc_url( $url ); ?>"
                                                >
                                            </td>
                                            <td>
                                                <?php if ( ! empty( $title ) ) : ?>
                                                    <strong><?php echo esc_html( $title ); ?></strong><br>
                                                <?php endif; ?>
                                                <code style="font-size: 11px; word-break: break-all; color: #666;"><?php echo esc_html( $url ); ?></code>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>

                <p class="submit" style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="submit" class="button button-primary button-hero">
                        <span class="dashicons dashicons-yes-alt" style="margin-top: 4px;"></span>
                        <?php esc_html_e( 'Create Clusters & Assign URLs', 'internal-link-clusters' ); ?>
                    </button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=ilc-cluster-generation' ) ); ?>" class="button button-secondary button-hero">
                        <span class="dashicons dashicons-update" style="margin-top: 4px;"></span>
                        <?php esc_html_e( 'Start Over / Regenerate', 'internal-link-clusters' ); ?>
                    </a>
                </p>
            </form>
        </div>

        <!-- JavaScript for UI interactions -->
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Toggle URL list visibility
            $('.ilc-toggle-urls').on('click', function() {
                var target = $(this).data('target');
                var $urls = $('#' + target);
                var $icon = $(this).find('.dashicons');
                var $text = $(this).find('.ilc-toggle-text');

                $urls.slideToggle(200);

                if ($urls.is(':visible')) {
                    $icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
                    $text.text('<?php echo esc_js( __( 'Hide URLs', 'internal-link-clusters' ) ); ?>');
                } else {
                    $icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
                    $text.text('<?php echo esc_js( __( 'Show URLs', 'internal-link-clusters' ) ); ?>');
                }
            });

            // Select all checkboxes for a cluster
            $('.ilc-select-all').on('change', function() {
                var cluster = $(this).data('cluster');
                var isChecked = $(this).prop('checked');
                $('.ilc-cluster-' + cluster).prop('checked', isChecked);
            });

            // Update select-all state when individual checkboxes change
            $('.ilc-url-checkbox').on('change', function() {
                var classes = $(this).attr('class').split(' ');
                var clusterClass = classes.find(function(c) { return c.startsWith('ilc-cluster-'); });
                if (clusterClass) {
                    var cluster = clusterClass.replace('ilc-cluster-', '');
                    var $all = $('.ilc-cluster-' + cluster);
                    var $checked = $all.filter(':checked');
                    var $selectAll = $('.ilc-select-all[data-cluster="' + cluster + '"]');
                    $selectAll.prop('checked', $all.length === $checked.length);
                }
            });
        });
        </script>

        <style>
            .ilc-cluster-card {
                box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            }
            .ilc-cluster-card:hover {
                border-color: #0073aa;
            }
            .ilc-cluster-urls table {
                margin: 0;
            }
            .ilc-cluster-urls tbody tr:nth-child(odd) {
                background: #f9f9f9;
            }
        </style>
        <?php
    }

    /**
     * Handle cluster creation step.
     */
    private static function handle_create_clusters() {
        // Verify nonce
        if ( ! check_admin_referer( 'ilc_cluster_gen_create' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'internal-link-clusters' ) );
        }

        $clusters_data = isset( $_POST['clusters'] ) ? wp_unslash( $_POST['clusters'] ) : array();

        if ( empty( $clusters_data ) || ! is_array( $clusters_data ) ) {
            self::render_result_page( 0, 0, __( 'No cluster data received.', 'internal-link-clusters' ) );
            return;
        }

        $clusters_created = 0;
        $urls_assigned    = 0;

        foreach ( $clusters_data as $cluster_data ) {
            // Validate cluster name
            $name = isset( $cluster_data['name'] ) ? sanitize_text_field( $cluster_data['name'] ) : '';
            if ( empty( $name ) ) {
                continue;
            }

            // Get slug (generate from name if empty)
            $slug = isset( $cluster_data['slug'] ) ? sanitize_title( $cluster_data['slug'] ) : '';
            if ( empty( $slug ) ) {
                $slug = sanitize_title( $name );
            }

            // Collect included URLs
            $included_urls = array();
            if ( isset( $cluster_data['urls'] ) && is_array( $cluster_data['urls'] ) ) {
                foreach ( $cluster_data['urls'] as $url_data ) {
                    if ( ! empty( $url_data['include'] ) && ! empty( $url_data['url'] ) ) {
                        $included_urls[] = esc_url_raw( $url_data['url'] );
                    }
                }
            }

            // Skip if no URLs included
            if ( empty( $included_urls ) ) {
                continue;
            }

            // Check if cluster with this slug already exists
            $existing = ILC_Cluster_Model::get_cluster_by_identifier( $slug );
            if ( $existing ) {
                // Append a unique suffix
                $slug = $slug . '-' . time();
            }

            // Create the cluster
            $cluster_id = ILC_Cluster_Model::save_cluster( array(
                'name'      => $name,
                'slug'      => $slug,
                'heading'   => $name,
                'subtitle'  => '',
                'style'     => 'default',
                'is_active' => 1,
            ) );

            if ( ! $cluster_id ) {
                continue;
            }

            $clusters_created++;

            // Add URLs to the cluster
            $sort_order = 0;
            foreach ( $included_urls as $url ) {
                // Try to find post ID for this URL
                $post_id = url_to_postid( $url );

                ILC_Cluster_Model::add_cluster_url( $cluster_id, array(
                    'url'         => $url,
                    'post_id'     => $post_id ? $post_id : null,
                    'anchor_text' => '', // User can edit later
                    'sort_order'  => $sort_order,
                ) );

                $urls_assigned++;
                $sort_order++;
            }
        }

        // Show result
        self::render_result_page( $clusters_created, $urls_assigned );
    }

    /**
     * Render the result page after cluster creation.
     *
     * @param int    $clusters_created Number of clusters created.
     * @param int    $urls_assigned    Number of URLs assigned.
     * @param string $error_message    Optional error message.
     */
    private static function render_result_page( $clusters_created, $urls_assigned, $error_message = '' ) {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Cluster Generation - Complete', 'internal-link-clusters' ); ?></h1>

            <?php if ( ! empty( $error_message ) ) : ?>
                <div class="notice notice-error">
                    <p><?php echo esc_html( $error_message ); ?></p>
                </div>
            <?php elseif ( $clusters_created > 0 ) : ?>
                <div class="notice notice-success">
                    <p>
                        <strong><?php esc_html_e( 'Success!', 'internal-link-clusters' ); ?></strong>
                        <?php
                        printf(
                            /* translators: %1$d: cluster count, %2$d: URL count */
                            esc_html__( 'Created %1$d clusters with %2$d URLs assigned.', 'internal-link-clusters' ),
                            $clusters_created,
                            $urls_assigned
                        );
                        ?>
                    </p>
                </div>
            <?php else : ?>
                <div class="notice notice-warning">
                    <p>
                        <?php esc_html_e( 'No clusters were created. This may happen if all cluster names were empty or no URLs were selected.', 'internal-link-clusters' ); ?>
                    </p>
                </div>
            <?php endif; ?>

            <div style="margin-top: 20px;">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ilc-clusters' ) ); ?>" class="button button-primary button-hero">
                    <span class="dashicons dashicons-networking" style="margin-top: 4px;"></span>
                    <?php esc_html_e( 'View Clusters', 'internal-link-clusters' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ilc-cluster-generation' ) ); ?>" class="button button-secondary button-hero">
                    <span class="dashicons dashicons-plus-alt" style="margin-top: 4px;"></span>
                    <?php esc_html_e( 'Generate More Clusters', 'internal-link-clusters' ); ?>
                </a>
            </div>
        </div>
        <?php
    }
}
