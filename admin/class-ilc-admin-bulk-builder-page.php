<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ILC_Admin_Bulk_Builder_Page {

    /**
     * Render the Bulk Builder admin page.
     */
    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'internal-link-clusters' ) );
        }

        $step = isset( $_POST['ilc_bulk_step'] ) ? sanitize_key( $_POST['ilc_bulk_step'] ) : '';

        // Handle Create step
        if ( $step === 'create' ) {
            self::handle_create_step();
            return;
        }

        // Handle Parse step
        if ( $step === 'parse' ) {
            self::handle_parse_step();
            return;
        }

        // Default: show initial form
        self::render_initial_form();
    }

    /**
     * Render the initial sitemap/URL input form.
     */
    protected static function render_initial_form( $error_message = '' ) {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Bulk Cluster Builder', 'internal-link-clusters' ); ?></h1>
            <p><?php esc_html_e( 'Paste a sitemap XML or a plain list of URLs (one per line) to auto-generate cluster suggestions based on URL path structure.', 'internal-link-clusters' ); ?></p>

            <?php if ( $error_message ) : ?>
                <div class="notice notice-error">
                    <p><?php echo esc_html( $error_message ); ?></p>
                </div>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field( 'ilc_bulk_builder_parse' ); ?>
                <input type="hidden" name="ilc_bulk_step" value="parse">

                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="ilc-sitemap-input"><?php esc_html_e( 'Sitemap XML or URL List', 'internal-link-clusters' ); ?></label>
                            </th>
                            <td>
                                <textarea name="ilc_sitemap_input" id="ilc-sitemap-input" rows="15" class="large-text code" placeholder="<?php esc_attr_e( "Paste sitemap XML here:\n<?xml version=\"1.0\"?>\n<urlset>\n  <url><loc>https://example.com/page/</loc></url>\n</urlset>\n\nOr paste plain URLs (one per line):\nhttps://example.com/services/web-design/\nhttps://example.com/services/seo/\nhttps://example.com/blog/tips/", 'internal-link-clusters' ); ?>"></textarea>
                                <p class="description">
                                    <?php esc_html_e( 'Accepts sitemap XML format (with <urlset> and <loc> tags) or plain URLs separated by line breaks.', 'internal-link-clusters' ); ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Parse Sitemap', 'internal-link-clusters' ); ?></button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Handle the parse step - extract URLs and show review form.
     */
    protected static function handle_parse_step() {
        // Verify nonce
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'ilc_bulk_builder_parse' ) ) {
            self::render_initial_form( __( 'Security check failed. Please try again.', 'internal-link-clusters' ) );
            return;
        }

        $input = isset( $_POST['ilc_sitemap_input'] ) ? wp_unslash( $_POST['ilc_sitemap_input'] ) : '';

        if ( empty( trim( $input ) ) ) {
            self::render_initial_form( __( 'Please paste a sitemap or URL list.', 'internal-link-clusters' ) );
            return;
        }

        // Extract URLs
        $urls = self::extract_urls( $input );

        if ( empty( $urls ) ) {
            self::render_initial_form( __( 'No valid URLs found in the input. Please check your sitemap or URL list.', 'internal-link-clusters' ) );
            return;
        }

        // Cluster URLs by path structure
        $clusters = self::cluster_urls_by_path( $urls );

        if ( empty( $clusters ) ) {
            self::render_initial_form( __( 'Could not generate any clusters from the provided URLs.', 'internal-link-clusters' ) );
            return;
        }

        // Render review form
        self::render_review_form( $clusters );
    }

    /**
     * Extract URLs from input (XML or plain text).
     *
     * @param string $input Raw input from textarea.
     * @return array Array of URL strings.
     */
    protected static function extract_urls( $input ) {
        $urls = array();

        // Detect if input is XML
        if ( strpos( $input, '<urlset' ) !== false || strpos( $input, '<loc>' ) !== false ) {
            // Parse as XML
            libxml_use_internal_errors( true );
            $xml = simplexml_load_string( $input );

            if ( $xml !== false ) {
                // Handle namespace if present
                $namespaces = $xml->getNamespaces( true );
                if ( ! empty( $namespaces ) ) {
                    $ns = reset( $namespaces );
                    $xml->registerXPathNamespace( 'sm', $ns );
                    $locs = $xml->xpath( '//sm:loc' );
                } else {
                    $locs = $xml->xpath( '//loc' );
                }

                if ( $locs ) {
                    foreach ( $locs as $loc ) {
                        $url = trim( (string) $loc );
                        if ( ! empty( $url ) ) {
                            $urls[] = $url;
                        }
                    }
                }
            }

            libxml_clear_errors();
        }

        // If no URLs extracted via XML, try plain text
        if ( empty( $urls ) ) {
            $lines = preg_split( '/\r\n|\r|\n/', $input );
            foreach ( $lines as $line ) {
                $line = trim( $line );
                if ( ! empty( $line ) ) {
                    $urls[] = $line;
                }
            }
        }

        // Remove duplicates
        $urls = array_unique( $urls );

        return array_values( $urls );
    }

    /**
     * Cluster URLs by their first path segment.
     *
     * @param array $urls Array of URL strings.
     * @return array Associative array of clusters.
     */
    protected static function cluster_urls_by_path( $urls ) {
        $clusters = array();

        foreach ( $urls as $url ) {
            $parsed = wp_parse_url( $url );
            $path   = isset( $parsed['path'] ) ? $parsed['path'] : '/';
            $path   = trim( $path, '/' );

            if ( empty( $path ) ) {
                // Group under 'root' cluster
                $key = 'root';
            } else {
                $segments = explode( '/', $path );
                $key      = $segments[0];
            }

            // Sanitize key for use as array index
            $safe_key = sanitize_title( $key );
            if ( empty( $safe_key ) ) {
                $safe_key = 'uncategorized';
            }

            if ( ! isset( $clusters[ $safe_key ] ) ) {
                // Humanize label: replace hyphens/underscores with spaces, title case
                $label = str_replace( array( '-', '_' ), ' ', $key );
                $label = ucwords( $label );

                $clusters[ $safe_key ] = array(
                    'label' => $label,
                    'slug'  => $safe_key,
                    'urls'  => array(),
                );
            }

            $clusters[ $safe_key ]['urls'][] = $url;
        }

        // Sort clusters alphabetically by key
        ksort( $clusters );

        return $clusters;
    }

    /**
     * Render the review form with suggested clusters.
     *
     * @param array $clusters Clustered URL data.
     */
    protected static function render_review_form( $clusters ) {
        $total_urls = 0;
        foreach ( $clusters as $cluster ) {
            $total_urls += count( $cluster['urls'] );
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Bulk Cluster Builder - Review', 'internal-link-clusters' ); ?></h1>
            <p>
                <?php
                printf(
                    /* translators: %1$d: number of clusters, %2$d: number of URLs */
                    esc_html__( 'Found %1$d suggested clusters with %2$d total URLs. Review and adjust the cluster names, slugs, and select which URLs to include.', 'internal-link-clusters' ),
                    count( $clusters ),
                    $total_urls
                );
                ?>
            </p>

            <form method="post">
                <?php wp_nonce_field( 'ilc_bulk_builder_create' ); ?>
                <input type="hidden" name="ilc_bulk_step" value="create">

                <div class="ilc-bulk-clusters" style="margin-top: 20px;">
                    <?php $cluster_index = 0; ?>
                    <?php foreach ( $clusters as $key => $cluster ) : ?>
                        <div class="ilc-bulk-cluster-panel" style="background: #fff; border: 1px solid #c3c4c7; margin-bottom: 15px; padding: 0;">
                            <div class="ilc-bulk-cluster-header" style="background: #f6f7f7; border-bottom: 1px solid #c3c4c7; padding: 12px 15px; cursor: pointer;" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'none' ? 'block' : 'none'; this.querySelector('.dashicons').classList.toggle('dashicons-arrow-down'); this.querySelector('.dashicons').classList.toggle('dashicons-arrow-up');">
                                <span class="dashicons dashicons-arrow-down" style="float: right; color: #646970;"></span>
                                <strong><?php echo esc_html( $cluster['label'] ); ?></strong>
                                <span style="color: #646970; margin-left: 10px;">
                                    (<?php echo esc_html( count( $cluster['urls'] ) ); ?> <?php esc_html_e( 'URLs', 'internal-link-clusters' ); ?>)
                                </span>
                            </div>
                            <div class="ilc-bulk-cluster-body" style="padding: 15px; display: block;">
                                <table class="form-table" style="margin: 0;">
                                    <tbody>
                                        <tr>
                                            <th scope="row" style="width: 120px;">
                                                <label><?php esc_html_e( 'Cluster Name', 'internal-link-clusters' ); ?></label>
                                            </th>
                                            <td>
                                                <input type="text" name="clusters[<?php echo esc_attr( $key ); ?>][name]" value="<?php echo esc_attr( $cluster['label'] ); ?>" class="regular-text">
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label><?php esc_html_e( 'Cluster Slug', 'internal-link-clusters' ); ?></label>
                                            </th>
                                            <td>
                                                <input type="text" name="clusters[<?php echo esc_attr( $key ); ?>][slug]" value="<?php echo esc_attr( $cluster['slug'] ); ?>" class="regular-text">
                                                <p class="description"><?php esc_html_e( 'Used in shortcode: [rc_cluster name="slug"]', 'internal-link-clusters' ); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label><?php esc_html_e( 'URLs', 'internal-link-clusters' ); ?></label>
                                            </th>
                                            <td>
                                                <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fafafa;">
                                                    <label style="display: block; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #ddd;">
                                                        <input type="checkbox" checked onchange="var checkboxes = this.closest('td').querySelectorAll('input[type=checkbox]:not(:first-of-type)'); checkboxes.forEach(function(cb) { cb.checked = this.checked; }.bind(this));">
                                                        <strong><?php esc_html_e( 'Select/Deselect All', 'internal-link-clusters' ); ?></strong>
                                                    </label>
                                                    <?php foreach ( $cluster['urls'] as $url_index => $url ) : ?>
                                                        <label style="display: block; margin-bottom: 5px; word-break: break-all;">
                                                            <input type="checkbox" name="clusters[<?php echo esc_attr( $key ); ?>][urls][<?php echo (int) $url_index; ?>][include]" value="1" checked>
                                                            <input type="hidden" name="clusters[<?php echo esc_attr( $key ); ?>][urls][<?php echo (int) $url_index; ?>][url]" value="<?php echo esc_attr( $url ); ?>">
                                                            <span style="color: #2271b1;"><?php echo esc_html( $url ); ?></span>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php $cluster_index++; ?>
                    <?php endforeach; ?>
                </div>

                <p class="submit">
                    <button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Create Clusters & Assign URLs', 'internal-link-clusters' ); ?></button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=ilc-bulk-builder' ) ); ?>" class="button button-large"><?php esc_html_e( 'Start Over', 'internal-link-clusters' ); ?></a>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Handle the create step - save clusters and URLs.
     */
    protected static function handle_create_step() {
        // Verify nonce
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'ilc_bulk_builder_create' ) ) {
            self::render_initial_form( __( 'Security check failed. Please try again.', 'internal-link-clusters' ) );
            return;
        }

        $clusters_data = isset( $_POST['clusters'] ) ? wp_unslash( $_POST['clusters'] ) : array();

        if ( empty( $clusters_data ) || ! is_array( $clusters_data ) ) {
            self::render_initial_form( __( 'No cluster data received. Please try again.', 'internal-link-clusters' ) );
            return;
        }

        $created_clusters = 0;
        $created_urls     = 0;

        foreach ( $clusters_data as $key => $cluster ) {
            $name = isset( $cluster['name'] ) ? sanitize_text_field( $cluster['name'] ) : '';
            $slug = isset( $cluster['slug'] ) ? sanitize_title( $cluster['slug'] ) : '';
            $urls = isset( $cluster['urls'] ) && is_array( $cluster['urls'] ) ? $cluster['urls'] : array();

            // Skip if no name
            if ( empty( $name ) ) {
                continue;
            }

            // Generate slug from name if empty
            if ( empty( $slug ) ) {
                $slug = sanitize_title( $name );
            }

            // Filter to only included URLs
            $included_urls = array();
            foreach ( $urls as $url_data ) {
                if ( ! empty( $url_data['include'] ) && ! empty( $url_data['url'] ) ) {
                    $included_urls[] = sanitize_text_field( $url_data['url'] );
                }
            }

            // Skip if no URLs selected
            if ( empty( $included_urls ) ) {
                continue;
            }

            // Create cluster
            $cluster_id = ILC_Cluster_Model::save_cluster(
                array(
                    'name'      => $name,
                    'slug'      => $slug,
                    'heading'   => $name,
                    'subtitle'  => '',
                    'style'     => 'default',
                    'is_active' => 1,
                )
            );

            if ( ! $cluster_id ) {
                continue;
            }

            $created_clusters++;

            // Add URLs to cluster
            $sort_order = 0;
            foreach ( $included_urls as $url ) {
                ILC_Cluster_Model::add_cluster_url(
                    $cluster_id,
                    array(
                        'url'         => $url,
                        'anchor_text' => '',
                        'sort_order'  => $sort_order,
                    )
                );
                $created_urls++;
                $sort_order++;
            }
        }

        // Show success message
        self::render_success_message( $created_clusters, $created_urls );
    }

    /**
     * Render success message after cluster creation.
     *
     * @param int $clusters_count Number of clusters created.
     * @param int $urls_count Number of URLs assigned.
     */
    protected static function render_success_message( $clusters_count, $urls_count ) {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Bulk Cluster Builder - Complete', 'internal-link-clusters' ); ?></h1>

            <?php if ( $clusters_count > 0 ) : ?>
                <div class="notice notice-success">
                    <p>
                        <?php
                        printf(
                            /* translators: %1$d: number of clusters, %2$d: number of URLs */
                            esc_html__( 'Successfully created %1$d clusters with %2$d URLs assigned.', 'internal-link-clusters' ),
                            $clusters_count,
                            $urls_count
                        );
                        ?>
                    </p>
                </div>

                <p><?php esc_html_e( 'Your clusters have been created. You can now edit them to add anchor text, icons, and other details.', 'internal-link-clusters' ); ?></p>

                <p class="submit">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=ilc-clusters' ) ); ?>" class="button button-primary"><?php esc_html_e( 'View All Clusters', 'internal-link-clusters' ); ?></a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=ilc-bulk-builder' ) ); ?>" class="button"><?php esc_html_e( 'Build More Clusters', 'internal-link-clusters' ); ?></a>
                </p>
            <?php else : ?>
                <div class="notice notice-warning">
                    <p><?php esc_html_e( 'No clusters were created. This may be because no clusters had both a name and at least one URL selected.', 'internal-link-clusters' ); ?></p>
                </div>

                <p class="submit">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=ilc-bulk-builder' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Try Again', 'internal-link-clusters' ); ?></a>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }
}

