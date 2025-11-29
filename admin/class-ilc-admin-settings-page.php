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
