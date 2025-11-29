<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ILC_Admin_Import_Page {

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $clusters = ILC_Cluster_Model::get_all_clusters();

        if ( isset( $_POST['ilc_import_urls'] ) && check_admin_referer( 'ilc_import_urls' ) ) {
            self::handle_import();
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Import URLs into Cluster', 'internal-link-clusters' ); ?></h1>

            <?php if ( empty( $clusters ) ) : ?>
                <p><?php esc_html_e( 'You must create at least one cluster before importing URLs.', 'internal-link-clusters' ); ?></p>
                <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=ilc-clusters&action=add' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Add New Cluster', 'internal-link-clusters' ); ?></a></p>
            <?php else : ?>
                <form method="post">
                    <?php wp_nonce_field( 'ilc_import_urls' ); ?>

                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="ilc-import-cluster"><?php esc_html_e( 'Cluster', 'internal-link-clusters' ); ?></label></th>
                                <td>
                                    <select name="cluster_id" id="ilc-import-cluster">
                                        <?php foreach ( $clusters as $cluster ) : ?>
                                            <option value="<?php echo (int) $cluster->id; ?>"><?php echo esc_html( $cluster->name ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description"><?php esc_html_e( 'All imported URLs will be added to this cluster.', 'internal-link-clusters' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="ilc-import-urls"><?php esc_html_e( 'URLs', 'internal-link-clusters' ); ?></label></th>
                                <td>
                                    <textarea name="urls" id="ilc-import-urls" class="large-text code" rows="10" placeholder="/knee-pain-treatment/
/knee-arthritis-treatment/
/acl-tear-treatment/"></textarea>
                                    <p class="description"><?php esc_html_e( 'One URL per line. Can be relative paths or full URLs.', 'internal-link-clusters' ); ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p class="submit">
                        <button type="submit" name="ilc_import_urls" class="button button-primary"><?php esc_html_e( 'Import URLs', 'internal-link-clusters' ); ?></button>
                    </p>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    protected static function handle_import() {
        $cluster_id = isset( $_POST['cluster_id'] ) ? (int) $_POST['cluster_id'] : 0;
        $urls_raw   = isset( $_POST['urls'] ) ? wp_unslash( $_POST['urls'] ) : '';

        if ( ! $cluster_id || ! $urls_raw ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Cluster and URLs are required.', 'internal-link-clusters' ) . '</p></div>';
            return;
        }

        $lines  = preg_split( '/\r\n|\r|\n/', $urls_raw );
        $count  = 0;

        foreach ( $lines as $line ) {
            $url = trim( $line );
            if ( '' === $url ) {
                continue;
            }

            ILC_Cluster_Model::add_cluster_url(
                $cluster_id,
                array(
                    'url'         => $url,
                    'anchor_text' => '',
                    'sort_order'  => 0,
                )
            );
            $count++;
        }

        if ( $count ) {
            echo '<div class="notice notice-success"><p>' . sprintf( esc_html__( 'Imported %d URLs.', 'internal-link-clusters' ), (int) $count ) . '</p></div>';
        } else {
            echo '<div class="notice notice-warning"><p>' . esc_html__( 'No valid URLs found to import.', 'internal-link-clusters' ) . '</p></div>';
        }
    }
}
