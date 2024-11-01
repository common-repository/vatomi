<?php
/**
 * Class Vatomi_Envato_API. Class for working with Envato api.
 *
 * @package vatomi
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Vatomi_Envato_API
 */
class Vatomi_Envato_API {
    /**
     * Id of Envato item.
     *
     * @var $item_id
     */
    protected $item_id;

    /**
     * Data of Envato item.
     *
     * @var $item_data
     */
    protected $item_data;

    /**
     * Data of Envato Username.
     *
     * @var $username
     */
    protected $username;

    /**
     * Data of Envato Account.
     *
     * @var $username
     */
    protected $account;

    /**
     * Data of Envato Email.
     *
     * @var $email
     */
    protected $email;

    /**
     * Envato access token.
     * More information: https://build.envato.com/api/#oauth
     *
     * @var $access_token
     */
    protected $access_token = null;

    /**
     * Envato refresh token.
     * More information: https://build.envato.com/api/#oauth
     *
     * @var $refresh_token
     */
    protected $refresh_token = null;

    /**
     * Envato refresh token.
     * More information: https://build.envato.com/api/#oauth
     *
     * @var $refresh_token
     */
    protected $token_expires_in = null;

    /**
     * Envato App Client ID.
     * More information: https://build.envato.com/api/#oauth
     *
     * @var $client_id
     */
    protected $client_id = null;

    /**
     * Envato App Client Secret.
     * More information: https://build.envato.com/api/#oauth
     *
     * @var $client_secret
     */
    protected $client_secret = null;

    /**
     * Envato User purchase license. The unique code of the user purchase.
     *
     * @var $license
     */
    protected $license;

    /**
     * Variable for storing logs.
     *
     * @var string $log
     */
    protected $log = '';

    /**
     * Array for storing errors.
     *
     * @var array $errors
     */
    protected $errors = array();

    /**
     * Flag to enable the cache.
     *
     * @var bool $cache_enabled
     */
    protected $cache_enabled = true;

    /**
     * Cache Time.
     *
     * @var int $cache_lifetime
     */
    protected $cache_lifetime = 5 * 60 * 60; // in seconds [5 hours].

    /**
     * Vatomi_Envato_API constructor.
     */
    public function __construct() {
    }

    /**
     * Loggint and printig in file log.txt.
     *
     * @param string $message - Set of logs.
     */
    public function log( $message ) {
        $this->log .= "\n[" . date( 'm/d/Y h:i:s a' ) . '] ' . $message;
    }

