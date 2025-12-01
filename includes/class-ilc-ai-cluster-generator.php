<?php
/**
 * ILC_AI_Cluster_Generator - AI-powered cluster generation.
 *
 * This class handles communication with AI APIs to generate cluster
 * suggestions from site URLs. It's designed to work with various
 * AI providers (OpenAI, Anthropic, etc.) via configurable endpoints.
 *
 * @package Internal_Link_Clusters
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ILC_AI_Cluster_Generator {

    /**
     * HTTP request timeout in seconds.
     */
    const REQUEST_TIMEOUT = 120;

    /**
     * Check if AI cluster generation is enabled and properly configured.
     *
     * @return bool True if enabled and configured, false otherwise.
     */
    public static function is_enabled() {
        $settings = ILC_Settings::get_settings();

        // Must be explicitly enabled
        if ( empty( $settings['ai_cluster_enabled'] ) ) {
            return false;
        }

        // Must have API key
        if ( empty( $settings['ai_cluster_api_key'] ) ) {
            return false;
        }

        // Must have endpoint
        if ( empty( $settings['ai_cluster_api_endpoint'] ) ) {
            return false;
        }

        return true;
    }

    /**
     * Get the configuration status message.
     *
     * @return array Status with 'configured' bool and 'message' string.
     */
    public static function get_config_status() {
        $settings = ILC_Settings::get_settings();

        if ( empty( $settings['ai_cluster_enabled'] ) ) {
            return array(
                'configured' => false,
                'message'    => __( 'AI cluster generation is disabled. Enable it in Settings.', 'internal-link-clusters' ),
            );
        }

        if ( empty( $settings['ai_cluster_api_endpoint'] ) ) {
            return array(
                'configured' => false,
                'message'    => __( 'AI API endpoint is not configured. Add it in Settings.', 'internal-link-clusters' ),
            );
        }

        if ( empty( $settings['ai_cluster_api_key'] ) ) {
            return array(
                'configured' => false,
                'message'    => __( 'AI API key is not configured. Add it in Settings.', 'internal-link-clusters' ),
            );
        }

        return array(
            'configured' => true,
            'message'    => __( 'AI cluster generation is configured and ready.', 'internal-link-clusters' ),
        );
    }

    /**
     * Suggest clusters from a list of URLs using AI.
     *
     * @param array $url_items Array of items with 'url' and 'title' keys.
     * @return array|WP_Error Array of cluster suggestions or WP_Error on failure.
     *
     * Expected return structure on success:
     * [
     *   [
     *     'name' => 'Epoxy Flooring Services',
     *     'slug' => 'epoxy-flooring-services',
     *     'urls' => [ 'https://site.com/epoxy-flooring/', ... ],
     *   ],
     *   ...
     * ]
     */
    public static function suggest_clusters_from_urls( $url_items ) {
        if ( ! self::is_enabled() ) {
            return new WP_Error( 'ai_not_configured', __( 'AI cluster generation is not configured.', 'internal-link-clusters' ) );
        }

        if ( empty( $url_items ) ) {
            return new WP_Error( 'no_urls', __( 'No URLs provided for cluster generation.', 'internal-link-clusters' ) );
        }

        $settings = ILC_Settings::get_settings();

        // Truncate to max URLs if needed
        $max_urls = absint( $settings['ai_cluster_max_urls'] );
        if ( $max_urls < 1 ) {
            $max_urls = 200;
        }

        if ( count( $url_items ) > $max_urls ) {
            $url_items = array_slice( $url_items, 0, $max_urls );
        }

        // Build the input data for AI
        $input_urls = array();
        $valid_urls = array(); // Track valid URLs for validation later
        foreach ( $url_items as $item ) {
            $url   = isset( $item['url'] ) ? $item['url'] : '';
            $title = isset( $item['title'] ) ? $item['title'] : '';

            if ( empty( $url ) ) {
                continue;
            }

            $input_urls[] = array(
                'url'   => $url,
                'title' => $title,
            );
            $valid_urls[] = $url;
        }

        if ( empty( $input_urls ) ) {
            return new WP_Error( 'no_valid_urls', __( 'No valid URLs to process.', 'internal-link-clusters' ) );
        }

        // Build and send request
        $response = self::send_ai_request( $input_urls, $settings );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        // Validate and filter the response
        $clusters = self::validate_ai_response( $response, $valid_urls );

        if ( is_wp_error( $clusters ) ) {
            return $clusters;
        }

        if ( empty( $clusters ) ) {
            return new WP_Error( 'no_clusters', __( 'AI did not return any valid cluster suggestions.', 'internal-link-clusters' ) );
        }

        return $clusters;
    }

    /**
     * Send request to the AI API.
     *
     * @param array $input_urls Array of URL/title pairs.
     * @param array $settings   Plugin settings.
     * @return array|WP_Error Parsed JSON response or WP_Error.
     */
    private static function send_ai_request( $input_urls, $settings ) {
        $endpoint = $settings['ai_cluster_api_endpoint'];
        $api_key  = $settings['ai_cluster_api_key'];
        $model    = ! empty( $settings['ai_cluster_model'] ) ? $settings['ai_cluster_model'] : 'gpt-4o-mini';

        // Build the prompt
        $system_prompt = self::get_system_prompt();
        $user_content  = wp_json_encode( $input_urls, JSON_UNESCAPED_SLASHES );

        // Build request body (OpenAI-compatible format)
        $body = array(
            'model'    => $model,
            'messages' => array(
                array(
                    'role'    => 'system',
                    'content' => $system_prompt,
                ),
                array(
                    'role'    => 'user',
                    'content' => $user_content,
                ),
            ),
            'temperature' => 0.3, // Lower temperature for more consistent output
        );

        // Send request
        $response = wp_remote_post( $endpoint, array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'body'    => wp_json_encode( $body ),
            'timeout' => self::REQUEST_TIMEOUT,
        ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'api_request_failed',
                sprintf(
                    /* translators: %s: error message */
                    __( 'AI API request failed: %s', 'internal-link-clusters' ),
                    $response->get_error_message()
                )
            );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = wp_remote_retrieve_body( $response );

        if ( $status_code !== 200 ) {
            return new WP_Error(
                'api_error',
                sprintf(
                    /* translators: %1$d: HTTP status code, %2$s: response body */
                    __( 'AI API returned error (HTTP %1$d): %2$s', 'internal-link-clusters' ),
                    $status_code,
                    substr( $body, 0, 500 )
                )
            );
        }

        $data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'json_parse_error', __( 'Failed to parse AI API response as JSON.', 'internal-link-clusters' ) );
        }

        // Extract content from OpenAI-style response
        $content = self::extract_content_from_response( $data );

        if ( is_wp_error( $content ) ) {
            return $content;
        }

        return $content;
    }

    /**
     * Get the system prompt for AI cluster generation.
     *
     * @return string System prompt.
     */
    private static function get_system_prompt() {
        return 'You are helping group website URLs into semantic internal-link clusters for SEO.

Input: a JSON array of pages with "url" and "title" fields.

Output: JSON ONLY, no extra text, no markdown formatting, no code blocks. Return a raw JSON array in this exact format:
[{"name": "...", "slug": "...", "urls": ["...", "..."]}, ...]

Rules:
1. Group URLs that belong to the same topic, service, or category together.
2. Look for patterns in URL paths and page titles to identify clusters.
3. Examples of good clusters: service pages for the same service type, location-based pages for a service, blog posts about related topics.
4. "name" should be human-readable and describe the cluster (e.g., "Epoxy Flooring Services", "Atlanta Locations", "Knee Pain Treatments").
5. "slug" should be URL-safe (lowercase, dash-separated, no special characters).
6. Each "urls" array must contain at least 2 URLs.
7. Do NOT invent URLs. Only include URLs that are in the input.
8. Do NOT include URLs that don\'t fit any meaningful cluster.
9. A URL should only appear in one cluster.

Return ONLY the JSON array, nothing else.';
    }

    /**
     * Extract the cluster data from AI API response.
     *
     * Handles various API response formats (OpenAI, etc.).
     *
     * @param array $data Decoded JSON response.
     * @return array|WP_Error Extracted cluster data or error.
     */
    private static function extract_content_from_response( $data ) {
        // OpenAI format: choices[0].message.content
        if ( isset( $data['choices'][0]['message']['content'] ) ) {
            $content = $data['choices'][0]['message']['content'];
            return self::parse_ai_content( $content );
        }

        // Anthropic format: content[0].text
        if ( isset( $data['content'][0]['text'] ) ) {
            $content = $data['content'][0]['text'];
            return self::parse_ai_content( $content );
        }

        // Direct array response (some APIs)
        if ( is_array( $data ) && isset( $data[0]['name'] ) ) {
            return $data;
        }

        return new WP_Error( 'unknown_response_format', __( 'Could not extract cluster data from AI response.', 'internal-link-clusters' ) );
    }

    /**
     * Parse the AI content string to extract JSON.
     *
     * @param string $content Raw content from AI.
     * @return array|WP_Error Parsed clusters or error.
     */
    private static function parse_ai_content( $content ) {
        // Clean up content - remove markdown code blocks if present
        $content = trim( $content );
        $content = preg_replace( '/^```json\s*/i', '', $content );
        $content = preg_replace( '/^```\s*/i', '', $content );
        $content = preg_replace( '/\s*```$/', '', $content );
        $content = trim( $content );

        // Try to parse as JSON
        $clusters = json_decode( $content, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            // Try to find JSON array in the content
            if ( preg_match( '/\[[\s\S]*\]/', $content, $matches ) ) {
                $clusters = json_decode( $matches[0], true );
            }
        }

        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $clusters ) ) {
            return new WP_Error(
                'invalid_ai_output',
                __( 'AI response was not valid JSON. Please try again.', 'internal-link-clusters' )
            );
        }

        return $clusters;
    }

    /**
     * Validate AI response and filter to only valid URLs.
     *
     * @param array $clusters   Raw clusters from AI.
     * @param array $valid_urls Array of valid URLs that were sent to AI.
     * @return array|WP_Error Validated clusters or error.
     */
    private static function validate_ai_response( $clusters, $valid_urls ) {
        if ( ! is_array( $clusters ) ) {
            return new WP_Error( 'invalid_response', __( 'AI response was not in the expected format.', 'internal-link-clusters' ) );
        }

        $valid_url_set = array_flip( $valid_urls );
        $validated     = array();

        foreach ( $clusters as $cluster ) {
            // Must have name
            if ( empty( $cluster['name'] ) || ! is_string( $cluster['name'] ) ) {
                continue;
            }

            // Must have urls array
            if ( empty( $cluster['urls'] ) || ! is_array( $cluster['urls'] ) ) {
                continue;
            }

            // Filter URLs to only those in our valid set
            $filtered_urls = array();
            foreach ( $cluster['urls'] as $url ) {
                if ( is_string( $url ) && isset( $valid_url_set[ $url ] ) ) {
                    $filtered_urls[] = $url;
                }
            }

            // Must have at least 2 valid URLs
            if ( count( $filtered_urls ) < 2 ) {
                continue;
            }

            // Generate slug if missing
            $slug = ! empty( $cluster['slug'] ) ? sanitize_title( $cluster['slug'] ) : sanitize_title( $cluster['name'] );

            $validated[] = array(
                'name' => sanitize_text_field( $cluster['name'] ),
                'slug' => $slug,
                'urls' => $filtered_urls,
            );
        }

        return $validated;
    }

    /**
     * Get the last error message if any.
     *
     * @param WP_Error $error WP_Error object.
     * @return string Error message.
     */
    public static function get_error_message( $error ) {
        if ( is_wp_error( $error ) ) {
            return $error->get_error_message();
        }
        return __( 'An unknown error occurred.', 'internal-link-clusters' );
    }
}

