<?php
/**
 * WordPress settings API Vatomi class. These are the basic plugin settings form.
 *
 * @package vatomi
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Vatomi_Settings class. Encapsulation of plugin configuration forms in the frontend.
 * Helper class for working with the Form Designer WeDevs_Settings_API class.
 */
class Vatomi_Settings {
    /**
     * Object to create the settings in the admin panel.
     *
     * @var WeDevs_Settings_API
     */
    private $settings_api;

    /**
     * Vatomi_Settings constructor.
     */
    public function __construct() {
        $this->settings_api = new WeDevs_Settings_API();
        add_action( 'admin_init', array( $this, 'admin_init' ) );
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
    }

    /**
     * Initialize admin panel settings.
     */
    public function admin_init() {
        // set the settings.
        $this->settings_api->set_sections( $this->get_settings_sections() );
        $this->settings_api->set_fields( $this->get_settings_fields() );
        // initialize settings.
        $this->settings_api->admin_init();
    }

    /**
     * Creating a admin menu with plugin settings.
     */
    public function admin_menu() {
        add_submenu_page(
            'vatomi',
            esc_html__( 'Settings', 'vatomi' ),
            esc_html__( 'Settings', 'vatomi' ),
            'manage_options',
            'vatomi-settings',
            array( $this, 'plugin_page' )
        );
    }

    /**
     * Get the top tabs with the plugin settings on the settings page.
     *
     * @return array
     */
    public function get_settings_sections() {
        $sections = array(
            array(
                'id'    => 'vatomi_envato',
                'title' => esc_html__( 'Envato Settings', 'vatomi' ),
            ),
            array(
                'id'    => 'vatomi_licenses',
                'title' => esc_html__( 'Licenses', 'vatomi' ),
            ),
            array(
                'id'    => 'vatomi_button',
                'title' => esc_html__( 'oAuth Button', 'vatomi' ),
            ),
            array(
                'id'    => 'vatomi_logs',
                'title' => esc_html__( 'Logs', 'vatomi' ),
            ),
        );
        return $sections;
    }

