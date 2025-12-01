<?php
/**
 * ILC_Gap_Finder - Internal Link Gap Finder service.
 *
 * This service identifies pages that should link to a target URL based on
 * keyword occurrences in their content but currently do not link to it.
 *
 * @package Internal_Link_Clusters
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ILC_Gap_Finder {

    /**
     * Batch size for processing posts.
     *
     * @var int
     */
    const BATCH_SIZE = 50;

    /**
     * Run gap scan for all clusters.
     *
     * @param bool $clear_new Whether to clear existing 'new' suggestions before scanning.
     * @return array Stats about the scan.
     */
    public static function scan_all_clusters( $clear_new = true ) {
        // Clear existing new suggestions if requested
        if ( $clear_new ) {
            ILC_Gap_Model::clear_new_suggestions();
        }

        $clusters = ILC_Cluster_Model::get_all_clusters();
        $stats    = array(
            'clusters_scanned'    => 0,
            'urls_processed'      => 0,
            'suggestions_created' => 0,
        );

        foreach ( $clusters as $cluster ) {
            if ( empty( $cluster->is_active ) ) {
                continue;
            }

            $result = self::scan_cluster( $cluster->id, false ); // Don't clear again per cluster

            $stats['clusters_scanned']++;
            $stats['urls_processed']      += $result['urls_processed'];
            $stats['suggestions_created'] += $result['suggestions_created'];
        }

        return $stats;
    }

    /**
     * Run gap scan for a single cluster.
     *
     * @param int  $cluster_id Cluster ID.
     * @param bool $clear_existing Whether to clear existing suggestions for this cluster.
     * @return array Stats about the scan.
     */
    public static function scan_cluster( $cluster_id, $clear_existing = true ) {
        if ( $clear_existing ) {
            ILC_Gap_Model::clear_for_cluster( $cluster_id );
        }

        $urls  = ILC_Cluster_Model::get_cluster_urls( $cluster_id );
        $stats = array(
            'urls_processed'      => 0,
            'suggestions_created' => 0,
        );

        foreach ( $urls as $url_row ) {
            $result = self::scan_for_url( $url_row, $cluster_id );

            $stats['urls_processed']++;
            $stats['suggestions_created'] += $result['suggestions_created'];
        }

        return $stats;
    }

    /**
     * Scan all posts/pages for link opportunities to a specific URL.
     *
     * @param object $url_row    URL row from ilc_cluster_urls.
     * @param int    $cluster_id Cluster ID.
     * @return array Stats about the scan.
     */
    public static function scan_for_url( $url_row, $cluster_id ) {
        $stats = array(
            'suggestions_created' => 0,
        );

        // Extract keyword for this URL
        $keyword = self::extract_keyword( $url_row );

        if ( empty( $keyword ) ) {
            return $stats;
        }

        $target_url = $url_row->url;

        // Get settings for post types and search options
        $settings   = self::get_gap_settings();
        $post_types = $settings['gap_post_types'];
        $search_title = $settings['gap_search_title'];

        // Get all candidate posts in batches
        $offset = 0;
        $has_more = true;

        while ( $has_more ) {
            $posts = self::get_candidate_posts( $post_types, self::BATCH_SIZE, $offset );

            if ( empty( $posts ) ) {
                $has_more = false;
                break;
            }

            foreach ( $posts as $post ) {
                // Skip if this is the target URL's own post
                if ( ! empty( $url_row->post_id ) && (int) $url_row->post_id === (int) $post->ID ) {
                    continue;
                }

                // Check if suggestion already exists
                if ( ILC_Gap_Model::suggestion_exists( $target_url, $post->ID, $keyword ) ) {
                    continue;
                }

                // Check if the post already links to the target URL
                if ( self::post_links_to_url( $post->post_content, $target_url ) ) {
                    continue;
                }

                // Calculate confidence and check for keyword match
                $confidence = self::calculate_confidence( $post, $keyword, $search_title );

                if ( $confidence > 0 ) {
                    // Create suggestion
                    $suggestion_id = ILC_Gap_Model::create_suggestion( array(
                        'cluster_id'      => $cluster_id,
                        'target_url'      => $target_url,
                        'source_post_id'  => $post->ID,
                        'matched_keyword' => $keyword,
                        'confidence'      => $confidence,
                        'status'          => 'new',
                    ) );

                    if ( $suggestion_id ) {
                        $stats['suggestions_created']++;
                    }
                }
            }

            $offset += self::BATCH_SIZE;

            // Safety check: stop if we've processed too many posts
            if ( $offset > 10000 ) {
                break;
            }
        }

        return $stats;
    }

    /**
     * Extract keyword phrase from a cluster URL row.
     *
     * Priority:
     * 1. Custom anchor_text
     * 2. Page title (if post_id set)
     * 3. URL slug (last path segment)
     *
     * @param object $url_row URL row from ilc_cluster_urls.
     * @return string Extracted keyword (normalized).
     */
    public static function extract_keyword( $url_row ) {
        // 1. Use anchor_text if available
        if ( ! empty( $url_row->anchor_text ) ) {
            return self::normalize_keyword( $url_row->anchor_text );
        }

        // 2. Use page title if post_id is set
        if ( ! empty( $url_row->post_id ) ) {
            $title = get_the_title( $url_row->post_id );
            if ( ! empty( $title ) ) {
                return self::normalize_keyword( $title );
            }
        }

        // 3. Extract from URL slug
        return self::extract_keyword_from_url( $url_row->url );
    }

    /**
     * Extract a keyword phrase from a URL's slug.
     *
     * Example: /polished-concrete-dacula/ → "polished concrete dacula"
     *
     * @param string $url Full URL.
     * @return string Extracted keyword.
     */
    public static function extract_keyword_from_url( $url ) {
        $parsed = wp_parse_url( $url );

        if ( empty( $parsed['path'] ) ) {
            return '';
        }

        $path = trim( $parsed['path'], '/' );

        // Get the last segment (in case of nested paths)
        $segments = explode( '/', $path );
        $slug     = end( $segments );

        if ( empty( $slug ) ) {
            return '';
        }

        // Convert slug to readable phrase
        // Replace hyphens and underscores with spaces
        $keyword = str_replace( array( '-', '_' ), ' ', $slug );

        return self::normalize_keyword( $keyword );
    }

    /**
     * Normalize a keyword phrase.
     *
     * @param string $keyword Raw keyword.
     * @return string Normalized keyword (lowercase, trimmed, single spaces).
     */
    public static function normalize_keyword( $keyword ) {
        // Convert to lowercase
        $keyword = strtolower( $keyword );

        // Remove extra whitespace
        $keyword = preg_replace( '/\s+/', ' ', $keyword );

        // Trim
        $keyword = trim( $keyword );

        return $keyword;
    }

    /**
     * Get candidate posts for scanning.
     *
     * @param array $post_types Post types to include.
     * @param int   $limit      Number of posts per batch.
     * @param int   $offset     Offset for pagination.
     * @return array Array of WP_Post objects.
     */
    private static function get_candidate_posts( $post_types, $limit, $offset ) {
        $args = array(
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'offset'         => $offset,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'no_found_rows'  => true, // Performance optimization
        );

        return get_posts( $args );
    }

    /**
     * Check if a post's content already links to a target URL.
     *
     * @param string $content    Post content.
     * @param string $target_url Target URL.
     * @return bool True if already links, false otherwise.
     */
    public static function post_links_to_url( $content, $target_url ) {
        // Normalize the target URL for comparison
        $parsed = wp_parse_url( $target_url );
        $path   = isset( $parsed['path'] ) ? trim( $parsed['path'], '/' ) : '';

        // Check for the full URL
        if ( stripos( $content, $target_url ) !== false ) {
            return true;
        }

        // Check for the path (handles relative URLs and different protocols)
        if ( ! empty( $path ) && stripos( $content, $path ) !== false ) {
            // Verify it's actually in an href attribute
            if ( preg_match( '/href\s*=\s*["\'][^"\']*' . preg_quote( $path, '/' ) . '[^"\']*["\']/', $content ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate confidence score for a keyword match.
     *
     * Scoring:
     * - Base: +1 for each occurrence in content
     * - Bonus: +5 if found in title
     * - Maximum: 10
     *
     * @param WP_Post $post         Post object.
     * @param string  $keyword      Keyword to search for.
     * @param bool    $search_title Whether to also search in title.
     * @return float Confidence score (0 if no match).
     */
    public static function calculate_confidence( $post, $keyword, $search_title = true ) {
        $confidence = 0;

        // Search in content (case-insensitive)
        $content_lower = strtolower( $post->post_content );
        $matches       = substr_count( $content_lower, $keyword );

        if ( $matches > 0 ) {
            $confidence += min( $matches, 5 ); // Cap at 5 points from content
        }

        // Search in title
        if ( $search_title ) {
            $title_lower = strtolower( $post->post_title );
            if ( strpos( $title_lower, $keyword ) !== false ) {
                $confidence += 5; // Bonus for title match
            }
        }

        return min( $confidence, 10 ); // Cap at 10
    }

    /**
     * Get gap finder settings.
     *
     * @return array Settings array.
     */
    private static function get_gap_settings() {
        $settings = ILC_Settings::get_settings();

        // Post types to scan
        $post_types_str = isset( $settings['gap_post_types'] ) ? $settings['gap_post_types'] : 'page,post';
        $post_types     = array_filter( array_map( 'trim', explode( ',', $post_types_str ) ) );

        if ( empty( $post_types ) ) {
            $post_types = array( 'page', 'post' );
        }

        return array(
            'gap_post_types'  => $post_types,
            'gap_search_title' => isset( $settings['gap_search_title'] ) ? (bool) $settings['gap_search_title'] : true,
        );
    }

    /**
     * Get statistics about current suggestions.
     *
     * @return array Stats array.
     */
    public static function get_stats() {
        return array(
            'total'     => ILC_Gap_Model::count_suggestions(),
            'new'       => ILC_Gap_Model::count_suggestions( array( 'status' => 'new' ) ),
            'accepted'  => ILC_Gap_Model::count_suggestions( array( 'status' => 'accepted' ) ),
            'dismissed' => ILC_Gap_Model::count_suggestions( array( 'status' => 'dismissed' ) ),
        );
    }
}

