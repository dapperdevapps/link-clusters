<?php
/**
 * ILC_Admin_Cluster_Generation_Page - Admin UI for automatic cluster generation.
 *
 * Provides a dedicated screen where users can:
 * - Scan site URLs automatically (primary flow)
 * - Paste sitemaps or URL lists (secondary flow)
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
        if ( $step === 'generate_from_site' ) {
            self::handle_generate_from_site();
            return;
        }

        if ( $step === 'generate_from_input' ) {
            self::handle_generate_from_input();
            return;
        }

        if ( $step === 'create' ) {
            self::handle_create_step();
            return;
        }

        // Default: Show initial input form
        self::render_input_form();
    }

    /**
     * Render the initial input form with both flows.
     *
     * @param string $error_message Optional error message to display.
     * @param string $previous_input Optional previous pasted input to preserve.
     */
    private static function render_input_form( $error_message = '', $previous_input = '' ) {
        // Get scan summary for display
        $scan_summary = ILC_URL_Discovery::get_scan_summary();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Cluster Generation', 'internal-link-clusters' ); ?></h1>

            <?php if ( ! empty( $error_message ) ) : ?>
                <div class="notice notice-error">
                    <p><?php echo esc_html( $error_message ); ?></p>
                </div>
            <?php endif; ?>

            <!-- Flow A: Scan Site URLs (Primary) -->
            <div class="ilc-generation-section" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin-bottom: 20px;">
                <h2 style="margin-top: 0;">
                    <span class="dashicons dashicons-search" style="color: #0073aa;"></span>
                    <?php esc_html_e( 'Generate from Site URLs', 'internal-link-clusters' ); ?>
                    <span class="ilc-recommended-badge" style="background: #0073aa; color: #fff; font-size: 11px; padding: 2px 8px; border-radius: 3px; margin-left: 10px; vertical-align: middle;"><?php esc_html_e( 'Recommended', 'internal-link-clusters' ); ?></span>
                </h2>
                <p class="description">
                    <?php esc_html_e( 'Automatically scan your site\'s published content and generate cluster suggestions based on URL structure.', 'internal-link-clusters' ); ?>
                </p>

                <!-- Scan Summary -->
                <div class="ilc-scan-summary" style="background: #f7f7f7; border: 1px solid #e0e0e0; border-radius: 4px; padding: 15px; margin: 15px 0;">
                    <strong><?php esc_html_e( 'Content to scan:', 'internal-link-clusters' ); ?></strong>
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
                        printf(
                            /* translators: %d: total URL count */
                            esc_html__( 'Total: %d URLs will be analyzed', 'internal-link-clusters' ),
                            $scan_summary['total_count']
                        );
                        ?>
                    </p>
                </div>

                <form method="post">
                    <?php wp_nonce_field( 'ilc_cluster_gen_generate_site' ); ?>
                    <input type="hidden" name="ilc_cluster_gen_step" value="generate_from_site">

                    <table class="form-table" style="margin-top: 0;">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="ilc_site_post_types"><?php esc_html_e( 'Post types to scan', 'internal-link-clusters' ); ?></label>
                                </th>
                                <td>
                                    <fieldset>
                                        <?php
                                        $all_types = ILC_URL_Discovery::get_public_post_types();
                                        foreach ( $all_types as $type ) :
                                            $type_obj = get_post_type_object( $type );
                                            $label = $type_obj ? $type_obj->labels->name : ucfirst( $type );
                                            $count = isset( $scan_summary['post_types'][ $type ]['count'] ) ? $scan_summary['post_types'][ $type ]['count'] : 0;
                                        ?>
                                            <label style="display: inline-block; margin-right: 15px; margin-bottom: 5px;">
                                                <input type="checkbox" name="ilc_site_post_types[]" value="<?php echo esc_attr( $type ); ?>" <?php checked( in_array( $type, array( 'post', 'page' ), true ) || $count > 0 ); ?>>
                                                <?php echo esc_html( $label ); ?> (<?php echo esc_html( $count ); ?>)
                                            </label>
                                        <?php endforeach; ?>
                                    </fieldset>
                                    <p class="description"><?php esc_html_e( 'Select which post types to include in the scan.', 'internal-link-clusters' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="ilc_site_min_urls"><?php esc_html_e( 'Minimum URLs per cluster', 'internal-link-clusters' ); ?></label>
                                </th>
                                <td>
                                    <input type="number" name="ilc_site_min_urls" id="ilc_site_min_urls" value="2" min="1" max="20" class="small-text">
                                    <p class="description"><?php esc_html_e( 'Clusters with fewer URLs than this will be filtered out.', 'internal-link-clusters' ); ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p class="submit" style="margin-top: 10px;">
                        <button type="submit" class="button button-primary button-hero">
                            <span class="dashicons dashicons-admin-site-alt3" style="margin-top: 4px;"></span>
                            <?php esc_html_e( 'Scan Site & Suggest Clusters', 'internal-link-clusters' ); ?>
                        </button>
                    </p>
                </form>
            </div>

            <!-- Flow B: Paste Input (Secondary) -->
            <div class="ilc-generation-section" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px;">
                <h2 style="margin-top: 0;">
                    <span class="dashicons dashicons-clipboard" style="color: #666;"></span>
                    <?php esc_html_e( 'Generate from Pasted Input', 'internal-link-clusters' ); ?>
                    <span style="font-size: 12px; color: #666; font-weight: normal; margin-left: 10px;"><?php esc_html_e( '(Alternative method)', 'internal-link-clusters' ); ?></span>
                </h2>
                <p class="description">
                    <?php esc_html_e( 'Paste a sitemap XML, URL list, or JSON data to generate cluster suggestions from external URLs.', 'internal-link-clusters' ); ?>
                </p>

                <details style="margin-top: 15px;">
                    <summary style="cursor: pointer; color: #0073aa; font-weight: 500;">
                        <?php esc_html_e( 'Click to expand pasted input form', 'internal-link-clusters' ); ?>
                    </summary>

                    <form method="post" style="margin-top: 15px;">
                        <?php wp_nonce_field( 'ilc_cluster_gen_generate_input' ); ?>
                        <input type="hidden" name="ilc_cluster_gen_step" value="generate_from_input">

                        <table class="form-table" style="margin-top: 0;">
                            <tbody>
                                <tr>
                                    <th scope="row">
                                        <label for="ilc_cluster_gen_input"><?php esc_html_e( 'URL Input', 'internal-link-clusters' ); ?></label>
                                    </th>
                                    <td>
                                        <textarea
                                            name="ilc_cluster_gen_input"
                                            id="ilc_cluster_gen_input"
                                            rows="12"
                                            class="large-text code"
                                            placeholder="<?php esc_attr_e( "Paste your sitemap XML, URL list, or JSON here...\n\nExamples:\n- Sitemap XML with <loc> tags\n- One URL per line\n- JSON array: [\"https://example.com/page1/\", ...]", 'internal-link-clusters' ); ?>"
                                        ><?php echo esc_textarea( $previous_input ); ?></textarea>
                                        <p class="description">
                                            <?php esc_html_e( 'Supported formats: XML Sitemap, URL list (one per line), JSON array.', 'internal-link-clusters' ); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="ilc_input_min_urls"><?php esc_html_e( 'Minimum URLs per cluster', 'internal-link-clusters' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" name="ilc_input_min_urls" id="ilc_input_min_urls" value="2" min="1" max="20" class="small-text">
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <p class="submit" style="margin-top: 10px;">
                            <button type="submit" class="button button-secondary">
                                <span class="dashicons dashicons-search" style="margin-top: 4px;"></span>
                                <?php esc_html_e( 'Generate from Pasted Input', 'internal-link-clusters' ); ?>
                            </button>
                        </p>
                    </form>
                </details>
            </div>
        </div>
        <?php
    }

    /**
     * Handle Flow A: Generate clusters from site URLs.
     */
    private static function handle_generate_from_site() {
        // Verify nonce
        if ( ! check_admin_referer( 'ilc_cluster_gen_generate_site' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'internal-link-clusters' ) );
        }

        // Get selected post types
        $post_types = isset( $_POST['ilc_site_post_types'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['ilc_site_post_types'] ) ) : array();
        $min_urls   = isset( $_POST['ilc_site_min_urls'] ) ? absint( $_POST['ilc_site_min_urls'] ) : 2;

        if ( $min_urls < 1 ) {
            $min_urls = 2;
        }

        if ( empty( $post_types ) ) {
            self::render_input_form( __( 'Please select at least one post type to scan.', 'internal-link-clusters' ) );
            return;
        }

        // Discover URLs from the site
        $urls = ILC_URL_Discovery::get_all_site_urls( $post_types );

        if ( empty( $urls ) ) {
            self::render_input_form( __( 'No published content found for the selected post types.', 'internal-link-clusters' ) );
            return;
        }

        // Generate cluster suggestions
        $clusters = ILC_Cluster_Generation::generate_clusters_from_urls( $urls, $min_urls );

        if ( empty( $clusters ) ) {
            self::render_input_form(
                sprintf(
                    /* translators: %1$d: URL count, %2$d: minimum URLs per cluster */
                    __( 'Found %1$d URLs, but no clusters could be generated with at least %2$d URLs each. Your site may not have enough pages with similar URL structures, or try lowering the minimum.', 'internal-link-clusters' ),
                    count( $urls ),
                    $min_urls
                )
            );
            return;
        }

        // Show the suggestions review form
        self::render_suggestions_form( $clusters, count( $urls ), 'site' );
    }

    /**
     * Handle Flow B: Generate clusters from pasted input.
     */
    private static function handle_generate_from_input() {
        // Verify nonce
        if ( ! check_admin_referer( 'ilc_cluster_gen_generate_input' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'internal-link-clusters' ) );
        }

        $raw_input = isset( $_POST['ilc_cluster_gen_input'] ) ? wp_unslash( $_POST['ilc_cluster_gen_input'] ) : '';
        $min_urls  = isset( $_POST['ilc_input_min_urls'] ) ? absint( $_POST['ilc_input_min_urls'] ) : 2;

        if ( $min_urls < 1 ) {
            $min_urls = 2;
        }

        // Parse input to URLs
        $urls = ILC_Cluster_Generation::parse_input_to_urls( $raw_input );

        if ( empty( $urls ) ) {
            self::render_input_form(
                __( 'No valid URLs found in your input. Please check the format and try again.', 'internal-link-clusters' ),
                $raw_input
            );
            return;
        }

        // Generate cluster suggestions
        $clusters = ILC_Cluster_Generation::generate_clusters_from_urls( $urls, $min_urls );

        if ( empty( $clusters ) ) {
            self::render_input_form(
                sprintf(
                    /* translators: %1$d: URL count, %2$d: minimum URLs per cluster */
                    __( 'Found %1$d URLs, but no clusters could be generated with at least %2$d URLs each. Try lowering the minimum or adding more URLs with similar path structures.', 'internal-link-clusters' ),
                    count( $urls ),
                    $min_urls
                ),
                $raw_input
            );
            return;
        }

        // Show the suggestions review form
        self::render_suggestions_form( $clusters, count( $urls ), 'input' );
    }

    /**
     * Render the suggestions review form.
     *
     * @param array  $clusters   Generated clusters.
     * @param int    $total_urls Total URLs processed.
     * @param string $source     Source type ('site' or 'input').
     */
    private static function render_suggestions_form( $clusters, $total_urls, $source = 'site' ) {
        $stats = ILC_Cluster_Generation::get_cluster_stats( $clusters );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Cluster Generation - Review Suggestions', 'internal-link-clusters' ); ?></h1>

            <div class="notice notice-success">
                <p>
                    <strong><?php esc_html_e( 'Analysis complete!', 'internal-link-clusters' ); ?></strong>
                    <?php
                    if ( $source === 'site' ) {
                        printf(
                            /* translators: %1$d: cluster count, %2$d: URL count */
                            esc_html__( 'Scanned your site and found %1$d potential clusters from %2$d URLs.', 'internal-link-clusters' ),
                            $stats['total_clusters'],
                            $total_urls
                        );
                    } else {
                        printf(
                            /* translators: %1$d: cluster count, %2$d: URL count */
                            esc_html__( 'Found %1$d potential clusters from %2$d pasted URLs.', 'internal-link-clusters' ),
                            $stats['total_clusters'],
                            $total_urls
                        );
                    }
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
                        $stats['total_clusters']
                    );
                    ?>
                </span>
                <span style="margin-left: 15px;">
                    <?php
                    printf(
                        /* translators: %d: total URLs */
                        esc_html__( 'URLs in clusters: %d', 'internal-link-clusters' ),
                        $stats['total_urls']
                    );
                    ?>
                </span>
                <span style="margin-left: 15px;">
                    <?php
                    printf(
                        /* translators: %s: average URLs per cluster */
                        esc_html__( 'Avg per cluster: %s', 'internal-link-clusters' ),
                        $stats['avg_urls']
                    );
                    ?>
                </span>
                <?php if ( $stats['total_urls'] < $total_urls ) : ?>
                    <span style="margin-left: 15px; color: #666;">
                        <?php
                        printf(
                            /* translators: %d: unassigned URL count */
                            esc_html__( '(%d URLs not assigned to clusters)', 'internal-link-clusters' ),
                            $total_urls - $stats['total_urls']
                        );
                        ?>
                    </span>
                <?php endif; ?>
            </div>

            <form method="post">
                <?php wp_nonce_field( 'ilc_cluster_gen_create' ); ?>
                <input type="hidden" name="ilc_cluster_gen_step" value="create">

                <p class="description" style="margin-bottom: 15px;">
                    <?php esc_html_e( 'Review the suggested clusters below. You can edit names, slugs, and uncheck URLs you want to exclude. Only checked URLs will be added to each cluster.', 'internal-link-clusters' ); ?>
                </p>

                <?php
                $cluster_index = 0;
                foreach ( $clusters as $key => $cluster ) :
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
                                    name="clusters[<?php echo esc_attr( $key ); ?>][name]"
                                    value="<?php echo esc_attr( $cluster['label'] ); ?>"
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
                                    name="clusters[<?php echo esc_attr( $key ); ?>][slug]"
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
                        <div id="cluster-urls-<?php echo esc_attr( $cluster_index ); ?>" class="ilc-cluster-urls" style="display: none; padding: 15px; max-height: 300px; overflow-y: auto;">
                            <table class="widefat" style="border: none;">
                                <thead>
                                    <tr>
                                        <th style="width: 30px;">
                                            <input type="checkbox" class="ilc-select-all" data-cluster="<?php echo esc_attr( $key ); ?>" checked>
                                        </th>
                                        <th><?php esc_html_e( 'URL', 'internal-link-clusters' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $cluster['urls'] as $url_index => $url ) : ?>
                                        <tr>
                                            <td>
                                                <input
                                                    type="checkbox"
                                                    name="clusters[<?php echo esc_attr( $key ); ?>][urls][<?php echo esc_attr( $url_index ); ?>][include]"
                                                    value="1"
                                                    class="ilc-url-checkbox ilc-cluster-<?php echo esc_attr( $key ); ?>"
                                                    checked
                                                >
                                                <input
                                                    type="hidden"
                                                    name="clusters[<?php echo esc_attr( $key ); ?>][urls][<?php echo esc_attr( $url_index ); ?>][url]"
                                                    value="<?php echo esc_url( $url ); ?>"
                                                >
                                            </td>
                                            <td>
                                                <code style="font-size: 12px; word-break: break-all;"><?php echo esc_html( $url ); ?></code>
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
                        <span class="dashicons dashicons-arrow-left-alt" style="margin-top: 4px;"></span>
                        <?php esc_html_e( 'Back / Start Over', 'internal-link-clusters' ); ?>
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
     * Handle the create step - create clusters and assign URLs.
     */
    private static function handle_create_step() {
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

        foreach ( $clusters_data as $key => $cluster_data ) {
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
