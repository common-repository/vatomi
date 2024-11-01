<?php
/**
 * Class Vatomi_Envato_API.
 * Helper class for WordPress engine with Envato API.
 *
 * @package vatomi
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Vatomi_Envato_Wordpress_API
 */
class Vatomi_Envato_Wordpress_API {

    /**
     * Your secret envato application key from the My Apps page: https://build.envato.com/register/.
     *
     * @var string|bool
     */
    protected $secret_key = null;

    /**
     * OAuth Client ID of your app from the My Apps page: https://build.envato.com/my-apps/.
     *
     * @var string|bool
     */
    protected $client_id = null;

    /**
     * After the user has logged in and given their permission,
     * the Envato API will redirect them back to your application on the Confirmation URL provided,
     * with a single-use authentication code provided in the query string (eg. http://your.app/callback?code=abc123...)
     *
     * @var string|bool
     */
    protected $code = null;

    /**
     * A class object Vatomi_Envato_API with low-level methods for accessing the API envato.
     *
     * @var null|Vatomi_Envato_API
     */
    protected $envato_api = null;

    /**
     * Envato api access token.
     * More detail: https://build.envato.com/api/#oauth
     *
     * @var null|string
     */
    protected $access_token = null;

    /**
     * Envato api refresh roken.
     * More detail: https://build.envato.com/api/#oauth
     *
     * @var null|string
     */
    protected $refresh_token = null;

    /**
     * Envato api roken expires in.
     * More detail: https://build.envato.com/api/#oauth
     *
     * @var null|string
     */
    protected $token_expires_in = null;

    /**
     * Personal user purchases.
     *
     * @var null|array
     */
    private $user_purchases_data = null;

    /**
     * Username.
     *
     * @var null|string
     */
    private $username = null;

    /**
     * User Email.
     *
     * @var null|string
     */
    private $email = null;

    /**
     * User Account Details.
     *
     * @var null|object
     */
    private $account = null;

    /**
     * Vatomi_Envato_Wordpress_API constructor.
     *
     * @param array $args - array with additional input parameters:
     * envato_secret_key, envato_client_id, secret_key, client_id, code, user_id, access_token, refresh_token.
     */
    public function __construct( $args = array() ) {
        $this->secret_key = vatomi_get_option( 'envato_secret_key', 'vatomi_envato' );
        $this->client_id = vatomi_get_option( 'envato_client_id', 'vatomi_envato' );

        if ( is_array( $args ) && ! empty( $args ) ) {
            if ( isset( $args['secret_key'] ) && ! empty( $args['secret_key'] ) ) {
                $this->secret_key = $args['secret_key'];
            }
            if ( isset( $args['client_id'] ) && ! empty( $args['client_id'] ) ) {
                $this->client_id = $args['client_id'];
            }
            if ( isset( $args['code'] ) && ! empty( $args['code'] ) ) {
                $this->code = $args['code'];
            }
            if ( isset( $args['access_token'] ) && ! empty( $args['access_token'] ) ) {
                $this->access_token = $args['access_token'];
            }
            if ( isset( $args['refresh_token'] ) && ! empty( $args['refresh_token'] ) ) {
                $this->refresh_token = $args['refresh_token'];
            }
        }

        $this->envato_api = new Vatomi_Envato_API();

        if ( null !== $this->client_id ) {
            $this->envato_api->set_client_id( $this->client_id );
        }
        if ( null !== $this->secret_key ) {
            $this->envato_api->set_client_secret( $this->secret_key );
        }

        // try to get tokens by user id.
        if ( is_array( $args ) && ! empty( $args ) && isset( $args['user_id'] ) && ! empty( $args['user_id'] ) ) {
            $this->retrieve_tokens_by_user_id( $args['user_id'] );
        }

        if ( is_object( $this->envato_api ) && null === $this->access_token && null === $this->refresh_token ) {
            $this->oauth_authentification();
        }

        if ( null !== $this->access_token ) {
            $this->envato_api->set_access_token( $this->access_token );
        }
        if ( null !== $this->refresh_token ) {
            $this->envato_api->set_refresh_token( $this->refresh_token );
        }
    }

