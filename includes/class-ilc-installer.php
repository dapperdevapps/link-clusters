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
            css_class varchar(255) DEFAULT '' NOT NULL,
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
            rel_attribute varchar(100) DEFAULT '' NOT NULL,
            css_class varchar(255) DEFAULT '' NOT NULL,
            icon_name varchar(100) DEFAULT '' NOT NULL,
            icon_color varchar(7) DEFAULT '' NOT NULL,
            PRIMARY KEY  (id),
            KEY cluster_id (cluster_id),
            KEY post_id (post_id)
        ) $charset_collate;";

        dbDelta( $sql_clusters );
        dbDelta( $sql_urls );

        // Add new columns to existing installations
        self::maybe_add_columns();
    }

    /**
     * Add new columns to existing database tables if they don't exist.
     * Made public so it can be called from other classes.
     */
    public static function maybe_add_columns() {
        global $wpdb;

        $clusters_table = $wpdb->prefix . 'ilc_clusters';
        $urls_table     = $wpdb->prefix . 'ilc_cluster_urls';

        // Check if css_class column exists in clusters table
        $column_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM $clusters_table LIKE %s", 'css_class' ) );
        if ( empty( $column_exists ) ) {
            $wpdb->query( "ALTER TABLE $clusters_table ADD COLUMN css_class varchar(255) DEFAULT '' NOT NULL AFTER is_active" );
        }

        // Check if rel_attribute column exists in urls table
        $column_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM $urls_table LIKE %s", 'rel_attribute' ) );
        if ( empty( $column_exists ) ) {
            $wpdb->query( "ALTER TABLE $urls_table ADD COLUMN rel_attribute varchar(100) DEFAULT '' NOT NULL AFTER sort_order" );
        }

        // Check if css_class column exists in urls table
        $column_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM $urls_table LIKE %s", 'css_class' ) );
        if ( empty( $column_exists ) ) {
            $wpdb->query( "ALTER TABLE $urls_table ADD COLUMN css_class varchar(255) DEFAULT '' NOT NULL AFTER rel_attribute" );
        }

        // Check if icon_name column exists in urls table
        $column_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM $urls_table LIKE %s", 'icon_name' ) );
        if ( empty( $column_exists ) ) {
            $wpdb->query( "ALTER TABLE $urls_table ADD COLUMN icon_name varchar(100) DEFAULT '' NOT NULL AFTER css_class" );
        }

        // Check if icon_color column exists in urls table
        $column_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM $urls_table LIKE %s", 'icon_color' ) );
        if ( empty( $column_exists ) ) {
            $wpdb->query( "ALTER TABLE $urls_table ADD COLUMN icon_color varchar(7) DEFAULT '' NOT NULL AFTER icon_name" );
        }
    }
}
