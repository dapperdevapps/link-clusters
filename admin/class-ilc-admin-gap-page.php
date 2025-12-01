<?php
/**
 * ILC_Admin_Gap_Page - Admin UI for Internal Link Gap Finder.
 *
 * Displays link suggestions and allows users to manage them
 * (accept, dismiss, delete, run scans).
 *
 * @package Internal_Link_Clusters
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ILC_Admin_Gap_Page {

    /**
     * Items per page for pagination.
     */
    const ITEMS_PER_PAGE = 20;

    /**
     * Render the Link Suggestions admin page.
     */
    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Process any pending actions first
        self::handle_actions();

        // Get current filters
        $current_cluster = isset( $_GET['cluster_id'] ) ? absint( $_GET['cluster_id'] ) : null;
        $current_status  = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : null;
        $current_page    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

        // Get suggestions
        $args = array(
            'cluster_id' => $current_cluster,
            'status'     => $current_status,
            'limit'      => self::ITEMS_PER_PAGE,
            'offset'     => ( $current_page - 1 ) * self::ITEMS_PER_PAGE,
            'orderby'    => 'confidence',
            'order'      => 'DESC',
        );

        $suggestions = ILC_Gap_Model::get_suggestions( $args );
        $total_items = ILC_Gap_Model::count_suggestions( array(
            'cluster_id' => $current_cluster,
            'status'     => $current_status,
        ) );
        $total_pages = ceil( $total_items / self::ITEMS_PER_PAGE );

        // Get all clusters for filter dropdown
        $clusters = ILC_Cluster_Model::get_all_clusters();

        // Get stats
        $stats = ILC_Gap_Finder::get_stats();

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Link Suggestions', 'internal-link-clusters' ); ?></h1>

            <!-- Run Scan Button -->
            <form method="post" style="display: inline-block; margin-left: 10px;">
                <?php wp_nonce_field( 'ilc_run_gap_scan', 'ilc_gap_scan_nonce' ); ?>
                <button type="submit" name="ilc_run_scan" class="page-title-action">
                    <?php esc_html_e( 'Run Internal Link Gap Scan', 'internal-link-clusters' ); ?>
                </button>
            </form>

            <hr class="wp-header-end">

            <!-- Stats Summary -->
            <div class="ilc-gap-stats" style="margin: 15px 0; padding: 15px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
                <strong><?php esc_html_e( 'Summary:', 'internal-link-clusters' ); ?></strong>
                <span style="margin-left: 15px;">
                    <?php
                    printf(
                        /* translators: %d: number of suggestions */
                        esc_html__( 'Total: %d', 'internal-link-clusters' ),
                        $stats['total']
                    );
                    ?>
                </span>
                <span style="margin-left: 15px; color: #0073aa;">
                    <?php
                    printf(
                        /* translators: %d: number of new suggestions */
                        esc_html__( 'New: %d', 'internal-link-clusters' ),
                        $stats['new']
                    );
                    ?>
                </span>
                <span style="margin-left: 15px; color: #46b450;">
                    <?php
                    printf(
                        /* translators: %d: number of accepted suggestions */
                        esc_html__( 'Accepted: %d', 'internal-link-clusters' ),
                        $stats['accepted']
                    );
                    ?>
                </span>
                <span style="margin-left: 15px; color: #999;">
                    <?php
                    printf(
                        /* translators: %d: number of dismissed suggestions */
                        esc_html__( 'Dismissed: %d', 'internal-link-clusters' ),
                        $stats['dismissed']
                    );
                    ?>
                </span>
            </div>

            <!-- Filters -->
            <form method="get" style="margin-bottom: 15px;">
                <input type="hidden" name="page" value="ilc-gap-finder">

                <select name="cluster_id">
                    <option value=""><?php esc_html_e( 'All Clusters', 'internal-link-clusters' ); ?></option>
                    <?php foreach ( $clusters as $cluster ) : ?>
                        <option value="<?php echo esc_attr( $cluster->id ); ?>" <?php selected( $current_cluster, $cluster->id ); ?>>
                            <?php echo esc_html( $cluster->name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="status">
                    <option value=""><?php esc_html_e( 'All Statuses', 'internal-link-clusters' ); ?></option>
                    <option value="new" <?php selected( $current_status, 'new' ); ?>><?php esc_html_e( 'New', 'internal-link-clusters' ); ?></option>
                    <option value="accepted" <?php selected( $current_status, 'accepted' ); ?>><?php esc_html_e( 'Accepted', 'internal-link-clusters' ); ?></option>
                    <option value="dismissed" <?php selected( $current_status, 'dismissed' ); ?>><?php esc_html_e( 'Dismissed', 'internal-link-clusters' ); ?></option>
                </select>

                <button type="submit" class="button"><?php esc_html_e( 'Filter', 'internal-link-clusters' ); ?></button>

                <?php if ( $current_cluster || $current_status ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=ilc-gap-finder' ) ); ?>" class="button">
                        <?php esc_html_e( 'Clear Filters', 'internal-link-clusters' ); ?>
                    </a>
                <?php endif; ?>
            </form>

            <!-- Suggestions Table -->
            <?php if ( empty( $suggestions ) ) : ?>
                <div class="notice notice-info">
                    <p>
                        <?php
                        if ( $stats['total'] === 0 ) {
                            esc_html_e( 'No link suggestions found. Click "Run Internal Link Gap Scan" to analyze your content.', 'internal-link-clusters' );
                        } else {
                            esc_html_e( 'No suggestions match your current filters.', 'internal-link-clusters' );
                        }
                        ?>
                    </p>
                </div>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col" style="width: 25%;"><?php esc_html_e( 'Source Page', 'internal-link-clusters' ); ?></th>
                            <th scope="col" style="width: 25%;"><?php esc_html_e( 'Target URL', 'internal-link-clusters' ); ?></th>
                            <th scope="col" style="width: 20%;"><?php esc_html_e( 'Matched Keyword', 'internal-link-clusters' ); ?></th>
                            <th scope="col" style="width: 8%;"><?php esc_html_e( 'Confidence', 'internal-link-clusters' ); ?></th>
                            <th scope="col" style="width: 8%;"><?php esc_html_e( 'Status', 'internal-link-clusters' ); ?></th>
                            <th scope="col" style="width: 14%;"><?php esc_html_e( 'Actions', 'internal-link-clusters' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $suggestions as $suggestion ) : ?>
                            <?php
                            $source_post   = get_post( $suggestion->source_post_id );
                            $source_title  = $source_post ? $source_post->post_title : sprintf( __( 'Post #%d', 'internal-link-clusters' ), $suggestion->source_post_id );
                            $target_title  = self::get_target_display( $suggestion );
                            $status_class  = self::get_status_class( $suggestion->status );
                            $status_label  = self::get_status_label( $suggestion->status );
                            ?>
                            <tr class="<?php echo esc_attr( $status_class ); ?>">
                                <!-- Source Page -->
                                <td>
                                    <?php if ( $source_post ) : ?>
                                        <strong>
                                            <a href="<?php echo esc_url( get_edit_post_link( $source_post->ID ) ); ?>">
                                                <?php echo esc_html( $source_title ); ?>
                                            </a>
                                        </strong>
                                        <div class="row-actions">
                                            <span class="edit">
                                                <a href="<?php echo esc_url( get_edit_post_link( $source_post->ID ) ); ?>">
                                                    <?php esc_html_e( 'Edit', 'internal-link-clusters' ); ?>
                                                </a> |
                                            </span>
                                            <span class="view">
                                                <a href="<?php echo esc_url( get_permalink( $source_post->ID ) ); ?>" target="_blank">
                                                    <?php esc_html_e( 'View', 'internal-link-clusters' ); ?>
                                                </a>
                                            </span>
                                        </div>
                                    <?php else : ?>
                                        <span class="dashicons dashicons-warning" style="color: #dba617;"></span>
                                        <?php echo esc_html( $source_title ); ?>
                                        <em>(<?php esc_html_e( 'not found', 'internal-link-clusters' ); ?>)</em>
                                    <?php endif; ?>
                                </td>

                                <!-- Target URL -->
                                <td>
                                    <?php echo wp_kses_post( $target_title ); ?>
                                    <div class="row-actions">
                                        <span class="view">
                                            <a href="<?php echo esc_url( $suggestion->target_url ); ?>" target="_blank">
                                                <?php esc_html_e( 'View', 'internal-link-clusters' ); ?>
                                            </a>
                                        </span>
                                    </div>
                                </td>

                                <!-- Matched Keyword -->
                                <td>
                                    <code><?php echo esc_html( $suggestion->matched_keyword ); ?></code>
                                </td>

                                <!-- Confidence -->
                                <td>
                                    <?php echo esc_html( self::format_confidence( $suggestion->confidence ) ); ?>
                                </td>

                                <!-- Status -->
                                <td>
                                    <span class="ilc-status ilc-status-<?php echo esc_attr( $suggestion->status ); ?>">
                                        <?php echo esc_html( $status_label ); ?>
                                    </span>
                                </td>

                                <!-- Actions -->
                                <td>
                                    <?php echo self::render_action_links( $suggestion ); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ( $total_pages > 1 ) : ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <?php
                            $base_url = admin_url( 'admin.php?page=ilc-gap-finder' );
                            if ( $current_cluster ) {
                                $base_url = add_query_arg( 'cluster_id', $current_cluster, $base_url );
                            }
                            if ( $current_status ) {
                                $base_url = add_query_arg( 'status', $current_status, $base_url );
                            }

                            echo paginate_links( array(
                                'base'      => add_query_arg( 'paged', '%#%', $base_url ),
                                'format'    => '',
                                'current'   => $current_page,
                                'total'     => $total_pages,
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                            ) );
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Inline Styles -->
            <style>
                .ilc-status {
                    display: inline-block;
                    padding: 3px 8px;
                    border-radius: 3px;
                    font-size: 12px;
                    font-weight: 500;
                }
                .ilc-status-new {
                    background: #0073aa;
                    color: #fff;
                }
                .ilc-status-accepted {
                    background: #46b450;
                    color: #fff;
                }
                .ilc-status-dismissed {
                    background: #ccc;
                    color: #555;
                }
                tr.ilc-row-accepted td {
                    opacity: 0.7;
                }
                tr.ilc-row-dismissed td {
                    opacity: 0.5;
                }
                .ilc-action-link {
                    margin-right: 8px;
                    text-decoration: none;
                }
                .ilc-action-link.accept {
                    color: #46b450;
                }
                .ilc-action-link.dismiss {
                    color: #a00;
                }
                .ilc-action-link.delete {
                    color: #dc3232;
                }
                .ilc-action-link:hover {
                    text-decoration: underline;
                }
            </style>
        </div>
        <?php
    }

    /**
     * Handle form actions (scan, status updates, deletes).
     */
    private static function handle_actions() {
        // Handle scan action
        if ( isset( $_POST['ilc_run_scan'] ) && check_admin_referer( 'ilc_run_gap_scan', 'ilc_gap_scan_nonce' ) ) {
            $stats = ILC_Gap_Finder::scan_all_clusters( true );

            $message = sprintf(
                /* translators: %1$d: clusters scanned, %2$d: URLs processed, %3$d: suggestions created */
                __( 'Scan complete! Scanned %1$d clusters, processed %2$d URLs, created %3$d suggestions.', 'internal-link-clusters' ),
                $stats['clusters_scanned'],
                $stats['urls_processed'],
                $stats['suggestions_created']
            );

            add_settings_error( 'ilc_gap_finder', 'scan_complete', $message, 'success' );
            settings_errors( 'ilc_gap_finder' );
        }

        // Handle status update
        if ( isset( $_GET['action'] ) && isset( $_GET['suggestion_id'] ) && isset( $_GET['_wpnonce'] ) ) {
            $action        = sanitize_text_field( wp_unslash( $_GET['action'] ) );
            $suggestion_id = absint( $_GET['suggestion_id'] );
            $nonce         = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );

            if ( ! wp_verify_nonce( $nonce, 'ilc_gap_action_' . $suggestion_id ) ) {
                add_settings_error( 'ilc_gap_finder', 'invalid_nonce', __( 'Security check failed.', 'internal-link-clusters' ), 'error' );
                settings_errors( 'ilc_gap_finder' );
                return;
            }

            switch ( $action ) {
                case 'accept':
                    if ( ILC_Gap_Model::update_status( $suggestion_id, 'accepted' ) ) {
                        add_settings_error( 'ilc_gap_finder', 'status_updated', __( 'Suggestion marked as accepted.', 'internal-link-clusters' ), 'success' );
                    }
                    break;

                case 'dismiss':
                    if ( ILC_Gap_Model::update_status( $suggestion_id, 'dismissed' ) ) {
                        add_settings_error( 'ilc_gap_finder', 'status_updated', __( 'Suggestion dismissed.', 'internal-link-clusters' ), 'success' );
                    }
                    break;

                case 'reset':
                    if ( ILC_Gap_Model::update_status( $suggestion_id, 'new' ) ) {
                        add_settings_error( 'ilc_gap_finder', 'status_updated', __( 'Suggestion reset to new.', 'internal-link-clusters' ), 'success' );
                    }
                    break;

                case 'delete':
                    if ( ILC_Gap_Model::delete_suggestion( $suggestion_id ) ) {
                        add_settings_error( 'ilc_gap_finder', 'deleted', __( 'Suggestion deleted.', 'internal-link-clusters' ), 'success' );
                    }
                    break;
            }

            settings_errors( 'ilc_gap_finder' );
        }
    }

    /**
     * Get display text for target URL.
     *
     * @param object $suggestion Suggestion object.
     * @return string HTML for target display.
     */
    private static function get_target_display( $suggestion ) {
        // Try to find associated post via cluster URL
        if ( ! empty( $suggestion->cluster_id ) ) {
            $urls = ILC_Cluster_Model::get_cluster_urls( $suggestion->cluster_id );
            foreach ( $urls as $url_row ) {
                if ( $url_row->url === $suggestion->target_url && ! empty( $url_row->post_id ) ) {
                    $target_post = get_post( $url_row->post_id );
                    if ( $target_post ) {
                        return '<strong>' . esc_html( $target_post->post_title ) . '</strong><br><small>' . esc_html( $suggestion->target_url ) . '</small>';
                    }
                }
            }
        }

        // Return just the URL
        return '<strong>' . esc_html( self::truncate_url( $suggestion->target_url, 50 ) ) . '</strong>';
    }

    /**
     * Truncate a URL for display.
     *
     * @param string $url    URL to truncate.
     * @param int    $length Max length.
     * @return string Truncated URL.
     */
    private static function truncate_url( $url, $length = 50 ) {
        if ( strlen( $url ) <= $length ) {
            return $url;
        }

        // Remove protocol for cleaner display
        $url = preg_replace( '#^https?://#', '', $url );

        if ( strlen( $url ) <= $length ) {
            return $url;
        }

        return substr( $url, 0, $length - 3 ) . '...';
    }

    /**
     * Get CSS class for status.
     *
     * @param string $status Status value.
     * @return string CSS class.
     */
    private static function get_status_class( $status ) {
        switch ( $status ) {
            case 'accepted':
                return 'ilc-row-accepted';
            case 'dismissed':
                return 'ilc-row-dismissed';
            default:
                return 'ilc-row-new';
        }
    }

    /**
     * Get human-readable status label.
     *
     * @param string $status Status value.
     * @return string Status label.
     */
    private static function get_status_label( $status ) {
        switch ( $status ) {
            case 'accepted':
                return __( 'Accepted', 'internal-link-clusters' );
            case 'dismissed':
                return __( 'Dismissed', 'internal-link-clusters' );
            default:
                return __( 'New', 'internal-link-clusters' );
        }
    }

    /**
     * Format confidence score for display.
     *
     * @param float $confidence Confidence score.
     * @return string Formatted confidence.
     */
    private static function format_confidence( $confidence ) {
        $score = round( $confidence, 1 );

        if ( $score >= 8 ) {
            return $score . ' ★★★';
        } elseif ( $score >= 5 ) {
            return $score . ' ★★';
        } elseif ( $score > 0 ) {
            return $score . ' ★';
        }

        return $score;
    }

    /**
     * Render action links for a suggestion.
     *
     * @param object $suggestion Suggestion object.
     * @return string HTML for action links.
     */
    private static function render_action_links( $suggestion ) {
        $base_url = admin_url( 'admin.php?page=ilc-gap-finder' );
        $nonce    = wp_create_nonce( 'ilc_gap_action_' . $suggestion->id );

        // Preserve current filters in action URLs
        if ( isset( $_GET['cluster_id'] ) ) {
            $base_url = add_query_arg( 'cluster_id', absint( $_GET['cluster_id'] ), $base_url );
        }
        if ( isset( $_GET['status'] ) ) {
            $base_url = add_query_arg( 'status', sanitize_text_field( wp_unslash( $_GET['status'] ) ), $base_url );
        }
        if ( isset( $_GET['paged'] ) ) {
            $base_url = add_query_arg( 'paged', absint( $_GET['paged'] ), $base_url );
        }

        $links = array();

        // Show different actions based on current status
        if ( $suggestion->status !== 'accepted' ) {
            $accept_url = add_query_arg( array(
                'action'        => 'accept',
                'suggestion_id' => $suggestion->id,
                '_wpnonce'      => $nonce,
            ), $base_url );
            $links[] = '<a href="' . esc_url( $accept_url ) . '" class="ilc-action-link accept">' . esc_html__( 'Accept', 'internal-link-clusters' ) . '</a>';
        }

        if ( $suggestion->status !== 'dismissed' ) {
            $dismiss_url = add_query_arg( array(
                'action'        => 'dismiss',
                'suggestion_id' => $suggestion->id,
                '_wpnonce'      => $nonce,
            ), $base_url );
            $links[] = '<a href="' . esc_url( $dismiss_url ) . '" class="ilc-action-link dismiss">' . esc_html__( 'Dismiss', 'internal-link-clusters' ) . '</a>';
        }

        // Reset to new if accepted or dismissed
        if ( $suggestion->status !== 'new' ) {
            $reset_url = add_query_arg( array(
                'action'        => 'reset',
                'suggestion_id' => $suggestion->id,
                '_wpnonce'      => $nonce,
            ), $base_url );
            $links[] = '<a href="' . esc_url( $reset_url ) . '" class="ilc-action-link reset">' . esc_html__( 'Reset', 'internal-link-clusters' ) . '</a>';
        }

        // Delete link
        $delete_url = add_query_arg( array(
            'action'        => 'delete',
            'suggestion_id' => $suggestion->id,
            '_wpnonce'      => $nonce,
        ), $base_url );
        $links[] = '<a href="' . esc_url( $delete_url ) . '" class="ilc-action-link delete" onclick="return confirm(\'' . esc_js( __( 'Are you sure you want to delete this suggestion?', 'internal-link-clusters' ) ) . '\');">' . esc_html__( 'Delete', 'internal-link-clusters' ) . '</a>';

        return implode( ' ', $links );
    }
}

