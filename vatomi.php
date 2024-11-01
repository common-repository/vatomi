<?php
/**
 * Plugin Name:  Vatomi
 * Description:  Envato licenses activation, envato items support and API
 * Version:      1.0.3
 * Author:       nK
 * Author URI:   https://nkdev.info/
 * License:      GPLv2 or later
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:  vatomi
 *
 * @package vatomi
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Vatomi Class
 */
class Vatomi {

    /**
     * The single class instance.
     *
     * @var null
     */
    private static $_instance = null;

    /**
     * Main Instance
     * Ensures only one instance of this class exists in memory at any one time.
     */
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
            self::$_instance->init();
        }
        return self::$_instance;
    }

    /**
     * The base path to the plugin in the file system.
     *
     * @var string
     */
    public $plugin_path;

    /**
     * URL Link to plugin
     *
     * @var string
     */
    public $plugin_url;

    /**
     * Vatomi constructor.
     */
    public function __construct() {
        /* We do nothing here! */
    }

    /**
     * Init.
     */
    public function init() {
        $this->plugin_path = plugin_dir_path( __FILE__ );
        $this->plugin_url = plugin_dir_url( __FILE__ );

        $this->load_text_domain();
        $this->add_actions();
        $this->add_roles();
        $this->include_dependencies();
    }

    /**
     * Plugin activation hook.
     */
    public static function activation() {
        // Create licenses page.
        $settings = get_option( 'vatomi_licenses', array() );
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }
        if ( ! isset( $settings['licenses_page_id'] ) || ! $settings['licenses_page_id'] ) {
            $post_id = wp_insert_post(
                array(
                    'post_title' => esc_attr__( 'Licenses', 'vatomi' ),
                    'post_type' => 'page',
                    'post_author' => get_current_user_id(),
                    'post_status' => 'publish',
                )
            );
            if ( ! is_wp_error( $post_id ) ) {
                $settings['licenses_page_id'] = $post_id;
                update_option( 'vatomi_licenses', $settings );
            }
        }
    }

    /**
     * Sets the text domain with the plugin translated into other languages.
     */
    public function load_text_domain() {
        load_plugin_textdomain( 'vatomi', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    /**
     * Set the main plugin handlers: Envato Sign Action, Save Cookie redirect.
     */
    public function add_actions() {
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_filter( 'parent_file', array( $this, 'admin_menu_highlight_items' ) );

        add_action( 'after_setup_theme', array( $this, 'envato_sign_action' ) );
        add_action( 'after_setup_theme', array( $this, 'envato_cookie_redirect_action' ), 10 );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts_everywhere' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts_everywhere' ) );
        add_action( 'login_head', array( $this, 'enqueue_scripts_everywhere' ) );

        // Tinymce styles.
        add_filter( 'mce_css', array( $this, 'mce_css' ) );
    }

    /**
     * Admin menu.
     */
    public function admin_menu() {
        add_menu_page(
            esc_html__( 'Vatomi', 'vatomi' ),
            esc_html__( 'Vatomi', 'vatomi' ),
            'manage_options',
            'vatomi',
            null,
            'dashicons-vatomi',
            59
        );

        add_submenu_page(
            'vatomi',
            esc_html__( 'Licenses', 'vatomi' ),
            esc_html__( 'Licenses', 'vatomi' ),
            'manage_options',
            'edit.php?post_type=vatomi_license'
        );

        if ( vatomi_get_option( 'enable_logging', 'vatomi_logs' ) ) {
            add_submenu_page(
                'vatomi',
                esc_html__( 'Logs', 'vatomi' ),
                esc_html__( 'Logs', 'vatomi' ),
                'manage_options',
                'edit.php?post_type=vatomi_log'
            );
        }
    }

    /**
     * Highlighting portfolio custom menu items.
     *
     * @param string $parent_file - parent file.
     *
     * @return string $parent_file
     */
    public function admin_menu_highlight_items( $parent_file ) {
        global $current_screen, $submenu_file, $submenu;

        // Highlight menus.
        switch ( $current_screen->post_type ) {
            case 'vatomi_log':
            case 'vatomi_license':
                $parent_file = 'vatomi';
                break;
        }

        // Remove 'Vatomi' sub menu item.
        if ( isset( $submenu['vatomi'] ) ) {
            unset( $submenu['vatomi'][0] );
        }

        return $parent_file;
    }

    /**
     * Add user roles.
     */
    public function add_roles() {
        // Create Vatomi User Role.
        if ( ! wp_roles()->is_role( 'vatomi_user' ) ) {
            add_role(
                'vatomi_user', 'Vatomi', array(
                    'read' => true,
                    'view_ticket' => true,
                    'create_ticket' => true,
                    'close_ticket' => true,
                    'reply_ticket' => true,
                    'attach_files' => true,
                )
            );
        }
    }

    /**
     * Set plugin Dependencies.
     */
    private function include_dependencies() {
        require_once( $this->plugin_path . '/vendors/wedevs-settings-api.php' );
        require_once( $this->plugin_path . '/admin/class-settings.php' );
        require_once( $this->plugin_path . '/admin/class-logging.php' );
        require_once( $this->plugin_path . '/admin/class-licenses.php' );
        require_once( $this->plugin_path . '/admin/class-envato-api.php' );
        require_once( $this->plugin_path . '/admin/class-envato-wordpress-api.php' );
        require_once( $this->plugin_path . '/admin/class-awesome-support.php' );
        require_once( $this->plugin_path . '/admin/class-user-settings.php' );
        require_once( $this->plugin_path . '/admin/class-rest-api.php' );
        require_once( $this->plugin_path . '/front/class-awesome-support.php' );
        require_once( $this->plugin_path . '/shortcodes/envato-login-shortcode.php' );
        require_once( $this->plugin_path . '/shortcodes/licenses.php' );
    }

    /**
     * Authorization and login handler using OAuth Envato.
     */
    public function envato_sign_action() {
        $code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : null;
        if ( null != $code ) {
            $access_token = false;
            $refresh_token = false;

            if ( is_user_logged_in() ) {
                $user_id = get_current_user_id();
                if ( $user_id ) {
                    $access_token = get_user_meta( $user_id, 'vatomi_access_token', true );
                    $refresh_token = get_user_meta( $user_id, 'vatomi_refresh_token', true );
                }
            }

            if ( ( ! is_user_logged_in() || ! $access_token || ! $refresh_token ) && isset( $_COOKIE['vatomi_api_envato_oauth_redirect'] ) ) {
                session_start();

                // We don't need to sanitize cookie string because redirect url will be unusable.
                // phpcs:ignore
                $redirect_uri = $_COOKIE['vatomi_api_envato_oauth_redirect'];
                unset( $_COOKIE['vatomi_api_envato_oauth_redirect'] );
                $api = new Vatomi_Envato_Wordpress_API(
                    array(
                        'code' => $code,
                    )
                );

                $api->create_and_authorize_user( $redirect_uri );
                $api->print_errors_result();
                session_destroy();
            }
        }
    }

    /**
     * Saving the link, which the user went through when clicking on the authorization button.
     * Redirect user to a page with an OAuth handler.
     */
    public function envato_cookie_redirect_action() {
        if ( isset( $_GET['redirect_to'] ) && isset( $_GET['envato_redirect'] ) && true == $_GET['envato_redirect'] ) {
            $client_id = vatomi_get_option( 'envato_client_id', 'vatomi_envato' );
            $client_redirect = get_site_url( null, 'wp-login.php' );

            // Save data in cookies.
            session_start();

            // We don't need to sanitize get string because redirect url will be unusable.
            // phpcs:ignore
            setcookie( 'vatomi_api_envato_oauth_redirect', $_GET['redirect_to'], time() + 5 * 360, COOKIEPATH, COOKIE_DOMAIN );

            header( 'Location: https://api.envato.com/authorization?response_type=code&client_id=' . $client_id . '&redirect_uri=' . urlencode( $client_redirect ) );
            exit;
        }
    }

    /**
     * Enqueue admin styles and scripts.
     */
    public function admin_enqueue_scripts() {
        wp_enqueue_style( 'vatomi-admin', plugins_url( 'vatomi' ) . '/assets/admin/css/style.min.css', array(), '1.0.3' );
    }

    /**
     * Enqueue styles and scripts on frontend and in admin.
     */
    public function enqueue_scripts_everywhere() {
        wp_enqueue_style( 'vatomi', plugins_url( 'vatomi' ) . '/assets/css/style.min.css', array(), '1.0.3' );
        wp_enqueue_script( 'vatomi', plugins_url( 'vatomi' ) . '/assets/js/script.min.js', array( 'jquery' ), '1.0.3', true );
        wp_localize_script(
            'vatomi', 'vatomiOptions', array(
                'nonce' => wp_create_nonce( 'ajax-nonce' ),
                'url' => admin_url( 'admin-ajax.php' ),
            )
        );
    }

    /**
     * Additional styles for TinyMCE.
     *
     * @param string $mce_css css urls string.
     *
     * @return string
     */
    public function mce_css( $mce_css ) {
        if ( ! empty( $mce_css ) ) {
            $mce_css .= ',';
        }

        $mce_css .= plugins_url( 'vatomi' ) . '/assets/admin/css/tinymce.min.css';

        return $mce_css;
    }
}

/**
 * The main cycle of the plugin.
 *
 * @return null|Vatomi
 */
function vatomi() {
    return Vatomi::instance();
}
vatomi();

// Activation hook.
register_activation_hook( __FILE__, array( 'Vatomi', 'activation' ) );