    /**
     * The method of authentication of the envato user.
     * Gets the user's tokens and initializes them in the class.
     */
    private function oauth_authentification() {
        if ( null !== $this->secret_key && null !== $this->client_id && null !== $this->code && null !== $this->envato_api ) {
            $url = 'https://api.envato.com/token';

            $envato_result = wp_remote_post(
                $url, array(
                    'body' => array(
                        'grant_type' => 'authorization_code',
                        'code' => $this->code,
                        'client_id' => $this->client_id,
                        'client_secret' => $this->secret_key,
                    ),
                )
            );

            if ( is_wp_error( $envato_result ) ) {
                $this->envato_api->error( esc_html__( 'HTTP request failed. Error was: ', 'vatomi' ) . $envato_result->get_error_message() );
            }

            $envato_result = json_decode( wp_remote_retrieve_body( $envato_result ) );

            // Check for envato error response.
            if ( isset( $envato_result->error ) ) {
                $this->envato_api->error( $envato_result->error_description );
                return;
            }

            // Check tokens.
            if ( ! isset( $envato_result->access_token ) ) {
                $this->envato_api->error( esc_html__( 'Bad access token.', 'vatomi' ) );
            }
            if ( ! isset( $envato_result->refresh_token ) ) {
                $this->envato_api->error( esc_html__( 'Bad refresh token.', 'vatomi' ) );
            }
            if ( ! empty( $envato_result->access_token ) ) {
                $this->access_token = $envato_result->access_token;
            }
            if ( ! empty( $envato_result->refresh_token ) ) {
                $this->refresh_token = $envato_result->refresh_token;
            }
        }
    }

    /**
     * Get tokens by user ID.
     *
     * @param int $user_id user id.
     */
    public function retrieve_tokens_by_user_id( $user_id ) {
        if ( $user_id ) {
            $this->access_token = get_user_meta( $user_id, 'vatomi_access_token', true );
            $this->refresh_token = get_user_meta( $user_id, 'vatomi_refresh_token', true );
            $this->token_expires_in = get_user_meta( $user_id, 'vatomi_token_expires_in', true );

            if ( null !== $this->access_token ) {
                $this->envato_api->set_access_token( $this->access_token );
            }
            if ( null !== $this->refresh_token ) {
                $this->envato_api->set_refresh_token( $this->refresh_token );
            }
            if ( null !== $this->token_expires_in ) {
                $this->envato_api->set_token_expires_in( $this->token_expires_in );
            }

            // maybe refresh expired token.
            if ( $this->envato_api->maybe_refresh_token( $this->token_expires_in ) ) {
                update_user_meta( $user_id, 'vatomi_access_token', $this->get_access_token() );
                update_user_meta( $user_id, 'vatomi_token_expires_in', $this->get_token_expires_in() );
            }
        }
    }

    /**
     * Using the getUserPurchases method, it receives a collection of user purchases and generates a structured array with a limited set of data for later storage in the database.
     *
     * @return array - Structured array of user purchases.
     */
    public function get_user_purchase_items() {
        if ( null !== $this->secret_key && null !== $this->client_id && null !== $this->access_token && null !== $this->refresh_token ) {
            $purachases_data = $this->envato_api->get_user_purchases();
            $this->user_purchases_data = isset( $purachases_data ) && is_array( $purachases_data ) ? $purachases_data : array();
        }
        return $this->user_purchases_data;
    }

    /**
     * Get Username.
     *
     * @return null|string
     */
    public function get_user_name() {
        if ( null === $this->username && null !== $this->secret_key && null !== $this->client_id && null !== $this->access_token && null !== $this->refresh_token ) {
            $this->username = $this->envato_api->get_envato_username();
        }
        return $this->username;
    }

    /**
     * Get User Email.
     *
     * @return null|string
     */
    public function get_user_email() {
        if ( null === $this->email && null !== $this->secret_key && null !== $this->client_id && null !== $this->access_token && null !== $this->refresh_token ) {
            $this->email = $this->envato_api->get_envato_email();
        }
        return $this->email;
    }

    /**
     * Get User Account Details.
     *
     * @return bool|mixed|string
     */
    public function get_user_account_details() {
        if ( null !== $this->secret_key && null !== $this->client_id && null !== $this->access_token && null !== $this->refresh_token ) {
            $this->account = $this->envato_api->get_envato_user_account_details();
        }
        return $this->account;
    }

    /**
     * Get Item JSON.
     *
     * @param int $id  item id.
     *
     * @return bool|array
     */
    public function get_item_data( $id = false ) {
        $this->envato_api->set_item_id( $id );
        return $this->envato_api->get_item_data_json();
    }

    /**
     * Get Item Version.
     *
     * @param int $id  item id.
     *
     * @return bool|array
     */
    public function get_item_version( $id = false ) {
        $this->envato_api->set_item_id( $id );
        return $this->envato_api->get_item_version();
    }

    /**
     * Get Item URI.
     *
     * @param int $id  item id.
     *
     * @return bool|array
     */
    public function get_item_uri( $id = false ) {
        $this->envato_api->set_item_id( $id );
        return $this->envato_api->get_item_uri();
    }

    /**
     * Get Refresh Token.
     *
     * @return bool|mixed|string
     */
    public function get_refresh_token() {
        $this->refresh_token = $this->envato_api->get_refresh_token();
        return $this->refresh_token;
    }

