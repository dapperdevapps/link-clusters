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

        $clusters_table = $wpdb->prefix . 'ilc_clusters';
        $urls_table     = $wpdb->prefix . 'ilc_cluster_urls';

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

        $clusters_table = $wpdb->prefix . 'ilc_clusters';
        $urls_table     = $wpdb->prefix . 'ilc_cluster_urls';

        $wpdb->delete( $clusters_table, array( 'id' => (int) $id ), array( '%d' ) );
        $wpdb->delete( $urls_table, array( 'cluster_id' => (int) $id ), array( '%d' ) );
    }

    public static function update_cluster_urls( $cluster_id, $items ) {
        global $wpdb;

        $urls_table = $wpdb->prefix . 'ilc_cluster_urls';

        foreach ( $items as $item ) {
            if ( empty( $item['id'] ) ) {
                continue;
            }

            $wpdb->update(
                $urls_table,
                array(
                    'url'           => $item['url'],
                    'anchor_text'  => $item['anchor_text'],
                    'sort_order'   => (int) $item['sort_order'],
                    'rel_attribute' => isset( $item['rel_attribute'] ) ? sanitize_text_field( $item['rel_attribute'] ) : '',
                    'css_class'    => isset( $item['css_class'] ) ? sanitize_html_class( $item['css_class'] ) : '',
                    'icon_name'    => isset( $item['icon_name'] ) ? sanitize_text_field( $item['icon_name'] ) : '',
                    'icon_color'   => isset( $item['icon_color'] ) ? sanitize_hex_color( $item['icon_color'] ) : '',
                ),
                array( 'id' => (int) $item['id'] ),
                array( '%s', '%s', '%d', '%s', '%s', '%s', '%s' ),
                array( '%d' )
            );
        }
    }

    public static function add_cluster_url( $cluster_id, $data ) {
        global $wpdb;

        $urls_table = $wpdb->prefix . 'ilc_cluster_urls';

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
            ),
            array( '%d', '%s', '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
        );
    }

    public static function delete_cluster_url( $url_id ) {
        global $wpdb;

        $urls_table = $wpdb->prefix . 'ilc_cluster_urls';

        $wpdb->delete( $urls_table, array( 'id' => (int) $url_id ), array( '%d' ) );
    }
}
