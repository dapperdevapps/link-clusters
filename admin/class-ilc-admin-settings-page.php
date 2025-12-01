<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ILC_Admin_Settings_Page {

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( isset( $_POST['ilc_save_settings'] ) && check_admin_referer( 'ilc_save_settings' ) ) {
            ILC_Settings::update_settings( $_POST );
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'internal-link-clusters' ) . '</p></div>';
        }

        $settings = ILC_Settings::get_settings();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Internal Link Clusters Settings', 'internal-link-clusters' ); ?></h1>

            <form method="post">
                <?php wp_nonce_field( 'ilc_save_settings' ); ?>

                <h2><?php esc_html_e( 'Auto-insert', 'internal-link-clusters' ); ?></h2>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Auto-insert cluster grids', 'internal-link-clusters' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="auto_insert_enabled" value="1" <?php checked( $settings['auto_insert_enabled'], 1 ); ?>>
                                    <?php esc_html_e( 'Automatically append [rc_cluster_auto] to the end of content.', 'internal-link-clusters' ); ?>
                                </label>
                                <p class="description"><?php esc_html_e( 'This will render the cluster grid on pages that belong to a cluster.', 'internal-link-clusters' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ilc-post-types"><?php esc_html_e( 'Post types for auto-insert', 'internal-link-clusters' ); ?></label></th>
                            <td>
                                <input type="text" name="auto_insert_post_types" id="ilc-post-types" class="regular-text" value="<?php echo esc_attr( $settings['auto_insert_post_types'] ); ?>">
                                <p class="description"><?php esc_html_e( 'Comma-separated list of post types (e.g., page,post). Leave blank to apply to all singular post types.', 'internal-link-clusters' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Fallback mode (Advanced)', 'internal-link-clusters' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="fallback_mode_enabled" value="1" <?php checked( $settings['fallback_mode_enabled'], 1 ); ?>>
                                    <?php esc_html_e( 'Force clusters in footer for incompatible themes.', 'internal-link-clusters' ); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e( 'Enable this if clusters don\'t appear on your theme. Some page builders (Bridge, Avada, Divi) may bypass the_content filter.', 'internal-link-clusters' ); ?>
                                    <br>
                                    <strong><?php esc_html_e( 'Alternative:', 'internal-link-clusters' ); ?></strong>
                                    <?php esc_html_e( 'Use the [rc_cluster_auto] shortcode in a text/code widget for full control.', 'internal-link-clusters' ); ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <hr />

                <h2><?php esc_html_e( 'Layout', 'internal-link-clusters' ); ?></h2>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Layout mode', 'internal-link-clusters' ); ?></th>
                            <td>
                                <select name="layout_mode">
                                    <option value="contained" <?php selected( $settings['layout_mode'], 'contained' ); ?>><?php esc_html_e( 'Contained (inside content width)', 'internal-link-clusters' ); ?></option>
                                    <option value="fullwidth" <?php selected( $settings['layout_mode'], 'fullwidth' ); ?>><?php esc_html_e( 'Full width (stretch like WPBakery row)', 'internal-link-clusters' ); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e( 'Full width is useful if you want cluster sections to behave like "Stretch row" bands, especially for auto-inserted grids.', 'internal-link-clusters' ); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <hr />

                <h2><?php esc_html_e( 'SEO Settings', 'internal-link-clusters' ); ?></h2>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Enable Schema.org markup', 'internal-link-clusters' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="seo_schema_enabled" value="1" <?php checked( $settings['seo_schema_enabled'], 1 ); ?>>
                                    <?php esc_html_e( 'Add structured data (ItemList) to help search engines understand the link cluster.', 'internal-link-clusters' ); ?>
                                </label>
                                <p class="description"><?php esc_html_e( 'This adds JSON-LD schema markup for better search engine visibility.', 'internal-link-clusters' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Add title attributes', 'internal-link-clusters' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="seo_add_title_attr" value="1" <?php checked( $settings['seo_add_title_attr'], 1 ); ?>>
                                    <?php esc_html_e( 'Add title attributes to links for better SEO and accessibility.', 'internal-link-clusters' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Add aria-label', 'internal-link-clusters' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="seo_add_aria_label" value="1" <?php checked( $settings['seo_add_aria_label'], 1 ); ?>>
                                    <?php esc_html_e( 'Add aria-label attributes for better screen reader accessibility.', 'internal-link-clusters' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="seo_default_rel"><?php esc_html_e( 'Default rel attribute', 'internal-link-clusters' ); ?></label></th>
                            <td>
                                <select name="seo_default_rel" id="seo_default_rel">
                                    <option value="" <?php selected( $settings['seo_default_rel'], '' ); ?>><?php esc_html_e( 'Follow (default)', 'internal-link-clusters' ); ?></option>
                                    <option value="nofollow" <?php selected( $settings['seo_default_rel'], 'nofollow' ); ?>><?php esc_html_e( 'Nofollow', 'internal-link-clusters' ); ?></option>
                                    <option value="nofollow sponsored" <?php selected( $settings['seo_default_rel'], 'nofollow sponsored' ); ?>><?php esc_html_e( 'Nofollow Sponsored', 'internal-link-clusters' ); ?></option>
                                    <option value="nofollow ugc" <?php selected( $settings['seo_default_rel'], 'nofollow ugc' ); ?>><?php esc_html_e( 'Nofollow UGC', 'internal-link-clusters' ); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e( 'Default rel attribute for all links. Can be overridden per link in cluster settings.', 'internal-link-clusters' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Open links in new tab', 'internal-link-clusters' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="seo_open_new_tab" value="1" <?php checked( $settings['seo_open_new_tab'], 1 ); ?>>
                                    <?php esc_html_e( 'Add target="_blank" with rel="noopener noreferrer" to all links.', 'internal-link-clusters' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="seo_max_links"><?php esc_html_e( 'Maximum links per cluster', 'internal-link-clusters' ); ?></label></th>
                            <td>
                                <input type="number" name="seo_max_links" id="seo_max_links" value="<?php echo esc_attr( $settings['seo_max_links'] ); ?>" class="small-text" min="1">
                                <p class="description"><?php esc_html_e( 'Limit the number of links displayed (leave empty for no limit). Helps with SEO by avoiding too many links on one page.', 'internal-link-clusters' ); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <hr />

                <h2><?php esc_html_e( 'Icon Settings', 'internal-link-clusters' ); ?></h2>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Enable Font Awesome', 'internal-link-clusters' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="icon_enable_fontawesome" value="1" <?php checked( $settings['icon_enable_fontawesome'], 1 ); ?>>
                                    <?php esc_html_e( 'Load Font Awesome library (v6.4.0) for icon support.', 'internal-link-clusters' ); ?>
                                </label>
                                <p class="description"><?php esc_html_e( 'Uncheck if your theme already includes Font Awesome.', 'internal-link-clusters' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="icon_position"><?php esc_html_e( 'Default Icon Position', 'internal-link-clusters' ); ?></label></th>
                            <td>
                                <select name="icon_position" id="icon_position">
                                    <option value="left" <?php selected( $settings['icon_position'], 'left' ); ?>><?php esc_html_e( 'Left of text', 'internal-link-clusters' ); ?></option>
                                    <option value="right" <?php selected( $settings['icon_position'], 'right' ); ?>><?php esc_html_e( 'Right of text', 'internal-link-clusters' ); ?></option>
                                    <option value="above" <?php selected( $settings['icon_position'], 'above' ); ?>><?php esc_html_e( 'Above text', 'internal-link-clusters' ); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e( 'Default position for icons. Can be overridden per link.', 'internal-link-clusters' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="icon_color_default"><?php esc_html_e( 'Default Icon Color', 'internal-link-clusters' ); ?></label></th>
                            <td>
                                <input type="text" name="icon_color_default" id="icon_color_default" value="<?php echo esc_attr( $settings['icon_color_default'] ); ?>" class="ilc-color-picker" data-default-color="">
                                <p class="description"><?php esc_html_e( 'Default color for icons. Leave empty to inherit text color. Can be overridden per link.', 'internal-link-clusters' ); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <hr />

                <h2><?php esc_html_e( 'Internal Link Gap Finder', 'internal-link-clusters' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Configure how the Link Suggestions feature scans your content for internal linking opportunities.', 'internal-link-clusters' ); ?></p>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="gap_post_types"><?php esc_html_e( 'Post types to scan', 'internal-link-clusters' ); ?></label></th>
                            <td>
                                <input type="text" name="gap_post_types" id="gap_post_types" class="regular-text" value="<?php echo esc_attr( $settings['gap_post_types'] ); ?>">
                                <p class="description"><?php esc_html_e( 'Comma-separated list of post types to scan for link opportunities (e.g., page,post).', 'internal-link-clusters' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Search in titles', 'internal-link-clusters' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="gap_search_title" value="1" <?php checked( $settings['gap_search_title'], 1 ); ?>>
                                    <?php esc_html_e( 'Also search for keywords in post titles (gives higher confidence score).', 'internal-link-clusters' ); ?>
                                </label>
                                <p class="description"><?php esc_html_e( 'When enabled, keyword matches in titles get a +5 confidence bonus.', 'internal-link-clusters' ); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <hr />

                <h2><?php esc_html_e( 'AI Cluster Generation', 'internal-link-clusters' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Configure AI-powered automatic cluster generation. The AI will analyze your site URLs and suggest meaningful clusters.', 'internal-link-clusters' ); ?></p>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Enable AI cluster generation', 'internal-link-clusters' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="ai_cluster_enabled" value="1" <?php checked( $settings['ai_cluster_enabled'], 1 ); ?>>
                                    <?php esc_html_e( 'Enable AI-powered cluster generation from site URLs.', 'internal-link-clusters' ); ?>
                                </label>
                                <p class="description"><?php esc_html_e( 'When enabled, you can use AI to automatically suggest clusters based on your site content.', 'internal-link-clusters' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai_cluster_api_endpoint"><?php esc_html_e( 'AI API endpoint', 'internal-link-clusters' ); ?></label></th>
                            <td>
                                <input type="url" name="ai_cluster_api_endpoint" id="ai_cluster_api_endpoint" class="regular-text" value="<?php echo esc_attr( $settings['ai_cluster_api_endpoint'] ); ?>" placeholder="https://api.openai.com/v1/chat/completions">
                                <p class="description"><?php esc_html_e( 'The API endpoint for AI requests (e.g., OpenAI, Anthropic, or custom endpoint).', 'internal-link-clusters' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai_cluster_api_key"><?php esc_html_e( 'AI API key', 'internal-link-clusters' ); ?></label></th>
                            <td>
                                <input type="password" name="ai_cluster_api_key" id="ai_cluster_api_key" class="regular-text" value="<?php echo esc_attr( $settings['ai_cluster_api_key'] ); ?>" autocomplete="new-password">
                                <p class="description"><?php esc_html_e( 'Your API key for the AI service. Keep this secret.', 'internal-link-clusters' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai_cluster_model"><?php esc_html_e( 'Model name / ID', 'internal-link-clusters' ); ?></label></th>
                            <td>
                                <input type="text" name="ai_cluster_model" id="ai_cluster_model" class="regular-text" value="<?php echo esc_attr( $settings['ai_cluster_model'] ); ?>" placeholder="gpt-4o-mini">
                                <p class="description"><?php esc_html_e( 'The model identifier to use (e.g., gpt-4o-mini, claude-3-haiku, etc.).', 'internal-link-clusters' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai_cluster_max_urls"><?php esc_html_e( 'Max URLs per AI call', 'internal-link-clusters' ); ?></label></th>
                            <td>
                                <input type="number" name="ai_cluster_max_urls" id="ai_cluster_max_urls" class="small-text" value="<?php echo esc_attr( $settings['ai_cluster_max_urls'] ); ?>" min="10" max="1000">
                                <p class="description"><?php esc_html_e( 'Maximum number of URLs to send to the AI in a single request (default: 200). Lower values reduce API costs.', 'internal-link-clusters' ); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <hr />

                <h2><?php esc_html_e( 'Styling', 'internal-link-clusters' ); ?></h2>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="style_bg"><?php esc_html_e( 'Cluster background', 'internal-link-clusters' ); ?></label></th>
                            <td><input type="text" name="style_bg" id="style_bg" value="<?php echo esc_attr( $settings['style_bg'] ); ?>" class="ilc-color-picker" data-default-color="#f7f7f7"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="style_heading"><?php esc_html_e( 'Heading color', 'internal-link-clusters' ); ?></label></th>
                            <td><input type="text" name="style_heading" id="style_heading" value="<?php echo esc_attr( $settings['style_heading'] ); ?>" class="ilc-color-picker" data-default-color="#000000"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="style_subtitle"><?php esc_html_e( 'Subtitle color', 'internal-link-clusters' ); ?></label></th>
                            <td><input type="text" name="style_subtitle" id="style_subtitle" value="<?php echo esc_attr( $settings['style_subtitle'] ); ?>" class="ilc-color-picker" data-default-color="#555555"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="style_box_bg"><?php esc_html_e( 'Box background', 'internal-link-clusters' ); ?></label></th>
                            <td><input type="text" name="style_box_bg" id="style_box_bg" value="<?php echo esc_attr( $settings['style_box_bg'] ); ?>" class="ilc-color-picker" data-default-color="#ffffff"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="style_box_text"><?php esc_html_e( 'Box text color', 'internal-link-clusters' ); ?></label></th>
                            <td><input type="text" name="style_box_text" id="style_box_text" value="<?php echo esc_attr( $settings['style_box_text'] ); ?>" class="ilc-color-picker" data-default-color="#222222"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="style_box_hover_bg"><?php esc_html_e( 'Box hover background', 'internal-link-clusters' ); ?></label></th>
                            <td><input type="text" name="style_box_hover_bg" id="style_box_hover_bg" value="<?php echo esc_attr( $settings['style_box_hover_bg'] ); ?>" class="ilc-color-picker" data-default-color="#f0f0f0"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="style_box_border"><?php esc_html_e( 'Box border color', 'internal-link-clusters' ); ?></label></th>
                            <td><input type="text" name="style_box_border" id="style_box_border" value="<?php echo esc_attr( $settings['style_box_border'] ); ?>" class="ilc-color-picker" data-default-color="#e0e0e0"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="style_radius"><?php esc_html_e( 'Border radius (px)', 'internal-link-clusters' ); ?></label></th>
                            <td><input type="number" name="style_radius" id="style_radius" value="<?php echo esc_attr( $settings['style_radius'] ); ?>" class="small-text"></td>
                        </tr>
                    </tbody>
                </table>

                <p class="submit">
                    <button type="submit" name="ilc_save_settings" class="button button-primary"><?php esc_html_e( 'Save Settings', 'internal-link-clusters' ); ?></button>
                </p>
            </form>
        </div>
        <?php
    }
}
