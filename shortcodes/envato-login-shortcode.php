<?php
/**
 * Vatomi Login Form.
 * Example:
 * [vatomi_login_form redirect_url=""]
 *
 * @package vatomi
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_shortcode( 'vatomi_login_form', 'vatomi_login_form' );
if ( ! function_exists( 'vatomi_login_form' ) ) :
    /**
     * Vatomi_login_form shortcode for displayed authorization button
     *
     * @param array $atts - Set of input data for shortcode.
     * @param null  $content - Short description information about author.
     * @return string $result - Html shortcode output.
     */
    function vatomi_login_form( $atts, $content = null ) {
        $args = shortcode_atts(
            array(
                'redirect_url' => '',
                'class' => '',
            ), $atts
        );

        // Shortcode variables.
        $class = esc_attr( $args['class'] );

        $client_redirect_after_logged = vatomi_get_option( 'envato_redirect_after_login', 'vatomi_button' );

        $result = '';

        $code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : null;

        $redirect_url = $args['redirect_url'];
        if ( ! $redirect_url ) {
            $currenturl = get_permalink();
            if ( $currenturl ) {
                $redirect_url = $currenturl;
            } else {
                $redirect_url = get_admin_url();
            }
        }

        if ( isset( $client_redirect_after_logged ) && ! empty( $client_redirect_after_logged ) ) {
            $redirect_url = $client_redirect_after_logged;
        }

        if ( null == $code ) {
            $access_token = false;
            $refresh_token = false;
            if ( is_user_logged_in() ) {
                $user_id = get_current_user_id();
                if ( $user_id ) {
                    $access_token = get_user_meta( $user_id, 'vatomi_access_token', true );
                    $refresh_token = get_user_meta( $user_id, 'vatomi_refresh_token', true );
                }
            }

            if ( ! is_user_logged_in() || ! $access_token && ! $refresh_token ) {
                $link = add_query_arg(
                    array(
                        'redirect_to' => urlencode( $redirect_url ),
                        'envato_redirect' => 1,
                    ), get_site_url( null, 'wp-login.php' )
                );

                $result .= '
                <div class="vatomi-btn-wrapper">
                <a href="' . esc_url_raw( $link ) . '" class="vatomi-btn ' . esc_attr( $class ) . '">' . esc_html__( 'Login With Envato', 'vatomi' ) . '</a>
                </div>
                ';
            }
        }
        return $result;
    }
endif;

/**
 * The vatomi_login_form shortcode integration with standard WordPress login form and other plugin forms: Awesome Support, Buddypress. TODO: Add Login With Ajax, Woocommerce, BBPress
 */
if ( function_exists( 'vatomi_login_form' ) ) {
    if ( ! function_exists( 'vatomi_login_with_wordpress_form' ) ) :
        /**
         * Print Envato button on WP logjn page.
         */
        function vatomi_login_with_wordpress_form() {
            // TODO: styling button output.
            echo do_shortcode( '[vatomi_login_form]' );
        }
    endif;
    if ( ! function_exists( 'vatomi_login_with_awesome_support_form' ) ) :
        /**
         * Print Envato button on Awesome Support registration page.
         */
        function vatomi_login_with_awesome_support_form() {
            // TODO: styling button output or change place.
            echo do_shortcode( '[vatomi_login_form]' );
        }
    endif;
    if ( ! function_exists( 'vatomi_login_with_buddypress_form' ) ) :
        /**
         * Print Envato button on BuddyPress registration page.
         */
        function vatomi_login_with_buddypress_form() {
            // TODO: styling button output or change place.
            echo do_shortcode( '[vatomi_login_form]' );
        }
    endif;
}

$vatomi_places_button_multicheck = vatomi_get_option( 'vatomi_places_button_multicheck', 'vatomi_button' );
if ( isset( $vatomi_places_button_multicheck ) && ! empty( $vatomi_places_button_multicheck ) && is_array( $vatomi_places_button_multicheck ) ) {
    foreach ( $vatomi_places_button_multicheck as $vatomi_place_option ) {
        // Place inside WordPress Login Form if set need option.
        if ( 'standard_wordpress_form' == $vatomi_place_option ) {
            add_action( 'login_message', 'vatomi_login_with_wordpress_form' );
        }
        // Place inside Awesome Support Form if set need option.
        if ( 'awesome_support' == $vatomi_place_option ) {
            add_action( 'wpas_before_login_form', 'vatomi_login_with_awesome_support_form' );
        }
        // Place inside Buddypress Form if set need option.
        if ( 'buddypress' == $vatomi_place_option ) {
            add_action( 'bp_before_register_page', 'vatomi_login_with_buddypress_form' );
        }
    }
}
