<?php
/**
 * ILC_Gap_Model - CRUD operations for link suggestions.
 *
 * This model handles all database interactions for the Internal Link Gap Finder
 * feature, managing link suggestions stored in the ilc_link_suggestions table.
 *
 * @package Internal_Link_Clusters
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ILC_Gap_Model {

    /**
     * Get the suggestions table name.
     *
     * @return string
     */
    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'ilc_link_suggestions';
    }

    /**
     * Create a new link suggestion.
     *
     * @param array $data {
     *     Suggestion data.
     *     @type int|null $cluster_id      Optional cluster ID.
     *     @type string   $target_url      Target URL (required).
     *     @type int      $source_post_id  Source post ID (required).
     *     @type string   $matched_keyword Matched keyword (required).
     *     @type float    $confidence      Confidence score.
     *     @type string   $status          Status (new, accepted, dismissed).
     * }
     * @return int|false Insert ID on success, false on failure.
     */
    public static function create_suggestion( $data ) {
        global $wpdb;

        $table = self::get_table_name();

        $insert_data = array(
            'cluster_id'      => isset( $data['cluster_id'] ) ? absint( $data['cluster_id'] ) : null,
            'target_url'      => isset( $data['target_url'] ) ? esc_url_raw( $data['target_url'] ) : '',
            'source_post_id'  => isset( $data['source_post_id'] ) ? absint( $data['source_post_id'] ) : 0,
            'matched_keyword' => isset( $data['matched_keyword'] ) ? sanitize_text_field( $data['matched_keyword'] ) : '',
            'confidence'      => isset( $data['confidence'] ) ? floatval( $data['confidence'] ) : 0,
            'status'          => isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'new',
        );

        // Validate required fields
        if ( empty( $insert_data['target_url'] ) || empty( $insert_data['source_post_id'] ) || empty( $insert_data['matched_keyword'] ) ) {
            return false;
        }

        $formats = array(
            '%d', // cluster_id
            '%s', // target_url
            '%d', // source_post_id
            '%s', // matched_keyword
            '%f', // confidence
            '%s', // status
        );

        // Handle null cluster_id
        if ( is_null( $insert_data['cluster_id'] ) ) {
            $formats[0] = null;
        }

        $result = $wpdb->insert( $table, $insert_data, $formats );

        if ( $result ) {
            return (int) $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Get link suggestions with optional filtering.
     *
     * @param array $args {
     *     Optional query arguments.
     *     @type int    $cluster_id     Filter by cluster ID.
     *     @type int    $source_post_id Filter by source post ID.
     *     @type string $status         Filter by status (new, accepted, dismissed).
     *     @type string $orderby        Order by column.
     *     @type string $order          Order direction (ASC, DESC).
     *     @type int    $limit          Number of results to return.
     *     @type int    $offset         Offset for pagination.
     * }
     * @return array Array of suggestion objects.
     */
    public static function get_suggestions( $args = array() ) {
        global $wpdb;

        $table = self::get_table_name();

        $defaults = array(
            'cluster_id'     => null,
            'source_post_id' => null,
            'status'         => null,
            'orderby'        => 'created_at',
            'order'          => 'DESC',
            'limit'          => 100,
            'offset'         => 0,
        );

        $args = wp_parse_args( $args, $defaults );

        $where_clauses = array( '1=1' );
        $where_values  = array();

        // Filter by cluster_id
        if ( ! is_null( $args['cluster_id'] ) ) {
            $where_clauses[] = 'cluster_id = %d';
            $where_values[]  = absint( $args['cluster_id'] );
        }

        // Filter by source_post_id
        if ( ! is_null( $args['source_post_id'] ) ) {
            $where_clauses[] = 'source_post_id = %d';
            $where_values[]  = absint( $args['source_post_id'] );
        }

        // Filter by status
        if ( ! is_null( $args['status'] ) ) {
            $where_clauses[] = 'status = %s';
            $where_values[]  = sanitize_text_field( $args['status'] );
        }

        // Build WHERE clause
        $where_sql = implode( ' AND ', $where_clauses );

        // Sanitize orderby
        $allowed_orderby = array( 'id', 'cluster_id', 'source_post_id', 'matched_keyword', 'confidence', 'status', 'created_at', 'updated_at' );
        $orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';

        // Sanitize order
        $order = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        // Build full query
        $sql = "SELECT * FROM $table WHERE $where_sql ORDER BY $orderby $order LIMIT %d OFFSET %d";

        // Add limit and offset to values
        $where_values[] = absint( $args['limit'] );
        $where_values[] = absint( $args['offset'] );

        if ( ! empty( $where_values ) ) {
            $query = $wpdb->prepare( $sql, $where_values );
        } else {
            $query = $sql;
        }

        $results = $wpdb->get_results( $query );

        return $results ? $results : array();
    }

    /**
     * Get total count of suggestions with optional filtering.
     *
     * @param array $args Same filtering args as get_suggestions().
     * @return int Total count.
     */
    public static function count_suggestions( $args = array() ) {
        global $wpdb;

        $table = self::get_table_name();

        $where_clauses = array( '1=1' );
        $where_values  = array();

        // Filter by cluster_id
        if ( isset( $args['cluster_id'] ) && ! is_null( $args['cluster_id'] ) ) {
            $where_clauses[] = 'cluster_id = %d';
            $where_values[]  = absint( $args['cluster_id'] );
        }

        // Filter by status
        if ( isset( $args['status'] ) && ! is_null( $args['status'] ) ) {
            $where_clauses[] = 'status = %s';
            $where_values[]  = sanitize_text_field( $args['status'] );
        }

        $where_sql = implode( ' AND ', $where_clauses );

        $sql = "SELECT COUNT(*) FROM $table WHERE $where_sql";

        if ( ! empty( $where_values ) ) {
            return (int) $wpdb->get_var( $wpdb->prepare( $sql, $where_values ) );
        }

        return (int) $wpdb->get_var( $sql );
    }

    /**
     * Update the status of a suggestion.
     *
     * @param int    $id     Suggestion ID.
     * @param string $status New status (new, accepted, dismissed).
     * @return bool True on success, false on failure.
     */
    public static function update_status( $id, $status ) {
        global $wpdb;

        $table = self::get_table_name();

        $allowed_statuses = array( 'new', 'accepted', 'dismissed' );
        if ( ! in_array( $status, $allowed_statuses, true ) ) {
            return false;
        }

        $result = $wpdb->update(
            $table,
            array( 'status' => $status ),
            array( 'id' => absint( $id ) ),
            array( '%s' ),
            array( '%d' )
        );

        return $result !== false;
    }

    /**
     * Delete a single suggestion.
     *
     * @param int $id Suggestion ID.
     * @return bool True on success, false on failure.
     */
    public static function delete_suggestion( $id ) {
        global $wpdb;

        $table = self::get_table_name();

        $result = $wpdb->delete(
            $table,
            array( 'id' => absint( $id ) ),
            array( '%d' )
        );

        return $result !== false;
    }

    /**
     * Clear all suggestions for a specific cluster.
     *
     * @param int $cluster_id Cluster ID.
     * @return int|false Number of rows deleted, or false on failure.
     */
    public static function clear_for_cluster( $cluster_id ) {
        global $wpdb;

        $table = self::get_table_name();

        return $wpdb->delete(
            $table,
            array( 'cluster_id' => absint( $cluster_id ) ),
            array( '%d' )
        );
    }

    /**
     * Clear all suggestions with 'new' status.
     *
     * @return int|false Number of rows deleted, or false on failure.
     */
    public static function clear_new_suggestions() {
        global $wpdb;

        $table = self::get_table_name();

        return $wpdb->delete(
            $table,
            array( 'status' => 'new' ),
            array( '%s' )
        );
    }

    /**
     * Check if a suggestion already exists.
     *
     * @param string $target_url      Target URL.
     * @param int    $source_post_id  Source post ID.
     * @param string $matched_keyword Matched keyword.
     * @return bool True if exists, false otherwise.
     */
    public static function suggestion_exists( $target_url, $source_post_id, $matched_keyword ) {
        global $wpdb;

        $table = self::get_table_name();

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM $table WHERE target_url = %s AND source_post_id = %d AND matched_keyword = %s LIMIT 1",
                esc_url_raw( $target_url ),
                absint( $source_post_id ),
                sanitize_text_field( $matched_keyword )
            )
        );

        return ! empty( $exists );
    }

    /**
     * Get a single suggestion by ID.
     *
     * @param int $id Suggestion ID.
     * @return object|null Suggestion object or null if not found.
     */
    public static function get_suggestion( $id ) {
        global $wpdb;

        $table = self::get_table_name();

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE id = %d",
                absint( $id )
            )
        );
    }

    /**
     * Bulk update status for multiple suggestions.
     *
     * @param array  $ids    Array of suggestion IDs.
     * @param string $status New status.
     * @return int Number of rows updated.
     */
    public static function bulk_update_status( $ids, $status ) {
        global $wpdb;

        if ( empty( $ids ) || ! is_array( $ids ) ) {
            return 0;
        }

        $allowed_statuses = array( 'new', 'accepted', 'dismissed' );
        if ( ! in_array( $status, $allowed_statuses, true ) ) {
            return 0;
        }

        $table = self::get_table_name();

        // Sanitize IDs
        $ids = array_map( 'absint', $ids );
        $ids = array_filter( $ids );

        if ( empty( $ids ) ) {
            return 0;
        }

        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

        $sql = $wpdb->prepare(
            "UPDATE $table SET status = %s WHERE id IN ($placeholders)",
            array_merge( array( $status ), $ids )
        );

        return $wpdb->query( $sql );
    }
}

