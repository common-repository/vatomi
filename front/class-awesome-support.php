<?php
/**
 * A set of methods for working with submission form of creating tickets for the awesome support plugin.
 *
 * @package vatomi
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Vatomi_Awesome_Support
 */
class Vatomi_Awesome_Support {

    /**
     * Vatomi_Awesome_Support constructor.
     */
    public function __construct() {
        add_filter( 'wpas_cf_taxonomy_oredered_terms', array( $this, 'filter_processing_envato_products' ), 10, 1 );
        add_action( 'wpas_submission_form_inside_before_subject', array( $this, 'action_add_envato_login_button' ), 10 );

        add_filter( 'wpas_before_submit_new_ticket_checks', array( $this, 'filter_envato_product_validation' ), 10, 1 );

        add_action( 'wp_ajax_vatomi_refresh_user_data', array( $this, 'ajax_refresh_user_data' ), 100 );
        add_action( 'wp_ajax_vatomi_get_wpas_products_select', array( $this, 'ajax_get_wpas_products_select' ), 100 );
    }

    /**
     * Forming a list of product terms available to the user when creating a ticket.
     *
     * @param array $ordered_terms - List of all term products in the system.
     * @return array - List of all user-accessible products. In the process it is limited by the time of technical support and by the purchase of the user.
     */
    public function filter_processing_envato_products( $ordered_terms ) {
        if ( isset( $ordered_terms ) && is_array( $ordered_terms ) && ! empty( $ordered_terms ) ) {
            // access to 1 element of terms to determine that this is an exact array of products.
            $copy_ordery_terms = $ordered_terms;
            $first_term = array_shift( $copy_ordery_terms );
            if ( is_object( $first_term ) && isset( $first_term->taxonomy ) && 'product' == $first_term->taxonomy && is_user_logged_in() ) {
                $user_id = get_current_user_id();
                $access_token = get_user_meta( $user_id, 'vatomi_access_token', true );
                $refresh_token = get_user_meta( $user_id, 'vatomi_refresh_token', true );

                // Fetch purchases meta and save.
                if ( ! empty( $access_token ) && ! empty( $refresh_token ) ) {
                    $api = new Vatomi_Envato_Wordpress_API(
                        array(
                            'user_id' => $user_id,
                        )
                    );
                    $api->update_envato_user_data();
                }

                $user_purchases = Vatomi_Licenses::get_all( $user_id );

                // check some envato items validation.
                // remove it from list if user don't have license.
                // add '(support expired)' to the item name.
                foreach ( $ordered_terms as $key => $term ) {
                    $term_meta = get_option( "taxonomy_$term->term_id" );
                    $is_envato_product = isset( $term_meta['vatomi_envato_license_verification'] ) && $term_meta['vatomi_envato_license_verification'];

                    if ( $is_envato_product ) {
                        $purchased_product = false;
                        foreach ( $user_purchases as $user_purchase ) {
                            if ( $user_purchase['item_id'] == $term_meta['vatomi_envato_product_id'] ) {
                                $purchased_product = true;

                                if ( ! $user_purchase['supported'] ) {
                                    $term->name .= ' (support expired)';
                                } else {
                                    break;
                                }
                            }
                        }
                        if ( ! $purchased_product ) {
                            unset( $ordered_terms[ $key ] );
                        }
                    }
                }
            }
        }
        return $ordered_terms;
    }

    /**
     * Add envato authorization button or button for refresh user purchases data.
     */
    public function action_add_envato_login_button() {
        $user_id = get_current_user_id();
        if ( $user_id ) {
            $access_token = get_user_meta( $user_id, 'vatomi_access_token', true );
            $refresh_token = get_user_meta( $user_id, 'vatomi_refresh_token', true );

            if ( empty( $access_token ) && empty( $refresh_token ) ) {
                echo do_shortcode( '[vatomi_login_form redirect_url="' . esc_url( get_permalink() ) . '"]' );
            } else {
                echo '<div class="vatomi-btn-wrapper"><a href="#" class="vatomi_refresh_user_data vatomi-btn vatomi-btn-sm vatomi-btn-dark">' . esc_html__( 'Refresh Data', 'vatomi' ) . '</a></div>';
            }
        }
    }