    /**
     * Get Access Token.
     *
     * @return bool|mixed|string
     */
    public function get_access_token() {
        $this->access_token = $this->envato_api->get_access_token();
        return $this->access_token;
    }

    /**
     * Get Token Expires IN.
     *
     * @return bool|mixed|string
     */
    public function get_token_expires_in() {
        $this->token_expires_in = $this->envato_api->get_token_expires_in();
        return $this->token_expires_in;
    }

    /**
     * The method registers and/or authorizes the user in the WordPress system,
     * and then saves his personal data about purchases in tokens in the database for further work with them.
     *
     * @param bool|string $redirect - Link to user redirect after registration and authorization.
     */
    public function create_and_authorize_user( $redirect = null ) {
        $username = $this->get_user_name();
        $email = $this->get_user_email();
        if ( $username && $email ) {
            $user_id = email_exists( $email );

            if ( ! $user_id ) {
                $wp_username = $username;

                // Validate username.
                if ( ! validate_username( $wp_username ) ) {
                    $wp_username = sanitize_user( $wp_username );
                }

                // Generate unique username.
                $try_find_username = 0;
                $saved_username = $wp_username;
                while ( ( username_exists( $wp_username ) ) && $try_find_username++ < 5 ) {
                    $wp_username = $saved_username . '_' . mt_rand();
                }

                $user_id = wp_insert_user(
                    array(
                        'user_login' => $wp_username,
                        'user_pass' => wp_generate_password( 12, false ),
                        'user_email' => $email,
                        'nickname' => $username,
                        'display_name' => $username,
                        'role' => 'vatomi_user',
                    )
                );

                // Registration error.
                if ( is_wp_error( $user_id ) ) {
                    $this->envato_api->error( $user_id->get_error_message() );
                    vatomi_log( esc_html__( 'New Envato user', 'vatomi' ), $user_id->get_error_message(), 'oAuth', 'error' );
                    return;
                }

                // translators: %1$s - username.
                // translators: %2$s - email.
                vatomi_log( sprintf( esc_html__( 'New Envato user: %1$s [%2$s]', 'vatomi' ), esc_html( $wp_username ), esc_html( $email ) ), $user_id, 'oAuth' );
            }

            // Update user meta.
            $this->update_envato_user_data();

            // User authorization.
            wp_clear_auth_cookie();
            wp_set_auth_cookie( $user_id, true );

            // Redirect to a page with a shortcode envato_login_form placed.
            header( 'Location: ' . esc_url_raw( $redirect ) );
            exit;
        }
    }

    /**
     * The method updates the user's purchases and tokens.
     *
     * @param bool $force  force fetch user purchases.
     */
    public function update_envato_user_data( $force = false ) {
        $email = $this->get_user_email();
        if ( $email ) {
            $user_id = email_exists( $email );
            if ( $user_id ) {
                $username = $this->get_user_name();
                $account = $this->get_user_account_details();
                $access_token = $this->get_access_token();
                $refresh_token = $this->get_refresh_token();
                $token_expires_in = $this->get_token_expires_in();

                // Fetch purchases meta and save.
                Vatomi_Licenses::maybe_fetch( $user_id, $force, $this );

                // Set Envato username.
                if ( $username && ! empty( $username ) ) {
                    if ( ! update_user_meta( $user_id, 'vatomi_username', $username ) ) {
                        $this->envato_api->error( esc_html__( 'Envato username were not updated', 'vatomi' ) );
                    }
                }

                // Set account details meta.
                if ( $account && is_array( $account ) && ! empty( $account ) ) {
                    if ( ! update_user_meta( $user_id, 'vatomi_account_details', $account ) ) {
                        $this->envato_api->error( esc_html__( 'User account details were not updated', 'vatomi' ) );
                    }

                    // Set account name.
                    if ( isset( $account['firstname'] ) ) {
                        update_user_meta( $user_id, 'first_name', $account['firstname'] );
                    }
                    if ( isset( $account['surname'] ) ) {
                        update_user_meta( $user_id, 'last_name', $account['surname'] );
                    }
                }

                // Set access and refresh token metas.
                if ( $access_token && ! empty( $access_token ) ) {
                    update_user_meta( $user_id, 'vatomi_access_token', $access_token );
                }
                if ( $refresh_token && ! empty( $refresh_token ) ) {
                    update_user_meta( $user_id, 'vatomi_refresh_token', $refresh_token );
                }
                if ( $token_expires_in && ! empty( $token_expires_in ) ) {
                    update_user_meta( $user_id, 'vatomi_token_expires_in', $token_expires_in );
                }
            }
        }
    }

    /**
     * The method displays errors in the process of working with an Envato in the information block on the wp-login.php WordPress page
     */
    public function print_errors_result() {
        $this->envato_api->print_errors_result();
    }
}