    /**
     * Returns all the settings fields
     *
     * @return array settings fields
     */
    public function get_settings_fields() {
        $vatomi_places_button_multicheck_options = array(
            'standard_wordpress_form'   => esc_attr__( 'WordPress Login Form', 'vatomi' ),
        );

        if ( is_plugin_active( 'awesome-support/awesome-support.php' ) ) {
            $vatomi_places_button_multicheck_options['awesome_support'] = esc_attr__( 'Awesome Support Form', 'vatomi' );
        }
        if ( is_plugin_active( 'buddypress/bp-loader.php' ) ) {
            $vatomi_places_button_multicheck_options['buddypress'] = esc_attr__( 'Buddypress Form', 'vatomi' );
        }

        $settings_fields = array(
            'vatomi_envato' => array(
                array(
                    'name'        => 'envato_get_keys_description',
                    'label'       => esc_html__( 'oAuth', 'vatomi' ),
                    'type'        => 'html',
                    'desc'        => '<a href="https://build.envato.com/register/" class="button button-secondary" target="_blank">' . esc_html__( 'Get Secret Key and Client ID', 'vatomi' ) . '</a>
                                    <br>' . esc_html__( 'Set these permissions:', 'vatomi' ) . '
                                    <ul class="ul-square">
                                        <li>View and search Envato sites</li>
                                        <li>View the user\'s Envato Account username</li>
                                        <li>View the user\'s email address</li>
                                        <li>View the user\'s account profile details</li>
                                        <li>Download the user\'s purchased items</li>
                                        <li>Verify purchases of the user\'s items</li>
                                        <li>View the user\'s purchases of the app creator\'s items</li> 
                                    </ul>',
                ),
                array(
                    'name'              => 'envato_secret_key',
                    'desc'              => esc_html__( 'Secret Key', 'vatomi' ),
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                array(
                    'name'              => 'envato_client_id',
                    'desc'              => esc_html__( 'OAuth client ID', 'vatomi' ),
                    'type'              => 'text',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                array(
                    'name'        => 'vatomi_redirect_uri',
                    'desc'        => '<input type="text" readonly class="regular-text" value="' . esc_url( get_site_url( null, 'wp-login.php' ) ) . '" onclick="this.select()"><br>' . esc_html__( 'Confirmation URL', 'vatomi' ),
                    'type'        => 'html',
                ),

                array(
                    'name'        => 'envato_personal_token_description',
                    'label'       => esc_html__( 'Personal Token', 'vatomi' ),
                    'desc'        => '<a href="https://build.envato.com/create-token/?user:username=t&sale:history=t&purchase:verify=t" class="button button-secondary" target="_blank">' . esc_html__( 'Get Personal Token', 'vatomi' ) . '</a>',
                    'type'        => 'html',
                ),
                array(
                    'name'              => 'envato_personal_token',
                    'desc'              => esc_attr__( 'Personal Token', 'vatomi' ),
                    'type'              => 'text',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
            'vatomi_licenses' => array(
                array(
                    'name'    => 'licenses_page_id',
                    'label'   => esc_html__( 'Licenses Page', 'vatomi' ),
                    'type'    => 'select',
                    'find_pages' => true,
                    'default' => '',
                    'options' => array(
                        '' => esc_html__( '-- Select Page --', 'vatomi' ),
                    ) + $this->get_pages(),
                ),
            ),
            'vatomi_button' => array(
                array(
                    'name'    => 'vatomi_places_button_multicheck',
                    'label'   => esc_html__( 'Place inside', 'vatomi' ),
                    'desc'    => esc_html__( 'Select where the sign in button will be located', 'vatomi' ),
                    'type'    => 'multicheck',
                    'default' => array(
                        'standard_wordpress_form' => 'standard_wordpress_form',
                    ),
                    'options' => $vatomi_places_button_multicheck_options,
                ),
                array(
                    'name'        => 'vatomi_description_button_settings',
                    'desc'        => __( 'To manually place a sign in button, use a <code onclick="window.getSelection().selectAllChildren(this)">[vatomi_login_form]</code> shortcode', 'vatomi' ),
                    'type'        => 'html',
                ),
                array(
                    'name'              => 'envato_redirect_after_login',
                    'label'             => esc_html__( 'Redirect after login', 'vatomi' ),
                    'placeholder'       => esc_attr__( 'Enter Redirect Link', 'vatomi' ),
                    'type'              => 'text',
                    'desc'              => esc_html__( 'If you leave the field blank, after authorization, the user will return to the same page on which the button was.', 'vatomi' ),
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
            'vatomi_logs' => array(
                array(
                    'name'    => 'enable_logging',
                    'label'   => esc_html__( 'Enable logging', 'vatomi' ),
                    'type'    => 'checkbox',
                    'default' => 'off',
                ),
                array(
                    'name'    => 'prune_logs',
                    'label'   => esc_html__( 'Prune old logs', 'vatomi' ),
                    'type'    => 'select',
                    'default' => '2wa',
                    'options' => array(
                        'false' => esc_html__( 'Never Prune', 'vatomi' ),
                        '1da' => esc_html__( '1 day ago', 'vatomi' ),
                        '3da' => esc_html__( '3 days ago', 'vatomi' ),
                        '1wa' => esc_html__( '1 week ago', 'vatomi' ),
                        '2wa' => esc_html__( '2 weeks ago', 'vatomi' ),
                        '3wa' => esc_html__( '3 weeks ago', 'vatomi' ),
                        '1ma' => esc_html__( '1 month ago', 'vatomi' ),
                        '2ma' => esc_html__( '2 months ago', 'vatomi' ),
                        '3ma' => esc_html__( '3 months ago', 'vatomi' ),
                        '4ma' => esc_html__( '4 months ago', 'vatomi' ),
                    ),
                ),
            ),
        );
        return $settings_fields;
    }

    /**
     * Print a page with plugin settings.
     */
    public function plugin_page() {
        echo '<div class="wrap">';
        $this->settings_api->show_navigation();
        $this->settings_api->show_forms();
        echo '</div>';
    }

    /**
     * Get all the pages
     *
     * @return array page names with key value pairs
     */
    public function get_pages() {
        $pages = get_pages();
        $pages_options = array();
        if ( $pages ) {
            foreach ( $pages as $page ) {
                $pages_options[ $page->ID ] = $page->post_title;
            }
        }
        return $pages_options;
    }
}
new Vatomi_Settings();

/**
 * Get the value of a settings field
 *
 * @param string $option settings field name.
 * @param string $section the section name this field belongs to.
 * @param string $default default text if it's not found.
 *
 * @return mixed
 */
function vatomi_get_option( $option, $section, $default = '' ) {

    $options = get_option( $section );

    if ( isset( $options[ $option ] ) ) {
        return 'off' === $options[ $option ] ? false : ( 'on' === $options[ $option ] ? true : $options[ $option ] );
    }

    return $default;
}