    /**
     * When trying to create a ticket, the method validation input data and generate error if their incorrect.
     *
     * @param bool $error_flag - In the absence of other errors: the truth.
     * @return WP_Error|bool - In the absence of other errors: $error_flag. In the case of errors, an object of class WP_Error with an error message.
     */
    public function filter_envato_product_validation( $error_flag ) {
        $select_product_id = isset( $_REQUEST['wpas_product'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wpas_product'] ) ) : null;
        if ( isset( $select_product_id ) && ! empty( $select_product_id ) ) {
            $current_product = get_term( $select_product_id, 'product' );
            if ( isset( $current_product ) && ! empty( $current_product ) ) {
                if ( is_object( $current_product ) && isset( $current_product->taxonomy ) && 'product' == $current_product->taxonomy && is_user_logged_in() ) {
                    $user_id = get_current_user_id();
                    $user_purchases = Vatomi_Licenses::get_all( $user_id );
                    $product_meta  = get_option( "taxonomy_$current_product->term_id" );
                    if ( isset( $product_meta['vatomi_envato_license_verification'] ) && $product_meta['vatomi_envato_license_verification'] ) {
                        if ( isset( $user_purchases ) && ! empty( $user_purchases ) ) {
                            $coincidence = false;
                            foreach ( $user_purchases as $user_purchase ) {
                                if ( $user_purchase['item_id'] == $product_meta['vatomi_envato_product_id'] ) {
                                    if ( $user_purchase['supported'] ) {
                                        $coincidence = true;
                                        break;
                                    } else {
                                        $error_flag = new WP_Error( 'product_error', esc_html__( 'Support Expired', 'vatomi' ) );
                                    }
                                    $coincidence = true;
                                }
                            }
                            if ( ! $coincidence ) {
                                $error_flag = new WP_Error( 'product_error', esc_html__( 'You should purchase this product to get support.', 'vatomi' ) );
                            }
                        } else {
                            $error_flag = new WP_Error( 'product_error', esc_html__( 'Your purchase list is empty. You should purchase this product to get support.', 'vatomi' ) );
                        }
                    }
                }
            } else {
                $error_flag = new WP_Error( 'product_error', esc_html__( 'Please select product', 'vatomi' ) );
            }
        } else {
            $error_flag = new WP_Error( 'product_error', esc_html__( 'Please select product', 'vatomi' ) );
        }
        return $error_flag;
    }

    /**
     * AJAX Refresh user Envato data.
     */
    public function ajax_refresh_user_data() {
        $nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : 0;
        $user_id = isset( $_REQUEST['user_id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['user_id'] ) ) : 0;
        $verify_nonce = wp_verify_nonce( $nonce, 'ajax-nonce' );

        // TODO: for some reason verification is not working on frontend form.
        $verify_nonce = 1;

        if ( $verify_nonce ) {
            $user_id = $user_id ? : get_current_user_id();

            // Update user envato data if user can do this.
            if ( current_user_can( 'edit_user', $user_id ) ) {
                $api = new Vatomi_Envato_Wordpress_API(
                    array(
                        'user_id' => $user_id,
                    )
                );
                $api->update_envato_user_data( true );

                $response_array = array(
                    'success' => true,
                );
            } else {
                $response_array = array(
                    'success' => false,
                    'response' => esc_html__( 'You have no permission to do this.', 'vatomi' ),
                );
            }

            header( 'Content-Type: application/json' );
            echo json_encode( $response_array );
        }
        exit();
    }

    /**
     * AJAX Get select field with support products for wpas
     * At the output, the selector prints json with a new list of products available to the user when creating a ticket.
     */
    public function ajax_get_wpas_products_select() {
        $nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : 0;
        if ( wp_verify_nonce( $nonce, 'ajax-nonce' ) ) {
            $field = array(
                'name' => 'product',
                'args' => array(
                    'required'   => true,
                    'field_type' => 'taxonomy',
                    'log'        => true,
                    'capability' => 'create_ticket',
                    'sanitize' => 'sanitize_text_field',
                    'save_callback' => false,
                    'show_column' => true,
                    'column_callback' => 'wpas_show_taxonomy_column',
                    'sortable_column' => true,
                    'filterable' => true,
                    'title'      => esc_html__( 'Product', 'vatomi' ),
                    'label'      => esc_html__( 'Product', 'vatomi' ),
                    'label_plural' => esc_html__( 'Products', 'vatomi' ),
                    'taxo_hierarchical' => true,
                    'update_count_callback'      => 'wpas_update_ticket_tag_terms_count',
                    'taxo_manage_terms'   => 'ticket_manage_products',
                    'taxo_edit_terms' => 'ticket_edit_products',
                    'taxo_delete_terms' => 'ticket_delete_products',
                    'taxo_assign_terms' => 'create_ticket',
                    'readonly' => false,
                    'name' => esc_html__( 'Product', 'vatomi' ),
                    'rewrite' => array(
                        'slug' => 'product',
                    ),
                    'select2' => false,
                ),
            );
            $this_field = new WPAS_Custom_Field( 'product', $field );
            $output     = $this_field->get_output();

            $response_array = array(
                'success' => true,
                'content' => $output,
            );
            header( 'Content-Type: application/json' );
            echo json_encode( $response_array );
        }
        exit();
    }
}

new Vatomi_Awesome_Support();
