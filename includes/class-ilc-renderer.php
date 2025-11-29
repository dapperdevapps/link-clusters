<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ILC_Renderer {

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
            );
        }

        if ( empty( $items ) ) {
            return '';
        }

        $heading  = ! empty( $cluster->heading ) ? esc_html( $cluster->heading ) : '';
        $subtitle = ! empty( $cluster->subtitle ) ? esc_html( $cluster->subtitle ) : '';

        // Layout mode: contained or fullwidth.
        $layout_class = '';
        if ( class_exists( 'ILC_Settings' ) ) {
            $settings = ILC_Settings::get_settings();
            if ( isset( $settings['layout_mode'] ) && $settings['layout_mode'] === 'fullwidth' ) {
                $layout_class = ' rc-cluster--fullwidth';
            }
        }

        ob_start();
        ?>
        <section class="rc-cluster rc-cluster-<?php echo esc_attr( $cluster->slug ); ?><?php echo esc_attr( $layout_class ); ?>">
            <?php if ( $heading ) : ?>
                <h2 class="rc-heading"><?php echo $heading; ?></h2>
            <?php endif; ?>

            <?php if ( $subtitle ) : ?>
                <p class="rc-subtitle"><?php echo $subtitle; ?></p>
            <?php endif; ?>

            <div class="rc-grid">
                <?php foreach ( $items as $item ) : ?>
                    <a href="<?php echo $item['href']; ?>" class="rc-box">
                        <?php echo $item['anchor_text']; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php

        return trim( ob_get_clean() );
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
}
