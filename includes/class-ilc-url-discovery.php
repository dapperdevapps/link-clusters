<?php
/**
 * ILC_URL_Discovery - Discover all public URLs on the WordPress site.
 *
 * This class provides methods to retrieve all public URLs from the site's
 * posts, pages, and custom post types. It's designed to be reusable by:
 * - The Cluster Generation admin UI
 * - Future cron jobs for automated cluster suggestions
 * - Future save_post hooks for adding URLs to existing clusters
 *
 * @package Internal_Link_Clusters
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ILC_URL_Discovery {

    /**
     * Default post types to exclude from discovery.
     *
     * @var array
     */
    private static $default_excluded_types = array( 'attachment', 'revision', 'nav_menu_item' );

    /**
     * Get all public URLs for this WordPress site.
     *
     * @param array|null $post_types Specific post types to include, or null for all public.
     * @param array      $args       Optional arguments for filtering.
     *                               - 'exclude_types' => array of post types to exclude
     *                               - 'exclude_patterns' => array of URL substrings to exclude (e.g., '/cart/', '/account/')
     *                               - 'limit' => max number of URLs to return (0 = no limit)
     * @return string[] Array of absolute URL strings.
     */
    public static function get_all_site_urls( $post_types = null, $args = array() ) {
        $defaults = array(
            'exclude_types'    => self::$default_excluded_types,
            'exclude_patterns' => array(),
            'limit'            => 0,
        );

        $args = wp_parse_args( $args, $defaults );

        // Determine which post types to query
        if ( is_null( $post_types ) ) {
            $post_types = self::get_public_post_types( $args['exclude_types'] );
        }

        if ( empty( $post_types ) ) {
            return array();
        }

        // Query posts
        $query_args = array(
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        );

        $post_ids = get_posts( $query_args );

        if ( empty( $post_ids ) ) {
            return array();
        }

        // Build URLs
        $urls = array();
        foreach ( $post_ids as $post_id ) {
            $url = get_permalink( $post_id );

            if ( empty( $url ) ) {
                continue;
            }

            // Apply exclusion patterns
            if ( ! empty( $args['exclude_patterns'] ) && self::url_matches_patterns( $url, $args['exclude_patterns'] ) ) {
                continue;
            }

            $urls[] = $url;

            // Check limit
            if ( $args['limit'] > 0 && count( $urls ) >= $args['limit'] ) {
                break;
            }
        }

        // Deduplicate (in case of any edge cases)
        $urls = array_unique( $urls );

        return array_values( $urls );
    }

    /**
     * Get all public post types, excluding specified types.
     *
     * @param array $exclude Post types to exclude.
     * @return array Array of post type names.
     */
    public static function get_public_post_types( $exclude = array() ) {
        $post_types = get_post_types( array( 'public' => true ), 'names' );

        // Apply default exclusions
        $exclude = array_merge( self::$default_excluded_types, $exclude );

        foreach ( $exclude as $type ) {
            unset( $post_types[ $type ] );
        }

        return array_values( $post_types );
    }

    /**
     * Check if a URL matches any of the exclusion patterns.
     *
     * @param string $url      URL to check.
     * @param array  $patterns Array of patterns (substrings) to match against.
     * @return bool True if URL matches any pattern, false otherwise.
     */
    private static function url_matches_patterns( $url, $patterns ) {
        foreach ( $patterns as $pattern ) {
            if ( strpos( $url, $pattern ) !== false ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get URL for a single post by ID.
     *
     * Utility method for future use (e.g., save_post hooks).
     *
     * @param int $post_id Post ID.
     * @return string|null URL string or null if not public/published.
     */
    public static function get_url_for_post( $post_id ) {
        $post = get_post( $post_id );

        if ( ! $post ) {
            return null;
        }

        // Check if post type is public
        $post_type_obj = get_post_type_object( $post->post_type );
        if ( ! $post_type_obj || ! $post_type_obj->public ) {
            return null;
        }

        // Check if published
        if ( $post->post_status !== 'publish' ) {
            return null;
        }

        // Exclude attachments by default
        if ( in_array( $post->post_type, self::$default_excluded_types, true ) ) {
            return null;
        }

        return get_permalink( $post_id );
    }

    /**
     * Get counts of posts by post type.
     *
     * Useful for displaying to users which post types will be scanned.
     *
     * @param array|null $post_types Specific post types, or null for all public.
     * @return array Associative array of post_type => count.
     */
    public static function get_post_counts_by_type( $post_types = null ) {
        if ( is_null( $post_types ) ) {
            $post_types = self::get_public_post_types();
        }

        $counts = array();

        foreach ( $post_types as $post_type ) {
            $count_obj = wp_count_posts( $post_type );
            $counts[ $post_type ] = isset( $count_obj->publish ) ? (int) $count_obj->publish : 0;
        }

        return $counts;
    }

    /**
     * Get total count of published posts across specified post types.
     *
     * @param array|null $post_types Specific post types, or null for all public.
     * @return int Total count.
     */
    public static function get_total_post_count( $post_types = null ) {
        $counts = self::get_post_counts_by_type( $post_types );
        return array_sum( $counts );
    }

    /**
     * Get post type labels for display.
     *
     * @param array|null $post_types Specific post types, or null for all public.
     * @return array Associative array of post_type => label.
     */
    public static function get_post_type_labels( $post_types = null ) {
        if ( is_null( $post_types ) ) {
            $post_types = self::get_public_post_types();
        }

        $labels = array();

        foreach ( $post_types as $post_type ) {
            $type_obj = get_post_type_object( $post_type );
            $labels[ $post_type ] = $type_obj ? $type_obj->labels->name : ucfirst( $post_type );
        }

        return $labels;
    }

    /**
     * Get a summary of what will be scanned.
     *
     * @param array|null $post_types Specific post types, or null for all public.
     * @return array Summary information.
     */
    public static function get_scan_summary( $post_types = null ) {
        $types  = is_null( $post_types ) ? self::get_public_post_types() : $post_types;
        $counts = self::get_post_counts_by_type( $types );
        $labels = self::get_post_type_labels( $types );

        $summary_items = array();
        foreach ( $types as $type ) {
            $count = isset( $counts[ $type ] ) ? $counts[ $type ] : 0;
            $label = isset( $labels[ $type ] ) ? $labels[ $type ] : $type;
            $summary_items[ $type ] = array(
                'label' => $label,
                'count' => $count,
            );
        }

        return array(
            'post_types'  => $summary_items,
            'total_count' => array_sum( $counts ),
        );
    }

    /**
     * Get all public URLs with titles for AI processing.
     *
     * Returns an array of items with URL, post_id, and title for each post.
     * This format is designed for AI cluster generation.
     *
     * @param array|null $post_types Specific post types to include, or null for all public.
     * @param array      $args       Optional arguments for filtering.
     *                               - 'exclude_types' => array of post types to exclude
     *                               - 'exclude_patterns' => array of URL substrings to exclude
     *                               - 'limit' => max number of items to return (0 = no limit)
     * @return array[] Array of [ 'url' => string, 'post_id' => int, 'title' => string ]
     */
    public static function get_all_site_urls_with_titles( $post_types = null, $args = array() ) {
        $defaults = array(
            'exclude_types'    => self::$default_excluded_types,
            'exclude_patterns' => array(),
            'limit'            => 0,
        );

        $args = wp_parse_args( $args, $defaults );

        // Determine which post types to query
        if ( is_null( $post_types ) ) {
            $post_types = self::get_public_post_types( $args['exclude_types'] );
        }

        if ( empty( $post_types ) ) {
            return array();
        }

        // Query posts
        $query_args = array(
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        );

        $post_ids = get_posts( $query_args );

        if ( empty( $post_ids ) ) {
            return array();
        }

        // Build URL items with titles
        $items = array();
        foreach ( $post_ids as $post_id ) {
            $url = get_permalink( $post_id );

            if ( empty( $url ) ) {
                continue;
            }

            // Apply exclusion patterns
            if ( ! empty( $args['exclude_patterns'] ) && self::url_matches_patterns( $url, $args['exclude_patterns'] ) ) {
                continue;
            }

            $items[] = array(
                'url'     => $url,
                'post_id' => $post_id,
                'title'   => get_the_title( $post_id ),
            );

            // Check limit
            if ( $args['limit'] > 0 && count( $items ) >= $args['limit'] ) {
                break;
            }
        }

        return $items;
    }
}

