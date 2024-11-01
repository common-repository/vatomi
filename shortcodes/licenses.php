<?php
/**
 * Vatomi Licenses list.
 *
 * Example:
 * [vatomi_licenses]
 *
 * @package vatomi
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_shortcode( 'vatomi_licenses', 'vatomi_licenses' );
if ( ! function_exists( 'vatomi_licenses' ) ) :
    /**
     * Shortcode vatomi_licenses to display list with user licenses.
     *
     * @param array $atts - Set of input data for shortcode.
     * @param null  $content - Short description information about author.
     * @return string $result - Html shortcode output.
     */
    function vatomi_licenses( $atts, $content = null ) {
        $args = shortcode_atts(
            array(
                'class' => '',
            ), $atts
        );

        $result = '';
        $class = 'vatomi-licenses ' . $args['class'];

        // Check if user logged in with Envato.
        $user_id = get_current_user_id();

        // We don't need to sanitize query string because redirect url will be unusable.
        // phpcs:ignore
        $permalink = add_query_arg( isset( $_SERVER['QUERY_STRING'] ) ? $_SERVER['QUERY_STRING'] : '', '', get_permalink() );

        if ( $user_id ) {
            $access_token = get_user_meta( $user_id, 'vatomi_access_token', true );
            $refresh_token = get_user_meta( $user_id, 'vatomi_refresh_token', true );

            if ( empty( $access_token ) || empty( $refresh_token ) ) {
                return do_shortcode( '[vatomi_login_form redirect_url="' . esc_url( $permalink ) . '"]' );
            }
        } else {
            return do_shortcode( '[vatomi_login_form redirect_url="' . esc_url( $permalink ) . '"]' );
        }

        $action = isset( $_GET['vatomi_action'] ) ? sanitize_text_field( wp_unslash( $_GET['vatomi_action'] ) ) : false;
        $item_id = isset( $_GET['vatomi_item_id'] ) ? sanitize_text_field( wp_unslash( $_GET['vatomi_item_id'] ) ) : false;
        $site = isset( $_GET['vatomi_site'] ) ? sanitize_text_field( wp_unslash( $_GET['vatomi_site'] ) ) : false;
        $redirect = isset( $_GET['vatomi_redirect'] ) ? sanitize_text_field( wp_unslash( $_GET['vatomi_redirect'] ) ) : false;

        // Activation.
        if ( $action && 'activate' === $action ) {
            if ( filter_var( $site, FILTER_VALIDATE_URL ) === false ) {
                $site = false;
            }

            if ( $item_id && $site ) {
                // Get all non-activated licenses.
                $query = new WP_Query(
                    array(
                        // @codingStandardsIgnoreLine
                        'posts_per_page'  => -1,
                        'post_type'       => 'vatomi_license',
                        'meta_query'      => array(
                            array(
                                'key'      => '_vatomi_license_site',
                                'compare'  => 'NOT EXISTS',
                            ),
                            array(
                                'key'      => '_vatomi_license_user_id',
                                'value'    => $user_id,
                                'compare'  => '=',
                            ),
                            array(
                                'key'      => '_vatomi_license_item_id',
                                'value'    => $item_id,
                                'compare'  => '=',
                            ),
                        ),
                    )
                );

                $cant_find_message = '<div><p class="vatomi-alert vatomi-alert-info">' . esc_html__( 'If you already activated license you can see it on "Licenses List" page. Can\'t find your license? Try to click on "Refresh Data" button.', 'vatomi' ) . '</p></div>';

                if ( $query->have_posts() ) {
                    $secret_key = vatomi_get_option( 'envato_secret_key', 'vatomi_envato' );
                    $client_id = vatomi_get_option( 'envato_client_id', 'vatomi_envato' );
                    $access_token = vatomi_get_option( 'envato_personal_token', 'vatomi_envato' );

                    $api = new Vatomi_Envato_Wordpress_API(
                        array(
                            'secret_key' => $secret_key,
                            'client_id' => $client_id,
                            'access_token' => $access_token,
                        )
                    );
                    $item = $api->get_item_data( $item_id );
                    $item_name = $item && isset( $item['name'] ) ? $item['name'] : false;

                    if ( $item_name ) {
                        $result .= '<h3>Activate <strong>' . esc_html( $item_name ) . '</strong></h3>';
                    }
                    $result .= '<div>on site: <a href="' . esc_url( $site ) . '">' . esc_html( $site ) . '</a></div><br>';
                    $result .= '<form action="' . esc_url( get_the_permalink() ) . '">';
                    $result .= '<input type="hidden" name="vatomi_action" value="activate">';
                    $result .= '<input type="hidden" name="vatomi_item_id" value="' . esc_attr( $item_id ) . '">';
                    $result .= '<input type="hidden" name="vatomi_site" value="' . esc_url( $site ) . '">';

                    if ( $redirect ) {
                        $result .= '<input type="hidden" name="vatomi_redirect" value="' . esc_url( $redirect ) . '">';
                    }

                    $result .= '<label for="vatomi-activation-select">License:</label>';
                    $result .= '<select id="vatomi-activation-select" name="vatomi_license" required>';
                    $result .= '<option value="" selected disabled>' . esc_html__( '-- Select License --', 'vatomi' ) . '</option>';

                    while ( $query->have_posts() ) {
                        $query->the_post();

                        $data = Vatomi_Licenses::get( get_the_ID() );

                        $result .= '<option value="' . esc_attr( $data['code'] ) . '">[' . esc_html( $data['sold_at_human'] ) . '] ' . esc_html( $data['code'] ) . '</option>';
                    }

                    wp_reset_postdata();
                    $result .= '</select>';
                    $result .= '<button class="vatomi-btn vatomi-btn-sm vatomi-btn-dark">' . esc_html__( 'Activate', 'vatomi' ) . '</button>';
                    $result .= '</form>';
                } else {
                    $result .= $cant_find_message;
                    $result .= '<a href="' . esc_url( get_the_permalink() ) . '" class="vatomi-btn vatomi-btn-sm vatomi-btn-dark">' . esc_html__( 'Licenses List', 'vatomi' ) . '</a>';
                }

                $result .= '<div class="vatomi-btn-wrapper vatomi-btn-wrapper-pull-right"><a href="#" class="vatomi_refresh_user_data vatomi-btn vatomi-btn-sm vatomi-btn-dark" data-reload-on-success="true">' . esc_html__( 'Refresh Data', 'vatomi' ) . '</a></div>';
            } else {
                $result .= '<a href="' . esc_url( get_the_permalink() ) . '" class="vatomi-btn vatomi-btn-sm vatomi-btn-dark">' . esc_html__( 'Licenses List', 'vatomi' ) . '</a>';
            }

            // Table with available purchase codes.
        } else {
            $licenses = Vatomi_Licenses::get_all();

            if ( ! $licenses ) {
                $result .= '<p class="vatomi-alert vatomi-alert-info">' . esc_html__( 'Can\'t find your license? Try to click on "Refresh Data" button.', 'vatomi' ) . '</p>';
            } elseif ( is_array( $licenses ) ) {
                $result .= '<table class="vatomi-licenses">';
                $result .= '<thead>';
                $result .= '<th>' . esc_html( 'Item', 'vatomi' ) . '</th>';
                $result .= '<th>' . esc_html( 'License', 'vatomi' ) . '</th>';
                $result .= '<th>' . esc_html( 'Site', 'vatomi' ) . '</th>';
                $result .= '<th>' . esc_html( 'Supported', 'vatomi' ) . '</th>';
                $result .= '</thead>';
                $result .= '<tbody>';
                foreach ( $licenses as $license ) {
                    $deactivation_btn_url = add_query_arg(
                        array(
                            'vatomi_action' => 'deactivate',
                            'vatomi_item_id' => $license['item_id'],
                            'vatomi_license' => $license['code'],
                        ), get_the_permalink()
                    );
                    $deactivation_btn = '<a class="vatomi-licenses-deactivate vatomi-btn vatomi-btn-sm vatomi-btn-dark" href="' . esc_url( $deactivation_btn_url ) . '">' . esc_html( 'Deactivate', 'vatomi' ) . '</a>';

                    $result .= '<tr>';
                    $result .= '<td class="vatomi-licenses-item">' . esc_html( $license['item_name'] ) . '</td>';
                    $result .= '<td class="vatomi-licenses-license">' . esc_html( $license['license'] ) . ':<br>' . esc_html( $license['code'] ) . '</td>';
                    $result .= '<td class="vatomi-licenses-site">' . ( $license['site'] ? $deactivation_btn : '' ) . '<span>' . esc_html( $license['site'] ? : '&#8212;' ) . '</span></td>';
                    $result .= '<td class="vatomi-licenses-supported">' . esc_html( $license['supported_until_human'] ) . '</td>';
                    $result .= '</tr>';
                }
                $result .= '</tbody>';
                $result .= '</table>';
            }

            $result .= '<div class="vatomi-btn-wrapper"><a href="#" class="vatomi_refresh_user_data vatomi-btn vatomi-btn-sm vatomi-btn-dark" data-reload-on-success="true">' . esc_html__( 'Refresh Data', 'vatomi' ) . '</a></div>';
        }

        $result = '<div class="' . esc_attr( $class ) . '">' . $result . '</div>';
        return $result;
    }
endif;
