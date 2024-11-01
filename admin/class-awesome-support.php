<?php
/**
 * Awesome Support extended features.
 *
 * @package vatomi
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Vatomi_Admin_Awesome_Support
 */
class Vatomi_Admin_Awesome_Support {
    /**
     * Vatomi_Admin_Awesome_Support constructor.
     */
    public function __construct() {
        // Import products.
        add_filter( 'wpas_plugin_settings', array( $this, 'filter_import_envato_settings' ), 10, 1 );
        add_action( 'update_option_wpas_options', array( $this, 'action_import_envato_products' ), 10, 3 );
        add_action( 'admin_enqueue_scripts', array( $this, 'action_import_envato_notifications' ) );

        // Meta product.
        add_action( 'product_add_form_fields', array( $this, 'action_envato_product_meta_add_form' ), 10, 2 );
        add_action( 'product_edit_form_fields', array( $this, 'action_envato_products_meta_edit_form' ), 10, 2 );
        add_action( 'edited_product', array( $this, 'action_envato_products_save_meta' ), 10, 2 );
        add_action( 'create_product', array( $this, 'action_envato_products_save_meta' ), 10, 2 );
    }

    /**
     * Add settings for the Envato Import tab.
     *
     * @param  array $def Array of existing settings.
     *
     * @return array      Updated settings
     */
    public function filter_import_envato_settings( $def ) {
        $presonal_token = vatomi_get_option( 'envato_personal_token', 'vatomi_envato' );
        $transient_name = 'envato_items_by_username_' . $presonal_token;
        $envato_products = get_transient( $transient_name );

        if ( ! $envato_products ) {
            $api = new Vatomi_Envato_API();
            $api->set_access_token( $presonal_token );
            $envato_products = $api->get_envato_items_by_username();
            set_transient( $transient_name, $envato_products, HOUR_IN_SECONDS * 2 );
        }

        $vatomi_exist_products = unserialize( get_transient( 'vatomi_exist_products' ) );
        $products = array();

        if ( $envato_products ) {
            foreach ( $envato_products as $product ) {
                $products[ $product['id'] ] = $product['name'];
            }
            if ( $vatomi_exist_products ) {
                foreach ( $vatomi_exist_products as $exist_product ) {
                    unset( $products[ $exist_product ] );
                }
            }
        }
        if ( isset( $products ) && is_array( $products ) && ! empty( $products ) ) {
            $import_field = array(
                'name'    => esc_html__( 'Products', 'vatomi' ),
                'id'      => 'envato_import_products_selector',
                'type'    => 'select',
                'multiple' => true,
                'options'  => $products,
                'default'  => '',
            );
        } else {
            if ( $envato_products ) {
                $import_field = array(
                    'type' => 'note',
                    'desc' => esc_html__( 'You imported all the products you have with Envato. If you want to get a list of products, please remove the already imported products and click on Reset to Defaults.', 'vatomi' ),
                );
            } else {
                $import_field = array(
                    'type' => 'note',
                    'desc' => esc_html__( 'Please check the correctness of your personal token key ', 'vatomi' ) . '<a href="' . admin_url( 'options-general.php?page=vatomi' ) . '">' . esc_html__( 'here', 'vatomi' ) . '</a>',
                );
            }
        }
        $settings = array(
            'envato_import_products' => array(
                'name'    => esc_html__( 'Envato Import', 'vatomi' ),
                'options' => array(
                    array(
                        'name' => esc_html__( 'Select Products', 'vatomi' ),
                        'type' => 'heading',
                    ),
                    $import_field,
                ),
            ),
        );

        return array_merge( $def, $settings );
    }

