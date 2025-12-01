<?php
/**
 * ILC_Cluster_Generation - Shared helper for cluster generation from URLs.
 *
 * This class provides the core logic for parsing URL inputs (XML sitemaps,
 * URL lists, JSON) and generating suggested cluster structures based on
 * URL path analysis. It's industry-agnostic and works purely with URL structure.
 *
 * @package Internal_Link_Clusters
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ILC_Cluster_Generation {

    /**
     * Parse raw input (XML, newline URLs, or JSON) into a flat list of URL strings.
     *
     * Supports three input formats:
     * - Sitemap XML (detected by <urlset or <loc>)
     * - Plain URL list (newline-separated)
     * - JSON (array of strings or objects with 'url' field)
     *
     * @param string $raw_input The raw input from user.
     * @return string[] Deduplicated array of URL strings.
     */
    public static function parse_input_to_urls( $raw_input ) {
        $raw_input = trim( $raw_input );

        if ( empty( $raw_input ) ) {
            return array();
        }

        $urls = array();

        // Try to detect and parse XML sitemap
        if ( self::is_xml_sitemap( $raw_input ) ) {
            $urls = self::parse_xml_sitemap( $raw_input );
        }
        // Try to detect and parse JSON
        elseif ( self::is_json( $raw_input ) ) {
            $urls = self::parse_json_input( $raw_input );
        }
        // Default: treat as newline-separated URL list
        else {
            $urls = self::parse_url_list( $raw_input );
        }

        // Deduplicate and filter empty values
        $urls = array_unique( array_filter( array_map( 'trim', $urls ) ) );

        // Re-index array
        return array_values( $urls );
    }

    /**
     * Check if input looks like XML sitemap.
     *
     * @param string $input Raw input string.
     * @return bool True if appears to be XML sitemap.
     */
    private static function is_xml_sitemap( $input ) {
        // Look for common sitemap XML markers
        return (
            strpos( $input, '<urlset' ) !== false ||
            strpos( $input, '<loc>' ) !== false ||
            strpos( $input, '<?xml' ) !== false
        );
    }

    /**
     * Check if input looks like JSON.
     *
     * @param string $input Raw input string.
     * @return bool True if appears to be JSON.
     */
    private static function is_json( $input ) {
        $first_char = substr( ltrim( $input ), 0, 1 );
        return $first_char === '[' || $first_char === '{';
    }

    /**
     * Parse XML sitemap and extract URLs.
     *
     * @param string $xml_string XML content.
     * @return string[] Array of URLs.
     */
    private static function parse_xml_sitemap( $xml_string ) {
        $urls = array();

        // Suppress XML errors
        libxml_use_internal_errors( true );

        $xml = simplexml_load_string( $xml_string );

        if ( $xml === false ) {
            // Try to fix common issues (missing namespace)
            $xml_string = preg_replace( '/xmlns="[^"]+"/', '', $xml_string );
            $xml = simplexml_load_string( $xml_string );
        }

        if ( $xml === false ) {
            libxml_clear_errors();
            return $urls;
        }

        // Handle standard sitemap format
        if ( isset( $xml->url ) ) {
            foreach ( $xml->url as $url_entry ) {
                if ( isset( $url_entry->loc ) ) {
                    $urls[] = (string) $url_entry->loc;
                }
            }
        }

        // Handle sitemap index format
        if ( isset( $xml->sitemap ) ) {
            foreach ( $xml->sitemap as $sitemap_entry ) {
                if ( isset( $sitemap_entry->loc ) ) {
                    $urls[] = (string) $sitemap_entry->loc;
                }
            }
        }

        // Fallback: find all <loc> elements regardless of structure
        if ( empty( $urls ) ) {
            // Use regex as fallback
            preg_match_all( '/<loc>([^<]+)<\/loc>/i', $xml_string, $matches );
            if ( ! empty( $matches[1] ) ) {
                $urls = $matches[1];
            }
        }

        libxml_clear_errors();

        return $urls;
    }

    /**
     * Parse JSON input and extract URLs.
     *
     * Supports:
     * - Flat array of URL strings: ["https://...", "https://..."]
     * - Array of objects with 'url' field: [{"url": "https://..."}, ...]
     *
     * @param string $json_string JSON content.
     * @return string[] Array of URLs.
     */
    private static function parse_json_input( $json_string ) {
        $urls = array();

        $data = json_decode( $json_string, true );

        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) {
            return $urls;
        }

        foreach ( $data as $item ) {
            if ( is_string( $item ) && filter_var( $item, FILTER_VALIDATE_URL ) ) {
                // Flat array of URL strings
                $urls[] = $item;
            } elseif ( is_array( $item ) && isset( $item['url'] ) && is_string( $item['url'] ) ) {
                // Object with 'url' field
                $urls[] = $item['url'];
            }
        }

        return $urls;
    }

    /**
     * Parse newline-separated URL list.
     *
     * @param string $input Raw input string.
     * @return string[] Array of URLs.
     */
    private static function parse_url_list( $input ) {
        // Split on newlines (handle various line ending formats)
        $lines = preg_split( '/[\r\n]+/', $input );

        $urls = array();

        foreach ( $lines as $line ) {
            $line = trim( $line );

            // Skip empty lines and comments
            if ( empty( $line ) || strpos( $line, '#' ) === 0 ) {
                continue;
            }

            // Basic URL validation
            if ( filter_var( $line, FILTER_VALIDATE_URL ) || strpos( $line, '/' ) === 0 ) {
                $urls[] = $line;
            }
        }

        return $urls;
    }

    /**
     * Given a list of URL strings, return a suggested cluster structure.
     *
     * Uses a two-pass, industry-agnostic heuristic:
     * - Pass 1: Collect base keys from multi-segment paths
     * - Pass 2: Assign cluster key per URL based on path structure
     *
     * @param string[] $urls Array of URL strings.
     * @param int      $min_urls Minimum URLs required per cluster (default: 2).
     * @return array Structured clusters array.
     *
     * Example return structure:
     * [
     *   'epoxy-flooring' => [
     *     'label' => 'Epoxy Flooring',
     *     'slug'  => 'epoxy-flooring',
     *     'urls'  => [ 'https://example.com/epoxy-flooring/epoxy-flooring-auburn-ga/', ... ],
     *   ],
     *   ...
     * ]
     */
    public static function generate_clusters_from_urls( $urls, $min_urls = 2 ) {
        if ( empty( $urls ) || ! is_array( $urls ) ) {
            return array();
        }

        // Pass 1: Collect base keys from multi-segment paths
        $base_keys = self::collect_base_keys( $urls );

        // Pass 2: Assign cluster key per URL
        $clusters = self::assign_urls_to_clusters( $urls, $base_keys );

        // Filter out clusters with fewer than minimum URLs
        $final = array();
        foreach ( $clusters as $key => $cluster ) {
            if ( ! empty( $cluster['urls'] ) && count( $cluster['urls'] ) >= $min_urls ) {
                $final[ $key ] = $cluster;
            }
        }

        // Sort clusters by URL count (descending)
        uasort( $final, function( $a, $b ) {
            return count( $b['urls'] ) - count( $a['urls'] );
        } );

        return $final;
    }

    /**
     * Pass 1: Collect base keys from multi-segment paths.
     *
     * Discovers generic "service-like" bases such as epoxy-flooring,
     * polished-concrete, etc., without any niche-specific logic.
     *
     * @param string[] $urls Array of URLs.
     * @return string[] Array of base keys.
     */
    private static function collect_base_keys( $urls ) {
        $base_keys = array();

        foreach ( $urls as $url ) {
            $parsed = wp_parse_url( $url );
            $path   = isset( $parsed['path'] ) ? trim( $parsed['path'], '/' ) : '';

            if ( $path === '' ) {
                continue;
            }

            $segments = explode( '/', $path );

            // Only consider multi-segment paths for base key discovery
            if ( count( $segments ) >= 2 ) {
                $base_keys[ $segments[0] ] = true;
            }
        }

        return array_keys( $base_keys );
    }

    /**
     * Pass 2: Assign each URL to a cluster.
     *
     * @param string[] $urls      Array of URLs.
     * @param string[] $base_keys Array of discovered base keys.
     * @return array Clusters array with urls assigned.
     */
    private static function assign_urls_to_clusters( $urls, $base_keys ) {
        $clusters = array();

        foreach ( $urls as $url ) {
            $parsed   = wp_parse_url( $url );
            $path     = isset( $parsed['path'] ) ? trim( $parsed['path'], '/' ) : '';
            $segments = $path === '' ? array() : explode( '/', $path );

            $cluster_key = null;

            if ( count( $segments ) >= 2 ) {
                // Multi-level path, e.g. /epoxy-flooring/epoxy-flooring-auburn-ga/
                // Use the first segment as cluster key
                $cluster_key = $segments[0];

            } elseif ( count( $segments ) === 1 ) {
                // Single-slug URL, e.g. /epoxy-flooring-auburn-ga/
                $slug = $segments[0];

                // Try to match against discovered base keys
                $matched_base = null;
                foreach ( $base_keys as $base ) {
                    // Check if slug starts with base followed by hyphen
                    // e.g. "epoxy-flooring-auburn-ga" starts with "epoxy-flooring-"
                    if ( $slug !== $base && strpos( $slug, $base . '-' ) === 0 ) {
                        $matched_base = $base;
                        break;
                    }
                }

                if ( $matched_base ) {
                    $cluster_key = $matched_base;
                } else {
                    // Fallback: use the slug itself as cluster key
                    $cluster_key = $slug;
                }
            }

            // Skip URLs that couldn't be assigned
            if ( ! $cluster_key ) {
                continue;
            }

            // Initialize cluster if it doesn't exist
            if ( ! isset( $clusters[ $cluster_key ] ) ) {
                $label = self::humanize_slug( $cluster_key );
                $slug  = sanitize_title( $cluster_key );

                $clusters[ $cluster_key ] = array(
                    'label' => $label,
                    'slug'  => $slug,
                    'urls'  => array(),
                );
            }

            // Add URL to cluster
            $clusters[ $cluster_key ]['urls'][] = $url;
        }

        return $clusters;
    }

    /**
     * Convert a slug to a human-readable label.
     *
     * @param string $slug URL slug (e.g., "epoxy-flooring").
     * @return string Human-readable label (e.g., "Epoxy Flooring").
     */
    public static function humanize_slug( $slug ) {
        // Replace hyphens and underscores with spaces
        $label = str_replace( array( '-', '_' ), ' ', $slug );

        // Capitalize each word
        $label = ucwords( $label );

        return $label;
    }

    /**
     * Get statistics about a cluster structure.
     *
     * @param array $clusters Clusters array from generate_clusters_from_urls().
     * @return array Stats array with counts.
     */
    public static function get_cluster_stats( $clusters ) {
        $total_clusters = count( $clusters );
        $total_urls     = 0;
        $max_urls       = 0;
        $min_urls       = PHP_INT_MAX;

        foreach ( $clusters as $cluster ) {
            $url_count = count( $cluster['urls'] );
            $total_urls += $url_count;
            $max_urls = max( $max_urls, $url_count );
            $min_urls = min( $min_urls, $url_count );
        }

        if ( $total_clusters === 0 ) {
            $min_urls = 0;
        }

        return array(
            'total_clusters' => $total_clusters,
            'total_urls'     => $total_urls,
            'max_urls'       => $max_urls,
            'min_urls'       => $min_urls,
            'avg_urls'       => $total_clusters > 0 ? round( $total_urls / $total_clusters, 1 ) : 0,
        );
    }
}

