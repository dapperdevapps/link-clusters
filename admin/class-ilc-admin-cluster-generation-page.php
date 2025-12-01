<?php
/**
 * ILC_Admin_Cluster_Generation_Page - Admin UI for automatic cluster generation.
 *
 * Provides a dedicated screen where users can paste sitemaps or URL lists
 * and get smart cluster suggestions based on URL structure analysis.
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
        if ( $step === 'generate' ) {
            self::handle_generate_step();
            return;
        }

        if ( $step === 'create' ) {
            self::handle_create_step();
            return;
        }

        // Default: Show initial input form (Mode A)
        self::render_input_form();
    }

    /**
     * Render the initial input form (Mode A).
     */
    private static function render_input_form() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Cluster Generation', 'internal-link-clusters' ); ?></h1>

            <div class="notice notice-info">
                <p>
                    <strong><?php esc_html_e( 'How it works:', 'internal-link-clusters' ); ?></strong>
                    <?php esc_html_e( 'Paste your sitemap XML, a list of URLs, or JSON data below. The plugin will analyze URL structures and suggest logical clusters automatically.', 'internal-link-clusters' ); ?>
                </p>
            </div>

            <form method="post">
                <?php wp_nonce_field( 'ilc_cluster_generation_generate' ); ?>
                <input type="hidden" name="ilc_cluster_gen_step" value="generate">

                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="ilc_cluster_gen_input"><?php esc_html_e( 'URL Input', 'internal-link-clusters' ); ?></label>
                            </th>
                            <td>
                                <textarea
                                    name="ilc_cluster_gen_input"
                                    id="ilc_cluster_gen_input"
                                    rows="15"
                                    class="large-text code"
                                    placeholder="<?php esc_attr_e( "Paste your sitemap XML, URL list, or JSON here...\n\nExamples:\n- Sitemap XML with <loc> tags\n- One URL per line\n- JSON array: [\"https://example.com/page1/\", ...]", 'internal-link-clusters' ); ?>"
                                ></textarea>
                                <p class="description">
                                    <?php esc_html_e( 'Supported formats:', 'internal-link-clusters' ); ?>
                                    <br>
                                    <strong><?php esc_html_e( 'XML Sitemap:', 'internal-link-clusters' ); ?></strong>
                                    <?php esc_html_e( 'Standard sitemap format with &lt;loc&gt; tags.', 'internal-link-clusters' ); ?>
                                    <br>
                                    <strong><?php esc_html_e( 'URL List:', 'internal-link-clusters' ); ?></strong>
                                    <?php esc_html_e( 'One URL per line.', 'internal-link-clusters' ); ?>
                                    <br>
                                    <strong><?php esc_html_e( 'JSON:', 'internal-link-clusters' ); ?></strong>
                                    <?php esc_html_e( 'Array of URLs or objects with "url" field.', 'internal-link-clusters' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="ilc_min_urls"><?php esc_html_e( 'Minimum URLs per cluster', 'internal-link-clusters' ); ?></label>
                            </th>
                            <td>
                                <input type="number" name="ilc_min_urls" id="ilc_min_urls" value="2" min="1" max="20" class="small-text">
                                <p class="description">
                                    <?php esc_html_e( 'Clusters with fewer URLs than this will be filtered out.', 'internal-link-clusters' ); ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary button-hero">
                        <span class="dashicons dashicons-search" style="margin-top: 4px;"></span>
                        <?php esc_html_e( 'Generate Cluster Suggestions', 'internal-link-clusters' ); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Handle the generate step - parse URLs and show suggestions (Mode B).
     */
    private static function handle_generate_step() {
        // Verify nonce
        if ( ! check_admin_referer( 'ilc_cluster_generation_generate' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'internal-link-clusters' ) );
        }

        $raw_input = isset( $_POST['ilc_cluster_gen_input'] ) ? wp_unslash( $_POST['ilc_cluster_gen_input'] ) : '';
        $min_urls  = isset( $_POST['ilc_min_urls'] ) ? absint( $_POST['ilc_min_urls'] ) : 2;

        if ( $min_urls < 1 ) {
            $min_urls = 2;
        }

        // Parse input to URLs
        $urls = ILC_Cluster_Generation::parse_input_to_urls( $raw_input );

        if ( empty( $urls ) ) {
            self::render_input_form_with_error(
                __( 'No valid URLs found in your input. Please check the format and try again.', 'internal-link-clusters' ),
                $raw_input
            );
            return;
        }

        // Generate cluster suggestions
        $clusters = ILC_Cluster_Generation::generate_clusters_from_urls( $urls, $min_urls );

        if ( empty( $clusters ) ) {
            self::render_input_form_with_error(
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

        // Show the suggestions review form (Mode B)
        self::render_suggestions_form( $clusters, count( $urls ) );
    }

    /**
     * Render the input form with an error message.
     *
     * @param string $error_message Error message to display.
     * @param string $previous_input Previous input to preserve.
     */
    private static function render_input_form_with_error( $error_message, $previous_input = '' ) {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Cluster Generation', 'internal-link-clusters' ); ?></h1>

            <div class="notice notice-error">
                <p><?php echo esc_html( $error_message ); ?></p>
            </div>

            <form method="post">
                <?php wp_nonce_field( 'ilc_cluster_generation_generate' ); ?>
                <input type="hidden" name="ilc_cluster_gen_step" value="generate">

                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="ilc_cluster_gen_input"><?php esc_html_e( 'URL Input', 'internal-link-clusters' ); ?></label>
                            </th>
                            <td>
                                <textarea
                                    name="ilc_cluster_gen_input"
                                    id="ilc_cluster_gen_input"
                                    rows="15"
                                    class="large-text code"
                                ><?php echo esc_textarea( $previous_input ); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="ilc_min_urls"><?php esc_html_e( 'Minimum URLs per cluster', 'internal-link-clusters' ); ?></label>
                            </th>
                            <td>
                                <input type="number" name="ilc_min_urls" id="ilc_min_urls" value="2" min="1" max="20" class="small-text">
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e( 'Generate Cluster Suggestions', 'internal-link-clusters' ); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render the suggestions review form (Mode B).
     *
     * @param array $clusters Generated clusters.
     * @param int   $total_urls Total URLs processed.
     */
    private static function render_suggestions_form( $clusters, $total_urls ) {
        $stats = ILC_Cluster_Generation::get_cluster_stats( $clusters );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Cluster Generation - Review Suggestions', 'internal-link-clusters' ); ?></h1>

            <div class="notice notice-success">
                <p>
                    <strong><?php esc_html_e( 'Analysis complete!', 'internal-link-clusters' ); ?></strong>
                    <?php
                    printf(
                        /* translators: %1$d: cluster count, %2$d: URL count */
                        esc_html__( 'Found %1$d potential clusters from %2$d URLs.', 'internal-link-clusters' ),
                        $stats['total_clusters'],
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
                        $stats['total_clusters']
                    );
                    ?>
                </span>
                <span style="margin-left: 15px;">
                    <?php
                    printf(
                        /* translators: %d: total URLs */
                        esc_html__( 'Total URLs: %d', 'internal-link-clusters' ),
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
            </div>

            <form method="post">
                <?php wp_nonce_field( 'ilc_cluster_generation_create' ); ?>
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
                                    <?php esc_html_e( 'Show URLs', 'internal-link-clusters' ); ?>
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

                $urls.slideToggle(200);

                if ($urls.is(':visible')) {
                    $icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
                    $(this).find('span:not(.dashicons)').text('<?php echo esc_js( __( 'Hide URLs', 'internal-link-clusters' ) ); ?>');
                } else {
                    $icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
                    $(this).find('span:not(.dashicons)').text('<?php echo esc_js( __( 'Show URLs', 'internal-link-clusters' ) ); ?>');
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
        if ( ! check_admin_referer( 'ilc_cluster_generation_create' ) ) {
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

