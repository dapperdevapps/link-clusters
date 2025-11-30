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

        // Handle adding page to cluster
        if ( isset( $_POST['ilc_add_to_cluster'] ) && check_admin_referer( 'ilc_add_to_cluster' ) ) {
            self::handle_add_to_cluster();
        }

        // Get selected cluster ID
        $cluster_id = isset( $_GET['cluster_id'] ) ? (int) $_GET['cluster_id'] : 0;

        // Render the page
        self::render_page( $cluster_id );
    }

    /**
     * Handle adding a page/post to a cluster.
     */
    protected static function handle_add_to_cluster() {
        $cluster_id = isset( $_POST['cluster_id'] ) ? (int) $_POST['cluster_id'] : 0;
        $post_id    = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;

        if ( ! $cluster_id || ! $post_id ) {
            return;
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            return;
        }

        // Get post URL
        $url = get_permalink( $post_id );

        // Get anchor text (use title if available)
        $anchor_text = ! empty( $_POST['anchor_text'] ) ? sanitize_text_field( wp_unslash( $_POST['anchor_text'] ) ) : $post->post_title;

        // Get other fields
        $icon_name    = isset( $_POST['icon_name'] ) ? sanitize_text_field( wp_unslash( $_POST['icon_name'] ) ) : '';
        $icon_color   = isset( $_POST['icon_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['icon_color'] ) ) : '';
        $rel_attribute = isset( $_POST['rel_attribute'] ) ? sanitize_text_field( wp_unslash( $_POST['rel_attribute'] ) ) : '';
        $css_class    = isset( $_POST['css_class'] ) ? sanitize_html_class( wp_unslash( $_POST['css_class'] ) ) : '';
        $sort_order   = isset( $_POST['sort_order'] ) ? (int) $_POST['sort_order'] : 0;

        // Check if URL already exists in cluster
        global $wpdb;
        $urls_table = $wpdb->prefix . 'ilc_cluster_urls';
        $existing   = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM $urls_table WHERE cluster_id = %d AND (post_id = %d OR url = %s) LIMIT 1",
                $cluster_id,
                $post_id,
                $url
            )
        );

        if ( $existing ) {
            // Update existing
            $wpdb->update(
                $urls_table,
                array(
                    'anchor_text'  => $anchor_text,
                    'icon_name'    => $icon_name,
                    'icon_color'   => $icon_color,
                    'rel_attribute' => $rel_attribute,
                    'css_class'    => $css_class,
                    'sort_order'   => $sort_order,
                ),
                array( 'id' => (int) $existing ),
                array( '%s', '%s', '%s', '%s', '%s', '%d' ),
                array( '%d' )
            );
        } else {
            // Add new
            ILC_Cluster_Model::add_cluster_url(
                $cluster_id,
                array(
                    'url'           => $url,
                    'post_id'       => $post_id,
                    'anchor_text'   => $anchor_text,
                    'icon_name'     => $icon_name,
                    'icon_color'    => $icon_color,
                    'rel_attribute' => $rel_attribute,
                    'css_class'     => $css_class,
                    'sort_order'    => $sort_order,
                )
            );
        }

        // Redirect back with success message
        wp_safe_redirect(
            add_query_arg(
                array(
                    'cluster_id' => $cluster_id,
                    'added'      => 1,
                ),
                admin_url( 'admin.php?page=ilc-bulk-builder' )
            )
        );
        exit;
    }

    /**
     * Render the main page with all WordPress pages/posts.
     */
    protected static function render_page( $cluster_id ) {
        // Get all clusters for dropdown
        $clusters = ILC_Cluster_Model::get_all_clusters();

        // Get all pages and posts
        $posts = get_posts(
            array(
                'post_type'      => array( 'page', 'post' ),
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );

        // Get URLs already in the selected cluster
        $cluster_urls = array();
        $cluster_post_ids = array();
        if ( $cluster_id ) {
            $urls = ILC_Cluster_Model::get_cluster_urls( $cluster_id );
            foreach ( $urls as $url_row ) {
                if ( ! empty( $url_row->post_id ) ) {
                    $cluster_post_ids[] = (int) $url_row->post_id;
                }
            }
        }

        // Separate posts into "in cluster" and "not in cluster"
        $posts_in_cluster = array();
        $posts_not_in_cluster = array();

        foreach ( $posts as $post ) {
            if ( in_array( $post->ID, $cluster_post_ids, true ) ) {
                $posts_in_cluster[] = $post;
            } else {
                $posts_not_in_cluster[] = $post;
            }
        }

        // Combine: in cluster first, then not in cluster
        $all_posts = array_merge( $posts_in_cluster, $posts_not_in_cluster );

        // Show success message
        if ( isset( $_GET['added'] ) && $_GET['added'] == 1 ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Page added to cluster successfully!', 'internal-link-clusters' ) . '</p></div>';
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Bulk Builder', 'internal-link-clusters' ); ?></h1>
            <p><?php esc_html_e( 'Select a cluster and add pages/posts to it. Pages already in the cluster appear at the top.', 'internal-link-clusters' ); ?></p>

            <form method="get" style="margin-bottom: 20px;">
                <input type="hidden" name="page" value="ilc-bulk-builder">
                <label for="cluster-select">
                    <strong><?php esc_html_e( 'Select Cluster:', 'internal-link-clusters' ); ?></strong>
                </label>
                <select name="cluster_id" id="cluster-select" style="margin-left: 10px;">
                    <option value="0"><?php esc_html_e( '-- Select a cluster --', 'internal-link-clusters' ); ?></option>
                    <?php foreach ( $clusters as $cluster ) : ?>
                        <option value="<?php echo (int) $cluster->id; ?>" <?php selected( $cluster_id, $cluster->id ); ?>>
                            <?php echo esc_html( $cluster->name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button" style="margin-left: 10px;"><?php esc_html_e( 'Load', 'internal-link-clusters' ); ?></button>
            </form>

            <?php if ( $cluster_id ) : ?>
                <?php
                $cluster = ILC_Cluster_Model::get_cluster_by_id( $cluster_id );
                if ( $cluster ) :
                    ?>
                    <div class="notice notice-info">
                        <p>
                            <strong><?php echo esc_html( $cluster->name ); ?></strong>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=ilc-clusters&action=edit&id=' . $cluster_id ) ); ?>" class="button button-small" style="margin-left: 10px;">
                                <?php esc_html_e( 'Edit Cluster', 'internal-link-clusters' ); ?>
                            </a>
                        </p>
                    </div>

                    <?php if ( ! empty( $all_posts ) ) : ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th style="width: 50px;"><?php esc_html_e( 'ID', 'internal-link-clusters' ); ?></th>
                                    <th><?php esc_html_e( 'Title', 'internal-link-clusters' ); ?></th>
                                    <th><?php esc_html_e( 'Type', 'internal-link-clusters' ); ?></th>
                                    <th><?php esc_html_e( 'URL', 'internal-link-clusters' ); ?></th>
                                    <th style="width: 200px;"><?php esc_html_e( 'Status', 'internal-link-clusters' ); ?></th>
                                    <th style="width: 150px;"><?php esc_html_e( 'Action', 'internal-link-clusters' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $all_posts as $post ) : ?>
                                    <?php
                                    $is_in_cluster = in_array( $post->ID, $cluster_post_ids, true );
                                    $post_url      = get_permalink( $post->ID );
                                    ?>
                                    <tr class="<?php echo $is_in_cluster ? 'in-cluster' : ''; ?>" style="<?php echo $is_in_cluster ? 'background-color: #e8f5e9;' : ''; ?>">
                                        <td><?php echo (int) $post->ID; ?></td>
                                        <td><strong><?php echo esc_html( $post->post_title ); ?></strong></td>
                                        <td><?php echo esc_html( ucfirst( $post->post_type ) ); ?></td>
                                        <td>
                                            <a href="<?php echo esc_url( $post_url ); ?>" target="_blank">
                                                <?php echo esc_html( $post_url ); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php if ( $is_in_cluster ) : ?>
                                                <span style="color: #46b450; font-weight: bold;">✓ <?php esc_html_e( 'In Cluster', 'internal-link-clusters' ); ?></span>
                                            <?php else : ?>
                                                <span style="color: #999;"><?php esc_html_e( 'Not in Cluster', 'internal-link-clusters' ); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ( $is_in_cluster ) : ?>
                                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ilc-clusters&action=edit&id=' . $cluster_id ) ); ?>" class="button button-small">
                                                    <?php esc_html_e( 'Edit in Cluster', 'internal-link-clusters' ); ?>
                                                </a>
                                            <?php else : ?>
                                                <button type="button" class="button button-small ilc-add-to-cluster-btn" 
                                                        data-post-id="<?php echo (int) $post->ID; ?>"
                                                        data-post-title="<?php echo esc_attr( $post->post_title ); ?>"
                                                        data-post-url="<?php echo esc_attr( $post_url ); ?>">
                                                    <?php esc_html_e( 'Add to Cluster', 'internal-link-clusters' ); ?>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p><?php esc_html_e( 'No pages or posts found.', 'internal-link-clusters' ); ?></p>
                    <?php endif; ?>
                <?php else : ?>
                    <div class="notice notice-error">
                        <p><?php esc_html_e( 'Cluster not found.', 'internal-link-clusters' ); ?></p>
                    </div>
                <?php endif; ?>
            <?php else : ?>
                <div class="notice notice-info">
                    <p><?php esc_html_e( 'Please select a cluster to view and add pages.', 'internal-link-clusters' ); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Modal for adding to cluster -->
        <div id="ilc-add-to-cluster-modal" style="display: none;">
            <div class="ilc-modal-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 100000; display: flex; align-items: center; justify-content: center;">
                <div class="ilc-modal-content" style="background: #fff; padding: 20px; border-radius: 4px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto;">
                    <h2><?php esc_html_e( 'Add to Cluster', 'internal-link-clusters' ); ?></h2>
                    <form method="post" id="ilc-add-to-cluster-form">
                        <?php wp_nonce_field( 'ilc_add_to_cluster' ); ?>
                        <input type="hidden" name="ilc_add_to_cluster" value="1">
                        <input type="hidden" name="cluster_id" value="<?php echo (int) $cluster_id; ?>">
                        <input type="hidden" name="post_id" id="ilc-modal-post-id" value="">

                        <table class="form-table">
                            <tbody>
                                <tr>
                                    <th scope="row"><?php esc_html_e( 'Page/Post', 'internal-link-clusters' ); ?></th>
                                    <td>
                                        <strong id="ilc-modal-post-title"></strong><br>
                                        <small id="ilc-modal-post-url" style="color: #666;"></small>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="ilc-modal-anchor-text"><?php esc_html_e( 'Anchor Text', 'internal-link-clusters' ); ?></label></th>
                                    <td>
                                        <input type="text" name="anchor_text" id="ilc-modal-anchor-text" class="regular-text" value="">
                                        <p class="description"><?php esc_html_e( 'Leave blank to use page title.', 'internal-link-clusters' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="ilc-modal-icon-name"><?php esc_html_e( 'Icon', 'internal-link-clusters' ); ?></label></th>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 5px;">
                                            <input type="text" name="icon_name" id="ilc-modal-icon-name" class="regular-text ilc-icon-name-input" placeholder="fa-home" style="flex: 1;">
                                            <button type="button" class="button ilc-icon-picker-btn"><i class="fas fa-icons"></i> <?php esc_html_e( 'Pick Icon', 'internal-link-clusters' ); ?></button>
                                        </div>
                                        <p class="description" style="font-size:11px; margin:2px 0 0;"><?php esc_html_e( 'Font Awesome class (e.g., fa-home)', 'internal-link-clusters' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="ilc-modal-icon-color"><?php esc_html_e( 'Icon Color', 'internal-link-clusters' ); ?></label></th>
                                    <td>
                                        <input type="text" name="icon_color" id="ilc-modal-icon-color" class="ilc-color-picker-small" data-default-color="">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="ilc-modal-rel"><?php esc_html_e( 'Rel Attribute', 'internal-link-clusters' ); ?></label></th>
                                    <td>
                                        <select name="rel_attribute" id="ilc-modal-rel">
                                            <option value=""><?php esc_html_e( 'Default', 'internal-link-clusters' ); ?></option>
                                            <option value="nofollow"><?php esc_html_e( 'Nofollow', 'internal-link-clusters' ); ?></option>
                                            <option value="nofollow sponsored"><?php esc_html_e( 'Nofollow Sponsored', 'internal-link-clusters' ); ?></option>
                                            <option value="nofollow ugc"><?php esc_html_e( 'Nofollow UGC', 'internal-link-clusters' ); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="ilc-modal-css-class"><?php esc_html_e( 'CSS Class', 'internal-link-clusters' ); ?></label></th>
                                    <td>
                                        <input type="text" name="css_class" id="ilc-modal-css-class" class="regular-text" placeholder="custom-class">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="ilc-modal-sort-order"><?php esc_html_e( 'Sort Order', 'internal-link-clusters' ); ?></label></th>
                                    <td>
                                        <input type="number" name="sort_order" id="ilc-modal-sort-order" class="small-text" value="0">
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <p class="submit">
                            <button type="submit" class="button button-primary"><?php esc_html_e( 'Add to Cluster', 'internal-link-clusters' ); ?></button>
                            <button type="button" class="button ilc-modal-close"><?php esc_html_e( 'Cancel', 'internal-link-clusters' ); ?></button>
                        </p>
                    </form>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Handle "Add to Cluster" button click
            $('.ilc-add-to-cluster-btn').on('click', function() {
                var postId = $(this).data('post-id');
                var postTitle = $(this).data('post-title');
                var postUrl = $(this).data('post-url');

                $('#ilc-modal-post-id').val(postId);
                $('#ilc-modal-post-title').text(postTitle);
                $('#ilc-modal-post-url').text(postUrl);
                $('#ilc-modal-anchor-text').val(postTitle);
                $('#ilc-modal-icon-name').val('');
                $('#ilc-modal-icon-color').val('');
                $('#ilc-modal-rel').val('');
                $('#ilc-modal-css-class').val('');
                $('#ilc-modal-sort-order').val('0');

                $('#ilc-add-to-cluster-modal').show();
            });

            // Close modal
            $('.ilc-modal-close, .ilc-modal-overlay').on('click', function(e) {
                if (e.target === this) {
                    $('#ilc-add-to-cluster-modal').hide();
                }
            });

            // Prevent modal content clicks from closing
            $('.ilc-modal-content').on('click', function(e) {
                e.stopPropagation();
            });
        });
        </script>
        <?php
    }
}