    /**
     * Import taxonomies for selected products.
     *
     * @param mixed  $old_value - Previous option value.
     * @param mixed  $value - Current save option value.
     * @param string $option - Name of the option to update. Must not exceed 64 characters.
     */
    public function action_import_envato_products( $old_value, $value, $option ) {
        $unserialize_option = maybe_unserialize( $value );
        if ( isset( $unserialize_option ) && ! empty( $unserialize_option ) && is_array( $unserialize_option ) && isset( $unserialize_option['envato_import_products_selector'] ) ) {

            // Obtain existing taxonomies.
            $product_terms = get_terms(
                [
                    'taxonomy' => 'product',
                    'hide_empty' => false,
                ]
            );

            $exist_product_ids = array();
            foreach ( $product_terms as $product ) {
                if ( is_object( $product ) && isset( $product->term_taxonomy_id ) ) {
                    $term_meta  = get_option( "taxonomy_$product->term_taxonomy_id" );
                    $product_id = isset( $term_meta['vatomi_envato_product_id'] ) ? (string) sanitize_key( $term_meta['vatomi_envato_product_id'] ) : '';
                    if ( ! empty( $product_id ) ) {
                        $exist_product_ids[] = $product_id;
                    }
                }
            }

            $presonal_token = vatomi_get_option( 'envato_personal_token', 'vatomi_envato' );
            $error_messages = '';
            $success_messages = '';
            if ( isset( $unserialize_option['envato_import_products_selector'] ) ) {
                foreach ( $unserialize_option['envato_import_products_selector'] as $product_id ) {
                    // Create a taxonomy of the product, if not already present.
                    $api = new Vatomi_Envato_API();
                    $api->set_access_token( $presonal_token );
                    $api->set_item_id( $product_id );
                    $envato_product = $api->get_envato_item();

                    if ( isset( $envato_product ) && ! empty( $envato_product ) && is_array( $envato_product ) ) {
                        if ( false === array_search( $product_id, $exist_product_ids ) ) {

                            // Create taxonomy.
                            $data = wp_insert_term( $envato_product['name'], 'product' );
                            if ( ! is_wp_error( $data ) ) {
                                $api->log( 'Create Taxonomy: id-' . $product_id . ', name: ' . $envato_product['name'] );
                                $term_id = $data['term_id'];

                                // update envato id field.
                                update_option(
                                    "taxonomy_$term_id", array(
                                        'vatomi_envato_product_id' => $product_id,
                                        'vatomi_envato_license_verification' => true,
                                        'vatomi_envato_thumbnail_url' => $envato_product['thumbnail_url'],
                                    )
                                );
                            }
                            $success_messages .= esc_html__( 'Product: ', 'vatomi' ) . $envato_product['name'] . '[' . $product_id . ']' . esc_html__( ' successfully imported.', 'vatomi' ) . '</br>';
                        } else {
                            $error_messages .= esc_html__( 'Product: ', 'vatomi' ) . $envato_product['name'] . '[' . $product_id . ']' . esc_html__( ' already exist.', 'vatomi' ) . '</br>';
                        }
                    }
                    unset( $api );
                }
            }
            if ( ! empty( $exist_product_ids ) ) {
                set_transient( 'vatomi_exist_products', serialize( $exist_product_ids ) );
            } else {
                delete_transient( 'vatomi_exist_products' );
            }
            if ( ! empty( $error_messages ) ) {
                set_transient( 'vatomi_import_errors', $error_messages );
            } else {
                delete_transient( 'vatomi_import_errors' );
            }
            if ( ! empty( $success_messages ) ) {
                set_transient( 'vatomi_import_success', $success_messages );
            } else {
                delete_transient( 'vatomi_import_success' );
            }
        }
    }

    /**
     * Print admin notifications with import result messages.
     */
    public function action_import_envato_notifications() {
        if ( isset( $_GET['tab'] ) && 'envato_import_products' === $_GET['tab'] ) {
            $errors = get_transient( 'vatomi_import_errors' );
            $success = get_transient( 'vatomi_import_success' );

            if ( $errors || $success ) {
                wp_enqueue_script( 'vatomi-envato-import-validation', plugins_url( 'vatomi' ) . '/assets/js/envato-import-validation.min.js', array( 'jquery' ), '1.0.3', true );
                wp_localize_script(
                    'vatomi-envato-import-validation', 'vatomiImportMessages', array(
                        'errors' => $errors ? $errors : '',
                        'success' => $success ? $success : '',
                    )
                );
            } else {
                delete_transient( 'vatomi_import_errors' );
                delete_transient( 'vatomi_import_success' );
            }
        } else {
            delete_transient( 'vatomi_import_errors' );
            delete_transient( 'vatomi_import_success' );
        }
    }

