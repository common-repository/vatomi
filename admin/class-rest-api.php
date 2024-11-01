<?php
/**
 * Class Vatomi_Rest
 *
 * Examples:
 *      Success Response Example:
 *      { "success": true, "response": "..." }
 *
 *      Error Response Example:
 *      { "error": false, "error_code": "...", "success": false, "response": "..." }
 *
 *      Get item URL:
 *      https//.../wp-json/vatomi/v1/envato/item_url/18711623
 *
 *      Get item WP URL if user activated theme:
 *      https//.../wp-json/vatomi/v1/envato/item_wp_url/18711623?license=d0dc5cd2-21312-d23s-ccd2-123sa...&site=https://...
 *
 *      Get item WP URL if user have Envato api access and refresh tokens:
 *      https//.../wp-json/vatomi/v1/envato/item_wp_url/18711623?license=d0dc5cd2-21312-d23s-ccd2-123sa...&access_token=abc...&refresh_token=abc...
 *
 *      Get item version:
 *      https//.../wp-json/vatomi/v1/envato/item_version/18711623
 *
 *      Check valid purchase code:
 *      https//.../wp-json/vatomi/v1/envato/check_license/d0dc5cd2-21312-d23s-ccd2-123sa...
 *
 * @package vatomi
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Vatomi_Rest
 */
class Vatomi_Rest extends WP_REST_Controller {

    /**
     * Namespace.
     *
     * @var string
     */
    protected $namespace = 'vatomi/v';

    /**
     * Version.
     *
     * @var string
     */
    protected $version   = '1';

    /**
     * API object.
     *
     * @var null|Vatomi_Envato_API
     */
    protected $api = null;

    /**
     * Vatomi_Rest constructor.
     */
    public function __construct() {
        $secret_key = vatomi_get_option( 'envato_secret_key', 'vatomi_envato' );
        $client_id = vatomi_get_option( 'envato_client_id', 'vatomi_envato' );
        $access_token = vatomi_get_option( 'envato_personal_token', 'vatomi_envato' );

        if ( $secret_key && $client_id && $access_token ) {
            $this->api = new Vatomi_Envato_API();
            $this->api->set_client_secret( $secret_key );
            $this->api->set_client_id( $client_id );
            $this->api->set_access_token( $access_token );
        }
    }

    /**
     * Register rest routes.
     */
    public function register_routes() {
        $namespace = $this->namespace . $this->version;

        // Get item URL.
        register_rest_route(
            $namespace, '/envato/item_url/(?P<id>[\d]+)', array(
                'methods'         => WP_REST_Server::READABLE,
                'callback'        => array( $this, 'get_envato_item_url' ),
            )
        );

        // Get item WP URL.
        register_rest_route(
            $namespace, '/envato/item_wp_url/(?P<id>[\d]+)', array(
                'methods'         => WP_REST_Server::READABLE,
                'callback'        => array( $this, 'get_envato_item_wp_url' ),
            )
        );

        // Get item version.
        register_rest_route(
            $namespace, '/envato/item_version/(?P<id>[\d]+)', array(
                'methods'         => WP_REST_Server::READABLE,
                'callback'        => array( $this, 'get_envato_item_version' ),
            )
        );

        // Check valid purchase code.
        register_rest_route(
            $namespace, '/envato/check_license/(?P<license>[-\w]+)', array(
                'methods'         => WP_REST_Server::READABLE,
                'callback'        => array( $this, 'get_envato_check_license' ),
            )
        );
    }

    /**
     * Register rest.
     */
    public function hook_rest_server() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Get item URL.
     *
     * @param WP_REST_Request $request  request object.
     *
     * @return mixed
     */
    public function get_envato_item_url( WP_REST_Request $request ) {
        if ( ! $this->api ) {
            return $this->error( 'no_api_keys', __( 'No api keys specified.', 'vatomi' ) );
        }

        $id = $request->get_param( 'id' );

        if ( ! $id ) {
            return $this->error( 'no_id_found', __( 'Provide item ID.', 'vatomi' ) );
        }

        $this->api->set_item_id( $id );
        $url = $this->api->get_item_uri();

        if ( ! $url ) {
            return $this->error( 'no_url_found', __( 'Item URL not found.', 'vatomi' ) );
        }

        return $this->success( $url );
    }

    /**
     * Get item version.
     *
     * @param WP_REST_Request $request  request object.
     *
     * @return mixed
     */
    public function get_envato_item_version( WP_REST_Request $request ) {
        if ( ! $this->api ) {
            return $this->error( 'no_api_keys', __( 'No api keys specified.', 'vatomi' ) );
        }

        $id = $request->get_param( 'id' );

        if ( ! $id ) {
            return $this->error( 'no_id_found', __( 'Provide item ID.', 'vatomi' ) );
        }

        $this->api->set_item_id( $id );
        $url = $this->api->get_item_version();

        if ( ! $url ) {
            return $this->error( 'no_version_found', __( 'Item version not found.', 'vatomi' ) );
        }

        return $this->success( $url );
    }

