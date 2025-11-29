<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ILC_Installer {

    public static function install() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $clusters_table = $wpdb->prefix . 'ilc_clusters';
        $urls_table     = $wpdb->prefix . 'ilc_cluster_urls';

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql_clusters = "CREATE TABLE $clusters_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(191) NOT NULL,
            slug varchar(191) NOT NULL,
            heading varchar(255) DEFAULT '' NOT NULL,
            subtitle text NULL,
            style varchar(50) DEFAULT 'default' NOT NULL,
            is_active tinyint(1) DEFAULT 1 NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";

        $sql_urls = "CREATE TABLE $urls_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            cluster_id bigint(20) unsigned NOT NULL,
            url text NOT NULL,
            post_id bigint(20) unsigned NULL,
            anchor_text varchar(255) NULL,
            is_hub tinyint(1) DEFAULT 0 NOT NULL,
            sort_order int(11) DEFAULT 0 NOT NULL,
            PRIMARY KEY  (id),
            KEY cluster_id (cluster_id),
            KEY post_id (post_id)
        ) $charset_collate;";

        dbDelta( $sql_clusters );
        dbDelta( $sql_urls );
    }
}
