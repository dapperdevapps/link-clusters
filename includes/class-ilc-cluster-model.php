<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ILC_Cluster_Model {

    public static function get_cluster_by_identifier( $identifier ) {
        global $wpdb;

        $table = $wpdb->prefix . 'ilc_clusters';

        $cluster = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE slug = %s",
                $identifier
            )
        );

        if ( ! $cluster ) {
            $cluster = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM $table WHERE name = %s",
                    $identifier
                )
            );
        }

        return $cluster;
    }

    public static function get_cluster_by_id( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'ilc_clusters';

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE id = %d",
                $id
            )
        );
    }

    public static function get_cluster_for_page( $post_id = null, $url = null ) {
        global $wpdb;

        $clusters_table      = $wpdb->prefix . 'ilc_clusters';
        $urls_table          = $wpdb->prefix . 'ilc_cluster_urls';
        $display_pages_table = $wpdb->prefix . 'ilc_cluster_display_pages';

        // First, check if the page is part of a cluster (in cluster URLs)
        if ( $post_id ) {
            $cluster = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT c.* FROM $clusters_table c
                     INNER JOIN $urls_table u ON c.id = u.cluster_id
                     WHERE u.post_id = %d
                     LIMIT 1",
                    $post_id
                )
            );

            if ( $cluster ) {
                return $cluster;
            }
        }

        if ( $url ) {
            $cluster = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT c.* FROM $clusters_table c
                     INNER JOIN $urls_table u ON c.id = u.cluster_id
                     WHERE u.url = %s
                     LIMIT 1",
                    $url
                )
            );

            if ( $cluster ) {
                return $cluster;
            }
        }

        // Second, check additional display pages (pages where cluster shows but aren't part of it)
        if ( $post_id ) {
            $cluster = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT c.* FROM $clusters_table c
                     INNER JOIN $display_pages_table d ON c.id = d.cluster_id
                     WHERE d.post_id = %d
                     LIMIT 1",
                    $post_id
                )
            );

            if ( $cluster ) {
                return $cluster;
            }
        }

        if ( $url ) {
            $cluster = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT c.* FROM $clusters_table c
                     INNER JOIN $display_pages_table d ON c.id = d.cluster_id
                     WHERE d.url = %s
                     LIMIT 1",
                    $url
                )
            );

            if ( $cluster ) {
                return $cluster;
            }
        }

        return null;
    }

    public static function get_cluster_urls( $cluster_id ) {
        global $wpdb;

        $urls_table = $wpdb->prefix . 'ilc_cluster_urls';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $urls_table
                 WHERE cluster_id = %d
                 ORDER BY sort_order ASC, id ASC",
                $cluster_id
            )
        );

        return $results ? $results : array();
    }

    public static function get_all_clusters() {
        global $wpdb;

        $table = $wpdb->prefix . 'ilc_clusters';

        $results = $wpdb->get_results( "SELECT * FROM $table ORDER BY name ASC" );

        return $results ? $results : array();
    }

    public static function save_cluster( $data, $id = null ) {
        global $wpdb;

        $table = $wpdb->prefix . 'ilc_clusters';

        $fields = array(
            'name'      => isset( $data['name'] ) ? $data['name'] : '',
            'slug'      => isset( $data['slug'] ) ? $data['slug'] : '',
            'heading'   => isset( $data['heading'] ) ? $data['heading'] : '',
            'subtitle'  => isset( $data['subtitle'] ) ? $data['subtitle'] : '',
            'style'     => isset( $data['style'] ) ? $data['style'] : 'default',
            'is_active' => isset( $data['is_active'] ) ? (int) $data['is_active'] : 1,
            'css_class' => isset( $data['css_class'] ) ? sanitize_html_class( $data['css_class'] ) : '',
        );

        if ( $id ) {
            $updated = $wpdb->update(
                $table,
                $fields,
                array( 'id' => (int) $id ),
                array( '%s', '%s', '%s', '%s', '%s', '%d', '%s' ),
                array( '%d' )
            );

            return $updated !== false ? $id : false;
        } else {
            $inserted = $wpdb->insert(
                $table,
                $fields,
                array( '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
            );

            if ( $inserted ) {
                return (int) $wpdb->insert_id;
            }
        }

        return false;
    }

    public static function delete_cluster( $id ) {
        global $wpdb;

        $clusters_table      = $wpdb->prefix . 'ilc_clusters';
        $urls_table          = $wpdb->prefix . 'ilc_cluster_urls';
        $display_pages_table = $wpdb->prefix . 'ilc_cluster_display_pages';

        $wpdb->delete( $clusters_table, array( 'id' => (int) $id ), array( '%d' ) );
        $wpdb->delete( $urls_table, array( 'cluster_id' => (int) $id ), array( '%d' ) );
        $wpdb->delete( $display_pages_table, array( 'cluster_id' => (int) $id ), array( '%d' ) );
    }

    public static function update_cluster_urls( $cluster_id, $items ) {
        global $wpdb;

        $urls_table = $wpdb->prefix . 'ilc_cluster_urls';

        // Ensure columns exist (for existing installations)
        ILC_Installer::maybe_add_columns();

        foreach ( $items as $item ) {
            if ( empty( $item['id'] ) ) {
                continue;
            }

            $wpdb->update(
                $urls_table,
                array(
                    'url'           => isset( $item['url'] ) ? $item['url'] : '',
                    'anchor_text'  => isset( $item['anchor_text'] ) ? $item['anchor_text'] : '',
                    'sort_order'   => isset( $item['sort_order'] ) ? (int) $item['sort_order'] : 0,
                    'rel_attribute' => isset( $item['rel_attribute'] ) ? sanitize_text_field( $item['rel_attribute'] ) : '',
                    'css_class'    => isset( $item['css_class'] ) ? sanitize_html_class( $item['css_class'] ) : '',
                    'icon_name'    => isset( $item['icon_name'] ) ? sanitize_text_field( $item['icon_name'] ) : '',
                    'icon_color'   => isset( $item['icon_color'] ) ? sanitize_hex_color( $item['icon_color'] ) : '',
                    'hide_cluster' => ! empty( $item['hide_cluster'] ) ? 1 : 0,
                ),
                array( 'id' => (int) $item['id'] ),
                array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d' ),
                array( '%d' )
            );
        }
    }

    public static function add_cluster_url( $cluster_id, $data ) {
        global $wpdb;

        $urls_table = $wpdb->prefix . 'ilc_cluster_urls';

        // Ensure columns exist (for existing installations)
        ILC_Installer::maybe_add_columns();

        $wpdb->insert(
            $urls_table,
            array(
                'cluster_id'    => (int) $cluster_id,
                'url'           => isset( $data['url'] ) ? $data['url'] : '',
                'post_id'       => ! empty( $data['post_id'] ) ? (int) $data['post_id'] : null,
                'anchor_text'   => isset( $data['anchor_text'] ) ? $data['anchor_text'] : '',
                'is_hub'        => ! empty( $data['is_hub'] ) ? 1 : 0,
                'sort_order'    => isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0,
                'rel_attribute' => isset( $data['rel_attribute'] ) ? sanitize_text_field( $data['rel_attribute'] ) : '',
                'css_class'     => isset( $data['css_class'] ) ? sanitize_html_class( $data['css_class'] ) : '',
                'icon_name'     => isset( $data['icon_name'] ) ? sanitize_text_field( $data['icon_name'] ) : '',
                'icon_color'    => isset( $data['icon_color'] ) ? sanitize_hex_color( $data['icon_color'] ) : '',
                'hide_cluster'  => ! empty( $data['hide_cluster'] ) ? 1 : 0,
            ),
            array( '%d', '%s', '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%d' )
        );
    }

    public static function delete_cluster_url( $url_id ) {
        global $wpdb;

        $urls_table = $wpdb->prefix . 'ilc_cluster_urls';

        $wpdb->delete( $urls_table, array( 'id' => (int) $url_id ), array( '%d' ) );
    }

    /**
     * Check if cluster should be hidden for a specific page.
     *
     * @param int         $cluster_id The cluster ID.
     * @param int|null    $post_id    The post ID to check.
     * @param string|null $url        The URL to check.
     * @return bool True if cluster should be hidden on this page.
     */
    public static function should_hide_cluster_for_page( $cluster_id, $post_id = null, $url = null ) {
        global $wpdb;

        $urls_table = $wpdb->prefix . 'ilc_cluster_urls';

        // Check by post_id first
        if ( $post_id ) {
            $hide = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT hide_cluster FROM $urls_table
                     WHERE cluster_id = %d AND post_id = %d
                     LIMIT 1",
                    $cluster_id,
                    $post_id
                )
            );

            if ( $hide !== null ) {
                return (bool) $hide;
            }
        }

        // Check by URL
        if ( $url ) {
            $hide = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT hide_cluster FROM $urls_table
                     WHERE cluster_id = %d AND url = %s
                     LIMIT 1",
                    $cluster_id,
                    $url
                )
            );

            if ( $hide !== null ) {
                return (bool) $hide;
            }
        }

        return false;
    }

    /**
     * Get additional display pages for a cluster.
     * These are pages where the cluster should be shown but are NOT part of the cluster.
     *
     * @param int $cluster_id The cluster ID.
     * @return array Array of display page rows.
     */
    public static function get_display_pages( $cluster_id ) {
        global $wpdb;

        $table = $wpdb->prefix . 'ilc_cluster_display_pages';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE cluster_id = %d ORDER BY id ASC",
                $cluster_id
            )
        );

        return $results ? $results : array();
    }

    /**
     * Add an additional display page for a cluster.
     *
     * @param int   $cluster_id The cluster ID.
     * @param array $data       Data array with 'post_id' and/or 'url'.
     * @return int|false Insert ID on success, false on failure.
     */
    public static function add_display_page( $cluster_id, $data ) {
        global $wpdb;

        $table = $wpdb->prefix . 'ilc_cluster_display_pages';

        // #region agent log
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
        $log_data = array('sessionId'=>'5092ba','runId'=>'run1','hypothesisId'=>'H1','location'=>'class-ilc-cluster-model.php:add_display_page','message'=>'Table check','data'=>array('table'=>$table,'table_exists'=>$table_exists),'timestamp'=>round(microtime(true)*1000));
        file_put_contents(ABSPATH . '../debug-5092ba.log', json_encode($log_data) . "\n", FILE_APPEND);
        // #endregion

        $post_id = ! empty( $data['post_id'] ) ? (int) $data['post_id'] : null;
        $url     = isset( $data['url'] ) ? sanitize_text_field( $data['url'] ) : '';

        // #region agent log
        $log_data = array('sessionId'=>'5092ba','runId'=>'run1','hypothesisId'=>'H3','location'=>'class-ilc-cluster-model.php:add_display_page','message'=>'Parsed data','data'=>array('cluster_id'=>$cluster_id,'post_id'=>$post_id,'url'=>$url,'validation_would_fail'=>(!$post_id && empty($url))),'timestamp'=>round(microtime(true)*1000));
        file_put_contents(ABSPATH . '../debug-5092ba.log', json_encode($log_data) . "\n", FILE_APPEND);
        // #endregion

        if ( ! $post_id && empty( $url ) ) {
            return false;
        }

        $inserted = $wpdb->insert(
            $table,
            array(
                'cluster_id' => (int) $cluster_id,
                'post_id'    => $post_id,
                'url'        => $url,
            ),
            array( '%d', '%d', '%s' )
        );

        // #region agent log
        $log_data = array('sessionId'=>'5092ba','runId'=>'run1','hypothesisId'=>'H2','location'=>'class-ilc-cluster-model.php:add_display_page','message'=>'After insert','data'=>array('inserted'=>$inserted,'insert_id'=>$wpdb->insert_id,'last_error'=>$wpdb->last_error),'timestamp'=>round(microtime(true)*1000));
        file_put_contents(ABSPATH . '../debug-5092ba.log', json_encode($log_data) . "\n", FILE_APPEND);
        // #endregion

        return $inserted ? (int) $wpdb->insert_id : false;
    }

    /**
     * Delete an additional display page.
     *
     * @param int $id The display page row ID.
     */
    public static function delete_display_page( $id ) {
        global $wpdb;

        $table = $wpdb->prefix . 'ilc_cluster_display_pages';

        $wpdb->delete( $table, array( 'id' => (int) $id ), array( '%d' ) );
    }

    /**
     * Delete all display pages for a cluster.
     *
     * @param int $cluster_id The cluster ID.
     */
    public static function delete_display_pages_for_cluster( $cluster_id ) {
        global $wpdb;

        $table = $wpdb->prefix . 'ilc_cluster_display_pages';

        $wpdb->delete( $table, array( 'cluster_id' => (int) $cluster_id ), array( '%d' ) );
    }
}