    /**
     * Additional fields for product taxonomies on add form.
     */
    public function action_envato_product_meta_add_form() {
        ?>
        <h3><?php esc_html_e( 'Envato Validation', 'vatomi' ); ?></h3>
        <div class="form-field">
            <label for="term_meta[vatomi_envato_product_id]"><?php esc_html_e( 'License Verification', 'vatomi' ); ?></label>
            <label>
                <input type="checkbox" name="term_meta[vatomi_envato_license_verification]" id="term_meta[vatomi_envato_license_verification]" value="1">
                <?php esc_html_e( 'Enable Envato license verification for this product.', 'vatomi' ); ?>
            </label>
        </div>
        <div class="form-field">
            <label for="term_meta[vatomi_envato_product_id]"><?php esc_html_e( 'Product ID', 'vatomi' ); ?></label>
            <input type="text" name="term_meta[vatomi_envato_product_id]" id="term_meta[vatomi_envato_product_id]" value="">
            <p class="description"></p>
        </div>
        <?php
    }

    /**
     * Additional fields for product taxonomies on edit form.
     *
     * @param object $term - The edit product term.
     */
    public function action_envato_products_meta_edit_form( $term ) {
        // put the term ID into a variable.
        $t_id = $term->term_id;

        // retrieve the existing value(s) for this meta field. This returns an array.
        $term_meta = get_option( "taxonomy_$t_id" );
        ?>
        <tr class="form-field">
            <td colspan="2"><h3><?php esc_html_e( 'Envato Validation', 'vatomi' ); ?></h3></td>
        </tr>
        <tr class="form-field">
            <th scope="row" valign="top"><label for="term_meta[vatomi_envato_license_verification]"><?php esc_html_e( 'Enable License Verification', 'vatomi' ); ?></label></th>
            <td>
                <label>
                    <input type="checkbox" name="term_meta[vatomi_envato_license_verification]" id="term_meta[vatomi_envato_license_verification]" value="1"
                        <?php
                        if ( isset( $term_meta['vatomi_envato_license_verification'] ) ) :
                            ?>
                            checked="checked"<?php endif; ?>> <?php esc_html_e( 'Yes', 'vatomi' ); ?>
                </label>
                <p class="description"><?php esc_html_e( 'Enable the Envato license verification for this product.', 'vatomi' ); ?></p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row" valign="top"><label for="term_meta[vatomi_envato_product_id]"><?php esc_html_e( 'Product ID', 'vatomi' ); ?></label></th>
            <td>
                <input type="text" name="term_meta[vatomi_envato_product_id]" id="term_meta[vatomi_envato_product_id]" value="<?php echo isset( $term_meta['vatomi_envato_product_id'] ) ? esc_attr( $term_meta['vatomi_envato_product_id'] ) : ''; ?>">
                <p class="description"></p>
            </td>
        </tr>
        <?php
    }

    /**
     * Save custom taxonomy meta fields.
     *
     * @param int $term_id - Save term id.
     */
    public function action_envato_products_save_meta( $term_id ) {
        // @codingStandardsIgnoreStart
        if ( isset( $_POST['term_meta'] ) ) {
            $t_id      = $term_id;
            $term_meta = get_option( "taxonomy_$t_id" );
            $cat_keys  = array_keys( $_POST['term_meta'] );

            foreach ( $cat_keys as $key ) {
                if ( isset( $_POST['term_meta'][ $key ] ) ) {
                    $term_meta[ $key ] = $_POST['term_meta'][ $key ];
                }
            }

            if ( ! isset( $_POST['term_meta']['vatomi_envato_license_verification'] ) ) {
                unset( $term_meta['vatomi_envato_license_verification'] );
            }

            update_option( "taxonomy_$t_id", $term_meta );
        }
        // @codingStandardsIgnoreEnd
    }
}

new Vatomi_Admin_Awesome_Support();
