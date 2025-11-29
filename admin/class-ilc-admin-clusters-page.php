<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ILC_Admin_Clusters_Page {

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
        $id     = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;

        if ( $action === 'delete' && $id && check_admin_referer( 'ilc_delete_cluster_' . $id ) ) {
            ILC_Cluster_Model::delete_cluster( $id );
            echo '<div class="notice notice-success"><p>Cluster deleted.</p></div>';
        }

        if ( isset( $_POST['ilc_save_cluster'] ) && check_admin_referer( 'ilc_save_cluster' ) ) {
            self::handle_save_cluster( $id );
        }

        if ( isset( $_POST['ilc_update_urls'] ) && $id && check_admin_referer( 'ilc_update_urls_' . $id ) ) {
            self::handle_update_urls( $id );
        }

        if ( isset( $_POST['ilc_add_url'] ) && $id && check_admin_referer( 'ilc_add_url_' . $id ) ) {
            self::handle_add_url( $id );
        }

        if ( $action === 'edit' ) {
            self::render_edit_screen( $id );
        } elseif ( $action === 'add' ) {
            self::render_edit_screen( 0 );
        } else {
            self::render_list_screen();
        }
    }

    protected static function handle_save_cluster( $id ) {
        $name      = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        $slug      = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
        $heading   = isset( $_POST['heading'] ) ? sanitize_text_field( wp_unslash( $_POST['heading'] ) ) : '';
        $subtitle  = isset( $_POST['subtitle'] ) ? sanitize_textarea_field( wp_unslash( $_POST['subtitle'] ) ) : '';
        $is_active = isset( $_POST['is_active'] ) ? 1 : 0;

        if ( empty( $slug ) && ! empty( $name ) ) {
            $slug = sanitize_title( $name );
        }

        $data = array(
            'name'      => $name,
            'slug'      => $slug,
            'heading'   => $heading,
            'subtitle'  => $subtitle,
            'style'     => 'default',
            'is_active' => $is_active,
        );

        $saved_id = ILC_Cluster_Model::save_cluster( $data, $id ?: null );

        if ( $saved_id ) {
            echo '<div class="notice notice-success"><p>Cluster saved.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Failed to save cluster.</p></div>';
        }
    }

    protected static function handle_update_urls( $cluster_id ) {
        $ids          = isset( $_POST['url_id'] ) ? (array) $_POST['url_id'] : array();
        $urls         = isset( $_POST['url'] ) ? (array) $_POST['url'] : array();
        $anchor_texts = isset( $_POST['anchor_text'] ) ? (array) $_POST['anchor_text'] : array();
        $sort_orders  = isset( $_POST['sort_order'] ) ? (array) $_POST['sort_order'] : array();

        $items = array();

        foreach ( $ids as $index => $id ) {
            $items[] = array(
                'id'          => (int) $id,
                'url'         => isset( $urls[ $index ] ) ? sanitize_text_field( wp_unslash( $urls[ $index ] ) ) : '',
                'anchor_text' => isset( $anchor_texts[ $index ] ) ? sanitize_text_field( wp_unslash( $anchor_texts[ $index ] ) ) : '',
                'sort_order'  => isset( $sort_orders[ $index ] ) ? (int) $sort_orders[ $index ] : 0,
            );
        }

        ILC_Cluster_Model::update_cluster_urls( $cluster_id, $items );

        echo '<div class="notice notice-success"><p>URLs updated.</p></div>';
    }

    protected static function handle_add_url( $cluster_id ) {
        $url         = isset( $_POST['new_url'] ) ? sanitize_text_field( wp_unslash( $_POST['new_url'] ) ) : '';
        $anchor_text = isset( $_POST['new_anchor_text'] ) ? sanitize_text_field( wp_unslash( $_POST['new_anchor_text'] ) ) : '';
        $sort_order  = isset( $_POST['new_sort_order'] ) ? (int) $_POST['new_sort_order'] : 0;

        if ( $url ) {
            ILC_Cluster_Model::add_cluster_url(
                $cluster_id,
                array(
                    'url'         => $url,
                    'anchor_text' => $anchor_text,
                    'sort_order'  => $sort_order,
                )
            );
            echo '<div class="notice notice-success"><p>URL added.</p></div>';
        }
    }

    protected static function render_list_screen() {
        $clusters = ILC_Cluster_Model::get_all_clusters();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Internal Link Clusters', 'internal-link-clusters' ); ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=ilc-clusters&action=add' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'internal-link-clusters' ); ?></a>
            <hr class="wp-header-end" />

            <?php if ( empty( $clusters ) ) : ?>
                <p><?php esc_html_e( 'No clusters found. Click "Add New" to create one.', 'internal-link-clusters' ); ?></p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Name', 'internal-link-clusters' ); ?></th>
                            <th><?php esc_html_e( 'Slug', 'internal-link-clusters' ); ?></th>
                            <th><?php esc_html_e( 'Active', 'internal-link-clusters' ); ?></th>
                            <th><?php esc_html_e( 'Shortcode', 'internal-link-clusters' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'internal-link-clusters' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $clusters as $cluster ) : ?>
                            <tr>
                                <td><?php echo esc_html( $cluster->name ); ?></td>
                                <td><?php echo esc_html( $cluster->slug ); ?></td>
                                <td><?php echo $cluster->is_active ? esc_html__( 'Yes', 'internal-link-clusters' ) : esc_html__( 'No', 'internal-link-clusters' ); ?></td>
                                <td><code>[rc_cluster name="<?php echo esc_attr( $cluster->slug ); ?>"]</code></td>
                                <td>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=ilc-clusters&action=edit&id=' . (int) $cluster->id ) ); ?>"><?php esc_html_e( 'Edit', 'internal-link-clusters' ); ?></a>
                                    |
                                    <?php
                                    $delete_url = wp_nonce_url(
                                        admin_url( 'admin.php?page=ilc-clusters&action=delete&id=' . (int) $cluster->id ),
                                        'ilc_delete_cluster_' . (int) $cluster->id
                                    );
                                    ?>
                                    <a href="<?php echo esc_url( $delete_url ); ?>" onclick="return confirm('Delete this cluster? This will also remove its URLs.');"><?php esc_html_e( 'Delete', 'internal-link-clusters' ); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    protected static function render_edit_screen( $id ) {
        $cluster = $id ? ILC_Cluster_Model::get_cluster_by_id( $id ) : null;

        $name      = $cluster ? $cluster->name : '';
        $slug      = $cluster ? $cluster->slug : '';
        $heading   = $cluster ? $cluster->heading : '';
        $subtitle  = $cluster ? $cluster->subtitle : '';
        $is_active = $cluster ? (int) $cluster->is_active : 1;
        ?>
        <div class="wrap">
            <h1><?php echo $id ? esc_html__( 'Edit Cluster', 'internal-link-clusters' ) : esc_html__( 'Add New Cluster', 'internal-link-clusters' ); ?></h1>

            <form method="post">
                <?php wp_nonce_field( 'ilc_save_cluster' ); ?>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="ilc-name"><?php esc_html_e( 'Name', 'internal-link-clusters' ); ?></label></th>
                            <td><input name="name" type="text" id="ilc-name" value="<?php echo esc_attr( $name ); ?>" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ilc-slug"><?php esc_html_e( 'Slug', 'internal-link-clusters' ); ?></label></th>
                            <td><input name="slug" type="text" id="ilc-slug" value="<?php echo esc_attr( $slug ); ?>" class="regular-text">
                                <p class="description"><?php esc_html_e( 'Used in shortcode: [rc_cluster name="slug"]. Leave blank to auto-generate from name.', 'internal-link-clusters' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ilc-heading"><?php esc_html_e( 'Heading (H2)', 'internal-link-clusters' ); ?></label></th>
                            <td><input name="heading" type="text" id="ilc-heading" value="<?php echo esc_attr( $heading ); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ilc-subtitle"><?php esc_html_e( 'Subtitle', 'internal-link-clusters' ); ?></label></th>
                            <td><textarea name="subtitle" id="ilc-subtitle" rows="3" class="large-text"><?php echo esc_textarea( $subtitle ); ?></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Active', 'internal-link-clusters' ); ?></th>
                            <td>
                                <label><input name="is_active" type="checkbox" value="1" <?php checked( $is_active, 1 ); ?>> <?php esc_html_e( 'Cluster is active', 'internal-link-clusters' ); ?></label>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p class="submit">
                    <button type="submit" name="ilc_save_cluster" class="button button-primary"><?php esc_html_e( 'Save Cluster', 'internal-link-clusters' ); ?></button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=ilc-clusters' ) ); ?>" class="button"><?php esc_html_e( 'Back to list', 'internal-link-clusters' ); ?></a>
                </p>
            </form>

            <?php if ( $id ) : ?>
                <hr />
                <h2><?php esc_html_e( 'Cluster URLs', 'internal-link-clusters' ); ?></h2>
                <?php
                $urls = ILC_Cluster_Model::get_cluster_urls( $id );
                if ( empty( $urls ) ) :
                    ?>
                    <p><?php esc_html_e( 'No URLs in this cluster yet.', 'internal-link-clusters' ); ?></p>
                <?php else : ?>
                    <form method="post">
                        <?php wp_nonce_field( 'ilc_update_urls_' . (int) $id ); ?>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'URL', 'internal-link-clusters' ); ?></th>
                                    <th><?php esc_html_e( 'Anchor Text', 'internal-link-clusters' ); ?></th>
                                    <th><?php esc_html_e( 'Sort Order', 'internal-link-clusters' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $urls as $row ) : ?>
                                    <tr>
                                        <td>
                                            <input type="hidden" name="url_id[]" value="<?php echo (int) $row->id; ?>">
                                            <input type="text" name="url[]" value="<?php echo esc_attr( $row->url ); ?>" class="regular-text">
                                        </td>
                                        <td>
                                            <input type="text" name="anchor_text[]" value="<?php echo esc_attr( $row->anchor_text ); ?>" class="regular-text">
                                        </td>
                                        <td style="width:120px;">
                                            <input type="number" name="sort_order[]" value="<?php echo (int) $row->sort_order; ?>" class="small-text">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p class="submit">
                            <button type="submit" name="ilc_update_urls" class="button button-secondary"><?php esc_html_e( 'Update URLs', 'internal-link-clusters' ); ?></button>
                        </p>
                    </form>
                <?php endif; ?>

                <h3><?php esc_html_e( 'Add New URL to Cluster', 'internal-link-clusters' ); ?></h3>
                <form method="post">
                    <?php wp_nonce_field( 'ilc_add_url_' . (int) $id ); ?>
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="ilc-new-url"><?php esc_html_e( 'URL', 'internal-link-clusters' ); ?></label></th>
                                <td><input name="new_url" type="text" id="ilc-new-url" class="regular-text" placeholder="/knee-pain-treatment/ or full URL"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="ilc-new-anchor"><?php esc_html_e( 'Anchor Text', 'internal-link-clusters' ); ?></label></th>
                                <td><input name="new_anchor_text" type="text" id="ilc-new-anchor" class="regular-text" placeholder="<?php esc_attr_e( 'Optional – defaults to page title or slug', 'internal-link-clusters' ); ?>"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="ilc-new-sort"><?php esc_html_e( 'Sort Order', 'internal-link-clusters' ); ?></label></th>
                                <td><input name="new_sort_order" type="number" id="ilc-new-sort" class="small-text" value="0"></td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="submit">
                        <button type="submit" name="ilc_add_url" class="button button-secondary"><?php esc_html_e( 'Add URL', 'internal-link-clusters' ); ?></button>
                    </p>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }
}