    /**
     * Curl request on envato api.
     *
     * @param string $url - Link to the REST method on the side of envato.
     * @param array  $args - Set of additional parameters for the wp_parse_args method.
     *
     * @return array|mixed|object - Envano server response in json format.
     */
    public function curl_request( $url, $args = array() ) {
        $this->log( 'CURL: ' . $url );

        $defaults = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
            ),
            'timeout' => 20,
        );

        $param_args = wp_parse_args( $args, $defaults );

        $response = wp_remote_get( esc_url_raw( $url ), $param_args );

        if ( is_wp_error( $response ) ) {
            $this->error( 'Error: "' . $response->get_error_message() . '", Code Error: ' . wp_remote_retrieve_response_code( $response ) );
        }

        $this->log( 'Response: ' . json_encode( $response ) );

        $resp_json = json_decode( wp_remote_retrieve_body( $response ), true );

        return $resp_json;
    }

    /**
     * Set envato api access token.
     *
     * @param string $token - Envato access token. More information: https://build.envato.com/api/#oauth.
     */
    public function set_access_token( $token ) {
        $this->access_token = $token;
    }

    /**
     * Set envato api refresh token.
     *
     * @param string $token - Envato refresh token. More information: https://build.envato.com/api/#oauth.
     */
    public function set_refresh_token( $token ) {
        $this->refresh_token = $token;
    }

    /**
     * Set envato api token expires in.
     *
     * @param string $expires - Envato token expires in. More information: https://build.envato.com/api/#oauth.
     */
    public function set_token_expires_in( $expires ) {
        $this->token_expires_in = $expires;
    }

    /**
     * Get envato api refresh token.
     *
     * @return bool|string - Refresh token in case of success, false in case of failure
     */
    public function get_refresh_token() {
        if ( $this->refresh_token ) {
            return $this->refresh_token;
        }
        return false;
    }

    /**
     * Get envato api access token.
     *
     * @return bool|string - Access token in case of success, false in case of failure
     */
    public function get_access_token() {
        if ( $this->access_token ) {
            return $this->access_token;
        }
        return false;
    }

    /**
     * Get envato api token expires in.
     *
     * @return bool|string - Expires in token in case of success, false in case of failure
     */
    public function get_token_expires_in() {
        if ( $this->token_expires_in ) {
            return $this->token_expires_in;
        }
        return false;
    }

    /**
     * Set envato api cliend Id.
     *
     * @param string $id - OAuth Client ID of your app from the My Apps page: https://build.envato.com/my-apps/.
     */
    public function set_client_id( $id ) {
        $this->client_id = $id;
    }

    /**
     * Set envato api secret key.
     *
     * @param string $client_secret - Your secret envato application key from the My Apps page: https://build.envato.com/register/.
     */
    public function set_client_secret( $client_secret ) {
        $this->client_secret = $client_secret;
    }

    /**
     * Refresh token if it expired.
     */
    public function refresh_token() {
        if ( isset( $this->refresh_token ) ) {
            $this->log( 'Refreshing Token...' );

            // Get user access token.
            $result = wp_remote_post( esc_url_raw( 'https://api.envato.com/token' ), array(
                'headers' => array(
                    'Content-type' => 'application/x-www-form-urlencoded',
                ),
                'body' => array(
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $this->refresh_token,
                    'client_id' => $this->client_id,
                    'client_secret' => $this->client_secret,
                ),
                'timeout' => 20,
            ) );

            if ( is_wp_error( $result ) ) {
                $this->log( 'Refreshing Token Error: ' . $result->get_error_message() );
            } else {
                $result = json_decode( wp_remote_retrieve_body( $result ) );
                if ( isset( $result->error ) ) {
                    $this->log( 'Refreshing Token Error: ' . isset( $result->error_description ) ? $result->error_description : $result->error );
                } else {
                    if ( isset( $result->access_token ) ) {
                        $this->log( 'New Access Token: ' . $result->access_token );
                        $this->set_access_token( $result->access_token );
                    }
                    if ( isset( $result->expires_in ) ) {
                        $expires_in = time() + intval( $result->expires_in );
                        $this->log( 'New Token Expires In: ' . $expires_in );
                        $this->set_token_expires_in( $expires_in );
                    }
                }
            }
        }
    }

    /**
     * Maybe refresh expired token?
     *
     * @param int $token_expires_in - expirest in seconds.
     *
     * @return bool
     */
    public function maybe_refresh_token( $token_expires_in ) {
        $old_token = $this->access_token;

        if ( ! $token_expires_in || $token_expires_in && time() >= (int) $token_expires_in ) {
            $this->refresh_token();
        }

        return $old_token !== $this->access_token;
    }

    /**
     * Set item id.
     *
     * @param int $id - Id of envato item.
     */
    public function set_item_id( $id ) {
        $this->item_id = $id;
    }

    /**
     * Set user purchase code.
     *
     * @param string $license - Envato purchase code.
     */
    public function set_license( $license ) {
        $this->license = $license;
    }

    /**
     * Lists all purchases that the authenticated user has made of the app creator's listed items.
     *
     * @return bool|mixed - User purchases in case of success, false in case of failure.
     */
    public function get_user_purchases() {
        $url = 'https://api.envato.com/v3/market/buyer/purchases';
        $response = $this->curl_request( $url );
        if ( isset( $response ) && is_array( $response ) && ! empty( $response ) && isset( $response['purchases'] ) && ! empty( $response['purchases'] ) ) {
            return $response['purchases'];
        }
        return false;
    }

    /**
     * Get Info about Item.
     *
     * @return array - Info about Item.
     */
    public function get_item_data_json() {
        $this->fetch_item_data();
        return json_decode( $this->item_data, true );
    }

    /**
     * Check for valid license.
     *
     * @return string - License status.
     */
    public function check_license() {
        if ( isset( $this->license ) ) {
            $url = 'https://api.envato.com/v3/market/author/sale?code=' . $this->license;
            $response = $this->curl_request( $url );
            if ( isset( $response ) && isset( $response['item'] ) ) {
                return $response;
            }
        }
        return false;
    }

    /**
     * Get item WP Uri by license.
     *
     * @return mixed
     */
    public function get_item_wp_uri() {
        $url = 'https://api.envato.com/v3/market/buyer/download?item_id=' . $this->item_id . '&shorten_url=true';
        $response = $this->curl_request( $url );

        if ( isset( $response['wordpress_theme'] ) ) {
            return $response['wordpress_theme'];
        }
        if ( isset( $response['wordpress_plugin'] ) ) {
            return $response['wordpress_plugin'];
        }
        return false;
    }

    /**
     * Get item version.
     *
     * @return mixed
     */
    public function get_item_version() {
        $item_data = $this->get_item_data_json();
        if ( isset( $item_data['wordpress_theme_metadata']['version'] ) ) {
            return $item_data['wordpress_theme_metadata']['version'];
        }
        return false;
    }

    /**
     * Get item uri on Envato market.
     *
     * @return bool|mixed
     */
    public function get_item_uri() {
        $item_data = $this->get_item_data_json();
        if ( isset( $item_data['url'] ) ) {
            return $item_data['url'];
        }
        return false;
    }

    /**
     * Returns all details of a particular item on Envato Market. Special private helper method.
     */
    private function fetch_item_from_envato() {
        if ( ! $this->item_data ) {
            $url = 'https://api.envato.com/v2/market/catalog/item?id=' . $this->item_id;
            $this->item_data = json_encode( $this->curl_request( $url ) );
        }
    }

    /**
     * Return details of a particular item data or cached details. Special private helper method.
     */
    private function fetch_item_data() {
        // Get any cache from transient data.
        $cache_name = 'vatomi_' . md5( 'envato_item_data_' . $this->item_id . '_' . ( $this->access_token ? : '' ) . ( $this->client_secret ? : '' ) . ( $this->client_id ? : '' ) );
        $this->item_data = get_transient( $cache_name );

        if ( ! $this->cache_enabled || false === $this->item_data ) {
            // If transient is not exists - fetch from envato api.
            $this->fetch_item_from_envato();
            set_transient( $cache_name, $this->item_data, $this->cache_lifetime );
        }
    }

    /**
     * Returns all details of a particular item on Envato Market.
     *
     * @return array|mixed|object
     */
    public function get_envato_item() {
        $url = 'https://api.envato.com/v2/market/catalog/item?id=' . $this->item_id;
        return $this->curl_request( $url );
    }

    /**
     * Returns the currently logged in user's email address.
     *
     * @return bool|mixed
     */
    public function get_envato_email() {
        $url = 'https://api.envato.com/v1/market/private/user/email.json';
        $response = $this->curl_request( $url );
        if ( isset( $response ) && is_array( $response ) && ! empty( $response ) && isset( $response['email'] ) && is_email( $response['email'] ) ) {
            $this->email = $response['email'];
            return $this->email;
        }
        return false;
    }

    /**
     * Returns the currently logged in user's Envato Account username.
     *
     * @return bool|mixed
     */
    public function get_envato_username() {
        $url = 'https://api.envato.com/v1/market/private/user/username.json';
        $response = $this->curl_request( $url );
        if ( is_array( $response ) && isset( $response['username'] ) && ! empty( $response['username'] ) ) {
            $this->username = $response['username'];
            return $this->username;
        }
        return false;
    }

    /**
     * Returns the currently logged in user's Envato Account details.
     *
     * @return bool|object
     */
    public function get_envato_user_account_details() {
        $url = 'https://api.envato.com/v1/market/private/user/account.json';
        $response = $this->curl_request( $url );
        if ( is_array( $response ) && isset( $response['account'] ) && ! empty( $response['account'] ) ) {
            $this->account = $response['account'];
            return $this->account;
        }
        return false;
    }

    /**
     * Receiving all items of the seller on its username.
     *
     * @return bool|mixed
     */
    public function get_envato_items_by_username() {
        $this->get_envato_username();
        if ( isset( $this->username ) && ! empty( $this->username ) ) {
            $url = 'https://api.envato.com/v1/discovery/search/search/item?username=' . $this->username;
            $response = $this->curl_request( $url );
            if ( is_array( $response ) && isset( $response['matches'] ) && is_array( $response['matches'] ) && ! empty( $response['matches'] ) ) {
                return $response['matches'];
            }
        }
        return false;
    }

    /**
     * Print errors result.
     */
    public function print_errors_result() {
        $data = $this->errors;
        if ( isset( $data ) && ! empty( $data ) && is_array( $data ) ) {
            add_filter( 'login_message', array( $this, 'login_errors_message' ), 10, 1 );
        }
    }

    /**
     * The "login_messages" filter is used to filter the message displayed on the WordPress Log In page above the Log In form. This filter can return HTML markup.
     * More detailed: https://codex.wordpress.org/Plugin_API/Filter_Reference/login_message
     *
     * @param string $message - Messages List.
     * @return string
     */
    public function login_errors_message( $message ) {
        $message .= '<div id="login_error">';
        foreach ( $this->errors as $error ) {
            if ( $error['error'] ) {
                $message .= '<strong>' . esc_html__( 'Error: ', 'vatomi' ) . '</strong>' . $error['response'] . '</br>';
            }
        }
        $message .= '</div>';
        return $message;
    }

    /**
     * Method of error generation.
     *
     * @param array $data - Error array with status.
     */
    public function error( $data = array() ) {
        if ( ! is_array( $data ) ) {
            $data = array(
                'response' => $data,
            );
        }
        $data['error'] = true;

        $this->log( 'Error: ' . json_encode( $data ) );
        $this->errors[] = $data;
    }
}