    /**
     * Get item WordPress URL.
     *
     * @param WP_REST_Request $request  request object.
     *
     * @return mixed
     */
    public function get_envato_item_wp_url( WP_REST_Request $request ) {
        if ( ! $this->api ) {
            return $this->error( 'no_api_keys', __( 'No api keys specified.', 'vatomi' ) );
        }

        $id = $request->get_param( 'id' );
        $license = $request->get_param( 'license' );
        $site = $request->get_param( 'site' );
        $site_alt = $site;
        $user_id = false;

        // support for https and http both if user changed it.
        if ( substr( $site_alt, 0, 5 ) === 'https' ) {
            $site_alt = preg_replace( '/^https/', 'http', $site_alt );
        } else if ( substr( $site_alt, 0, 4 ) === 'http' ) {
            $site_alt = preg_replace( '/^http/', 'https', $site_alt );
        }

        // Alternative way to get wp url.
        $access_token = $request->get_param( 'access_token' );
        $refresh_token = $request->get_param( 'refresh_token' );
        $token_expires_in = false;

        if ( ! $id ) {
            return $this->error( 'no_id_found', __( 'Provide item ID.', 'vatomi' ) );
        }

        if ( ! $license ) {
            return $this->error( 'no_license_found', __( 'Provide purchase code.', 'vatomi' ) );
        }

        if ( ! $site && ( ! $access_token || ! $refresh_token ) ) {
            return $this->error( 'no_site_found', __( 'Provide activated site url.', 'vatomi' ) );
        }

        // Check if site activated.
        if ( $site ) {
            $query = new WP_Query(
                array(
                    'posts_per_page'  => 1,
                    'post_type'       => 'vatomi_license',
                    'meta_query'      => array(
                        array(
                            'relation' => 'OR',
                            array(
                                'key'      => '_vatomi_license_site',
                                'value'    => $site,
                                'compare'  => '=',
                            ),
                            array(
                                'key'      => '_vatomi_license_site',
                                'value'    => $site_alt,
                                'compare'  => '=',
                            ),
                        ),
                        array(
                            'key'      => '_vatomi_license_code',
                            'value'    => $license,
                            'compare'  => '=',
                        ),
                        array(
                            'key'      => '_vatomi_license_item_id',
                            'value'    => $id,
                            'compare'  => '=',
                        ),
                    ),
                )
            );

            if ( $query->have_posts() ) {
                $license_id = $query->posts[0]->ID;
                $user_id = get_post_meta( $license_id, '_vatomi_license_user_id', true );

                if ( $user_id ) {
                    $access_token = get_user_meta( $user_id, 'vatomi_access_token', true );
                    $refresh_token = get_user_meta( $user_id, 'vatomi_refresh_token', true );
                    $token_expires_in = get_user_meta( $user_id, 'vatomi_token_expires_in', true );
                }

                wp_reset_postdata();
            } else {
                return $this->error( 'no_site_activated', __( 'Your site was not activated.', 'vatomi' ) );
            }
        }

        $this->api->set_access_token( $access_token );
        $this->api->set_refresh_token( $refresh_token );

        // maybe refresh expired token.
        if ( $user_id && $this->api->maybe_refresh_token( $token_expires_in ) ) {
            update_user_meta( $user_id, 'vatomi_access_token', $this->api->get_access_token() );
            update_user_meta( $user_id, 'vatomi_token_expires_in', $this->api->get_token_expires_in() );
        } else if ( ! $user_id && $refresh_token ) {
            $this->api->maybe_refresh_token( $token_expires_in );
        }

        $this->api->set_item_id( $id );
        $url = $this->api->get_item_wp_uri();

        if ( ! $url ) {
            return $this->error( 'no_url_found', __( 'Item WP URL not found.', 'vatomi' ) );
        }

        return $this->success( $url );
    }

    /**
     * Check purchase code.
     *
     * @param WP_REST_Request $request  request object.
     *
     * @return mixed
     */
    public function get_envato_check_license( WP_REST_Request $request ) {
        if ( ! $this->api ) {
            return $this->error( 'no_api_keys', __( 'No api keys specified.', 'vatomi' ) );
        }

        $purchase_code = $request->get_param( 'license' );

        if ( ! $purchase_code ) {
            return $this->error( 'no_license_found', __( 'Provide purchase code.', 'vatomi' ) );
        }

        $this->api->set_license( $purchase_code );
        $result = $this->api->check_license();

        if ( ! $result ) {
            return $this->error( 'no_valid_license', __( 'Purchase code no valid.', 'vatomi' ) );
        }

        return $this->success( $result );
    }

    /**
     * Success rest.
     *
     * @param mixed $response response data.
     * @return mixed
     */
    public function success( $response ) {
        $rest_url = str_replace( get_rest_url(), '', esc_url( home_url( add_query_arg( null, null ) ) ) );
        vatomi_log( $rest_url, $response, 'Rest' );

        return new WP_REST_Response(
            array(
                'success' => true,
                'response' => $response,
            ), 200
        );
    }

    /**
     * Error rest.
     *
     * @param mixed $code     error code.
     * @param mixed $response response data.
     * @return mixed
     */
    public function error( $code, $response ) {
        $rest_url = str_replace( get_rest_url(), '', esc_url( home_url( add_query_arg( null, null ) ) ) );
        vatomi_log( $rest_url, $response, 'Rest', 'error' );

        return new WP_REST_Response(
            array(
                'error' => true,
                'success' => false,
                'error_code' => $code,
                'response' => $response,
            ), 401
        );
    }
}

$vatomi_rest = new Vatomi_Rest();
$vatomi_rest->hook_rest_server();
