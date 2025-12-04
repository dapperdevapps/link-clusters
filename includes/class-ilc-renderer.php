<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ILC_Renderer {

    /**
     * Render auto clusters for the current post/page.
     *
     * Uses ilc_get_current_post_id() for robust post detection across
     * different builders (Elementor, Bridge/Qode, etc.).
     *
     * @return string Rendered cluster HTML, or empty string if no cluster found.
     */
    public static function render_auto_clusters_for_current_post() {
        $current_post_id = function_exists( 'ilc_get_current_post_id' )
            ? ilc_get_current_post_id()
            : get_the_ID();

        $current_url = $current_post_id ? get_permalink( $current_post_id ) : '';

        if ( ! $current_post_id && ! $current_url ) {
            return '';
        }

        $cluster = ILC_Cluster_Model::get_cluster_for_page( $current_post_id, $current_url );

        if ( ! $cluster ) {
            return '';
        }

        // Check if cluster should be hidden on this page
        if ( ILC_Cluster_Model::should_hide_cluster_for_page( $cluster->id, $current_post_id, $current_url ) ) {
            return '';
        }

        return self::render_cluster( $cluster, $current_url, $current_post_id );
    }

    public static function render_cluster( $cluster, $current_url = null, $current_post_id = null ) {
        if ( ! $cluster || empty( $cluster->id ) ) {
            return '';
        }

        $urls = ILC_Cluster_Model::get_cluster_urls( $cluster->id );

        if ( empty( $urls ) ) {
            return '';
        }

        $items = array();

        foreach ( $urls as $row ) {
            if ( $current_post_id && ! empty( $row->post_id ) && (int) $row->post_id === (int) $current_post_id ) {
                continue;
            }

            if ( $current_url && ! empty( $row->url ) && self::normalize_url( $row->url ) === self::normalize_url( $current_url ) ) {
                continue;
            }

            $href = ! empty( $row->url ) ? $row->url : '';

            if ( empty( $href ) && ! empty( $row->post_id ) ) {
                $href = get_permalink( (int) $row->post_id );
            }

            if ( ! $href ) {
                continue;
            }

            $anchor_text = '';

            if ( ! empty( $row->anchor_text ) ) {
                $anchor_text = $row->anchor_text;
            } elseif ( ! empty( $row->post_id ) ) {
                $anchor_text = get_the_title( (int) $row->post_id );
            } else {
                $anchor_text = self::fallback_anchor_from_url( $href );
            }

            if ( ! $anchor_text ) {
                continue;
            }

            $items[] = array(
                'href'        => esc_url( $href ),
                'anchor_text' => esc_html( $anchor_text ),
                'icon_name'  => isset( $row->icon_name ) ? $row->icon_name : '',
                'icon_color' => isset( $row->icon_color ) ? $row->icon_color : '',
                'rel_attribute' => isset( $row->rel_attribute ) ? $row->rel_attribute : '',
                'css_class'  => isset( $row->css_class ) ? $row->css_class : '',
            );
        }

        if ( empty( $items ) ) {
            return '';
        }

        // Get SEO settings
        $settings = class_exists( 'ILC_Settings' ) ? ILC_Settings::get_settings() : array();
        $seo_schema_enabled = ! empty( $settings['seo_schema_enabled'] );
        $seo_add_title_attr = ! empty( $settings['seo_add_title_attr'] );
        $seo_add_aria_label = ! empty( $settings['seo_add_aria_label'] );
        $seo_default_rel    = isset( $settings['seo_default_rel'] ) ? $settings['seo_default_rel'] : '';
        $seo_open_new_tab   = ! empty( $settings['seo_open_new_tab'] );
        $seo_max_links      = ! empty( $settings['seo_max_links'] ) ? (int) $settings['seo_max_links'] : 0;

        // Limit items if max_links is set
        if ( $seo_max_links > 0 && count( $items ) > $seo_max_links ) {
            $items = array_slice( $items, 0, $seo_max_links );
        }

        $heading  = ! empty( $cluster->heading ) ? esc_html( $cluster->heading ) : '';
        $subtitle = ! empty( $cluster->subtitle ) ? esc_html( $cluster->subtitle ) : '';

        // Layout mode: contained or fullwidth.
        $layout_class = '';
        if ( isset( $settings['layout_mode'] ) && $settings['layout_mode'] === 'fullwidth' ) {
            $layout_class = ' rc-cluster--fullwidth';
        }

        // Custom CSS class for cluster
        $cluster_css_class = '';
        if ( isset( $cluster->css_class ) && ! empty( $cluster->css_class ) ) {
            $cluster_css_class = ' ' . esc_attr( $cluster->css_class );
        }

        ob_start();
        ?>
        <section class="rc-cluster rc-cluster-<?php echo esc_attr( $cluster->slug ); ?><?php echo esc_attr( $layout_class ); ?><?php echo $cluster_css_class; ?>">
            <?php if ( $heading ) : ?>
                <h2 class="rc-heading"><?php echo $heading; ?></h2>
            <?php endif; ?>

            <?php if ( $subtitle ) : ?>
                <p class="rc-subtitle"><?php echo $subtitle; ?></p>
            <?php endif; ?>

            <div class="rc-grid">
                <?php foreach ( $items as $index => $item ) : ?>
                    <?php
                    // Build link attributes
                    $link_attrs = array( 'href' => $item['href'], 'class' => 'rc-box' );

                    // Add title attribute (Page Name - Site Title format)
                    if ( $seo_add_title_attr ) {
                        $site_title = get_bloginfo( 'name' );
                        $page_title = $item['anchor_text'];
                        $link_attrs['title'] = $page_title . ( $site_title ? ' - ' . $site_title : '' );
                    }

                    // Add aria-label
                    if ( $seo_add_aria_label ) {
                        $link_attrs['aria-label'] = sprintf( __( 'Visit %s', 'internal-link-clusters' ), $item['anchor_text'] );
                    }

                    // Add rel attribute (per-link override or default)
                    $rel_parts = array();
                    $link_rel = ! empty( $item['rel_attribute'] ) ? $item['rel_attribute'] : $seo_default_rel;
                    if ( $link_rel ) {
                        $rel_parts = array_merge( $rel_parts, explode( ' ', $link_rel ) );
                    }
                    if ( $seo_open_new_tab ) {
                        $rel_parts[] = 'noopener';
                        $rel_parts[] = 'noreferrer';
                    }
                    if ( ! empty( $rel_parts ) ) {
                        $link_attrs['rel'] = implode( ' ', array_unique( $rel_parts ) );
                    }

                    // Add target attribute
                    if ( $seo_open_new_tab ) {
                        $link_attrs['target'] = '_blank';
                    }

                    // Add custom CSS class
                    if ( ! empty( $item['css_class'] ) ) {
                        $link_attrs['class'] = $link_attrs['class'] . ' ' . esc_attr( $item['css_class'] );
                    }

                    // Icon settings
                    $icon_position = isset( $settings['icon_position'] ) ? $settings['icon_position'] : 'left';
                    $icon_color_default = isset( $settings['icon_color_default'] ) ? $settings['icon_color_default'] : '';
                    $icon_name = ! empty( $item['icon_name'] ) ? $item['icon_name'] : '';
                    $icon_color = ! empty( $item['icon_color'] ) ? $item['icon_color'] : $icon_color_default;

                    // Add analytics tracking attributes
                    $link_attrs['data-cluster-link'] = '1';
                    $link_attrs['data-link-text'] = esc_attr( $item['anchor_text'] );
                    ?>
                    <a <?php echo self::build_attributes( $link_attrs ); ?>>
                        <?php
                        // Display icon based on position
                        if ( $icon_name ) {
                            $icon_style = $icon_color ? ' style="color: ' . esc_attr( $icon_color ) . ';"' : '';
                            $icon_class = self::normalize_fa_class( $icon_name );
                            $icon_html = '<i class="' . esc_attr( $icon_class ) . '"' . $icon_style . '></i>';
                            
                            if ( $icon_position === 'above' ) {
                                echo '<span class="rc-icon-wrapper rc-icon-above">' . $icon_html . '</span>';
                                echo '<span class="rc-link-text">' . $item['anchor_text'] . '</span>';
                            } elseif ( $icon_position === 'right' ) {
                                echo '<span class="rc-link-text">' . $item['anchor_text'] . '</span>';
                                echo '<span class="rc-icon-wrapper rc-icon-right">' . $icon_html . '</span>';
                            } else { // left (default)
                                echo '<span class="rc-icon-wrapper rc-icon-left">' . $icon_html . '</span>';
                                echo '<span class="rc-link-text">' . $item['anchor_text'] . '</span>';
                            }
                        } else {
                            echo '<span class="rc-link-text">' . $item['anchor_text'] . '</span>';
                        }
                        ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
        $output = ob_get_clean();

        // Add Schema.org structured data
        if ( $seo_schema_enabled && ! empty( $items ) ) {
            $schema = self::generate_schema_markup( $cluster, $items, $heading );
            $output .= $schema;
        }

        return trim( $output );
    }

    protected static function normalize_url( $url ) {
        $parsed = wp_parse_url( $url );
        $path   = isset( $parsed['path'] ) ? trailingslashit( $parsed['path'] ) : '/';

        return $path;
    }

    protected static function fallback_anchor_from_url( $url ) {
        $parsed = wp_parse_url( $url );
        if ( empty( $parsed['path'] ) ) {
            return '';
        }

        $path = trim( $parsed['path'], '/' );
        if ( '' === $path ) {
            return '';
        }

        $parts = explode( '/', $path );
        $last  = end( $parts );

        $text = ucwords( str_replace( '-', ' ', $last ) );

        return $text;
    }

    /**
     * Normalize Font Awesome class name.
     * Uses FA 4/5 format (fa fa-icon) to match Visual Composer compatibility.
     *
     * @param string $icon_name Icon class name.
     * @return string Normalized icon class.
     */
    protected static function normalize_fa_class( $icon_name ) {
        $icon_name = trim( $icon_name );
        
        // If it already has 'fa fa-' format, return as-is
        if ( strpos( $icon_name, 'fa fa-' ) === 0 ) {
            return $icon_name;
        }
        
        // If it has 'fa-solid', 'fa-regular', or 'fa-brands', convert to 'fa' format
        if ( strpos( $icon_name, 'fa-solid ' ) === 0 ) {
            return str_replace( 'fa-solid ', 'fa ', $icon_name );
        }
        if ( strpos( $icon_name, 'fa-regular ' ) === 0 ) {
            return str_replace( 'fa-regular ', 'fa ', $icon_name );
        }
        if ( strpos( $icon_name, 'fa-brands ' ) === 0 ) {
            return str_replace( 'fa-brands ', 'fa ', $icon_name );
        }
        
        // If it starts with 'fa ' (already correct format)
        if ( strpos( $icon_name, 'fa ' ) === 0 ) {
            return $icon_name;
        }
        
        // If it already starts with 'fa-', add 'fa ' prefix
        if ( strpos( $icon_name, 'fa-' ) === 0 ) {
            return 'fa ' . $icon_name;
        }
        
        // Just the icon name without 'fa-' prefix, add both
        return 'fa fa-' . $icon_name;
    }

    /**
     * Build HTML attributes string from array.
     *
     * @param array $attrs Attributes array.
     * @return string HTML attributes string.
     */
    protected static function build_attributes( $attrs ) {
        $output = array();
        foreach ( $attrs as $key => $value ) {
            $output[] = esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
        }
        return implode( ' ', $output );
    }

    /**
     * Generate Schema.org ItemList structured data.
     *
     * @param object $cluster Cluster object.
     * @param array  $items   Link items array.
     * @param string $heading Cluster heading.
     * @return string JSON-LD script tag.
     */
    protected static function generate_schema_markup( $cluster, $items, $heading ) {
        $schema = array(
            '@context' => 'https://schema.org',
            '@type'    => 'ItemList',
            'name'     => ! empty( $heading ) ? $heading : __( 'Related Links', 'internal-link-clusters' ),
        );

        $list_items = array();
        foreach ( $items as $index => $item ) {
            $list_items[] = array(
                '@type'    => 'ListItem',
                'position' => $index + 1,
                'name'     => $item['anchor_text'],
                'url'      => $item['href'],
            );
        }

        $schema['itemListElement'] = $list_items;
        $schema['numberOfItems']   = count( $list_items );

        $json = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

        return '<script type="application/ld+json">' . $json . '</script>';
    }
}
