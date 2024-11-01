<?php
/**
 * Class Vatomi_Licenses
 *
 * Create Licenses post type for activate user sites by license keys.
 *
 * @package vatomi
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Vatomi_Licenses
 */
class Vatomi_Licenses {
    /**
     * Check when on licenses page.
     *
     * @var bool
     */
    protected $is_licenses_page = false;

    /**
     * Logs array.
     *
     * @var array
     */
    protected static $alerts = array();

    /**
     * Vatomi_Licenses constructor.
     */
    public function __construct() {
        // Create license post type.
        add_action( 'init', array( $this, 'register_post_type' ) );

        // Add to Licenses page state in admin pages list.
        add_filter( 'display_post_states', array( $this, 'display_post_states' ), 10, 2 );

        // Check if this is licenses page and make some hints on it.
        add_action( 'template_redirect', array( $this, 'template_redirect' ) );

        // Add licenses shortcode after content of licenses page.
        add_filter( 'the_content', array( $this, 'add_content_shortcode' ) );

        // Remove actions from licenses list.
        add_filter( 'post_row_actions', array( $this, 'post_row_actions' ) );

        // Hide bulk actions.
        add_filter( 'bulk_actions-edit-vatomi_license', array( $this, 'bulk_actions' ) );

        // Add new admin columns.
        add_filter( 'manage_edit-vatomi_license_columns', array( $this, 'manage_edit_columns' ) );
        add_action( 'manage_vatomi_license_posts_custom_column', array( $this, 'manage_columns' ), 10, 2 );

        // Extend licenses search by additional fields.
        add_action( 'posts_join', array( $this, 'action_extend_licenses_search_join' ) );
        add_action( 'posts_where', array( $this, 'action_extend_licenses_search_where' ) );
        add_action( 'posts_distinct', array( $this, 'action_extend_licenses_search_distinct' ) );
    }

    /**
     * Add alert to print it in the future.
     *
     * @param string $text  log text.
     * @param string $type  log type.
     */
    public static function add_alert( $text, $type = 'success' ) {
        if ( $text ) {
            self::$alerts[] = array(
                'text' => $text,
                'type' => $type,
            );
        }
    }

    /**
     * Get string with alerts.
     *
     * @return string $result
     */
    public static function get_alerts() {
        $result = '';

        if ( self::$alerts && ! empty( self::$alerts ) ) {
            foreach ( self::$alerts as $log ) {
                $result .= '<div class="vatomi-alert vatomi-alert-' . esc_attr( $log['type'] ) . '">' . esc_html( $log['text'] ) . '</div>';
            }
        }

        return $result;
    }

    /**
     * Registers the vatomi_license Post Type.
     *
     * @return     void
     */
    public function register_post_type() {
        $args = array(
            'labels'          => array(
                'name' => __( 'Licenses', 'vatomi' ),
            ),
            'exclude_from_search' => true,
            'publicly_queryable' => false,
            'show_ui'         => true,
            'show_in_nav_menus' => false,
            'show_in_menu'    => false,
            'query_var'       => false,
            'rewrite'         => false,
            'capability_type' => 'post',
            'capabilities'    => array(
                'create_posts' => 'do_not_allow',
            ),
            'map_meta_cap'    => true,
            'supports'        => array( '' ),
            'can_export'      => false,
            'register_meta_box_cb' => array( $this, 'add_metaboxes' ),
        );
        register_post_type( 'vatomi_license', apply_filters( 'vatomi_license_post_type_args', $args ) );
    }

    /**
     * Add a post display state for special Licenses pages in the page list table.
     *
     * @param array   $post_states An array of post display states.
     * @param WP_Post $post        The current post object.
     *
     * @return array $post_states  An array of post display states.
     */
    public function display_post_states( $post_states, $post ) {
        if ( 'page' === $post->post_type ) {
            if ( intval( vatomi_get_option( 'licenses_page_id', 'vatomi_licenses', false ) ) === $post->ID ) {
                $post_states[] = esc_html__( 'Licenses Page', 'vatomi' );
            }
        }
        return $post_states;
    }

    /**
     * Check if this is license page and make some hints.
     */
    public function template_redirect() {
        if ( 'page' !== get_post_type() || intval( vatomi_get_option( 'licenses_page_id', 'vatomi_licenses', false ) ) !== get_the_ID() ) {
            return;
        }

        // Is license page flag.
        $this->is_licenses_page = true;

        // Activation action.
        $action = isset( $_GET['vatomi_action'] ) ? sanitize_text_field( wp_unslash( $_GET['vatomi_action'] ) ) : false;
        if ( $action ) {
            $license = isset( $_GET['vatomi_license'] ) ? sanitize_text_field( wp_unslash( $_GET['vatomi_license'] ) ) : false;
            $item_id = isset( $_GET['vatomi_item_id'] ) ? sanitize_text_field( wp_unslash( $_GET['vatomi_item_id'] ) ) : false;
            $redirect = isset( $_GET['vatomi_redirect'] ) ? sanitize_text_field( wp_unslash( $_GET['vatomi_redirect'] ) ) : false;

            // If user not logged in - redirect to envato oAuth.
            if ( ! get_current_user_id() ) {
                // We don't need to sanitize query string because redirect url will be unusable.
                // phpcs:ignore
                $permalink = add_query_arg( isset( $_SERVER['QUERY_STRING'] ) ? $_SERVER['QUERY_STRING'] : '', '', get_permalink() );

                $redirect_to = add_query_arg(
                    array(
                        'redirect_to' => urlencode( $permalink ),
                        'envato_redirect' => 1,
                    ), get_site_url( null, 'wp-login.php' )
                );

                header( 'Location: ' . $redirect_to );
                exit;
            }

            switch ( $action ) {
                case 'deactivate':
                    $this->deactivate( $license, $item_id, $redirect );
                    break;
                case 'activate':
                    $site = isset( $_GET['vatomi_site'] ) ? sanitize_text_field( wp_unslash( $_GET['vatomi_site'] ) ) : false;
                    $this->activate( $site, $item_id, $license, $redirect );
                    break;
            }
        }
    }

    /**
     * Add shortcode after content on licenses page.
     *
     * @param string $content  content string.
     *
     * @return string
     */
    public function add_content_shortcode( $content ) {
        if ( $this->is_licenses_page ) {
            $content = self::get_alerts() . $content . '[vatomi_licenses]';
        }
        return $content;
    }

    /**
     * Add metaboxes.
     */
    public function add_metaboxes() {
        add_meta_box(
            'vatomi_licenses_info',
            'License Information',
            array( $this, 'metabox_license_info' ),
            'vatomi_license',
            'normal',
            'high'
        );
    }

    /**
     * Add metabox License Info.
     */
    public function metabox_license_info() {
        $data = self::get( get_the_ID() );
        ?>
        <table class="widefat fixed">
            <tbody>
            <tr>
                <th><?php echo esc_html__( 'License', 'vatomi' ); ?></th>
                <td>
                    <?php
                    if ( ! $data['license'] ) {
                        echo '&#8212;';
                    } else {
                        echo esc_html( $data['license'] );
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <th><?php echo esc_html__( 'Code', 'vatomi' ); ?></th>
                <td>
                    <?php
                    if ( ! $data['code'] ) {
                        echo '&#8212;';
                    } else {
                        echo esc_html( $data['code'] );
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <th><?php echo esc_html__( 'Item', 'vatomi' ); ?></th>
                <td>
                    <?php
                    if ( ! $data['item_name'] ) {
                        echo '&#8212;';
                    } else {
                        echo esc_html( $data['item_name'] ) . ' [' . esc_html( $data['item_id'] ) . ']';
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <th><?php echo esc_html__( 'Site', 'vatomi' ); ?></th>
                <td>
                    <?php
                    if ( ! $data['site'] ) {
                        echo '&#8212;';
                    } else {
                        echo esc_html( $data['site'] );
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <th><?php echo esc_html__( 'Buyer', 'vatomi' ); ?></th>
                <td>
                    <?php
                    if ( ! $data['user_id'] ) {
                        echo '&#8212;';
                    } else {
                        $account_details = get_user_meta( $data['user_id'], 'vatomi_account_details', true );

                        echo '<div>';
                        if ( $account_details ) {
                            echo esc_html( $account_details['firstname'] . ' ' . $account_details['surname'] );
                        }
                        if ( $data['username_envato'] ) {
                            $url = 'https://themeforest.net/user/' . $data['username_envato'];
                            echo ' &#8212; <a href="' . esc_url( $url ) . '" target="_blank" title="' . esc_attr( $data['username_envato'] ) . '"><span class="vatomi-icon-envato"></span> <span>' . esc_html( $data['username_envato'] ) . '</span></a>';
                        }
                        echo ' &#8212; <a href="' . esc_url( get_edit_user_link( $data['user_id'] ) ) . '">' . esc_html( 'WordPress Profile', 'vatomi' ) . '</a>';
                        echo '</div>';

                        if ( $account_details ) {
                            echo '<div>' . esc_html( $account_details['country'] ) . '</div>';
                        }
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <th><?php echo esc_html__( 'Purchased', 'vatomi' ); ?></th>
                <td>
                    <?php
                    if ( ! $data['sold_at'] ) {
                        echo '&#8212;';
                    } else {
                        ?>
                        <abbr title="<?php echo esc_attr( date_i18n( 'Y/m/j h:m:s a', strtotime( $data['sold_at'] ) ) ); ?>"><?php echo esc_html( $data['sold_at_human'] ); ?></abbr>
                        <?php
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <th><?php echo esc_html__( 'Supported', 'vatomi' ); ?></th>
                <td>
                    <?php
                    if ( ! $data['supported_until'] ) {
                        echo '&#8212;';
                    } else {
                        ?>
                        <div class="vatomi-support-date<?php echo esc_attr( ( ! $data['supported'] ? '-expired' : '' ) ); ?>">
                            <abbr title="<?php echo esc_attr( date_i18n( 'Y/m/j h:m:s a', strtotime( $data['supported_until'] ) ) ); ?>"><?php echo esc_html( $data['supported_until_human'] ); ?></abbr>
                        </div>
                        <?php
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <th><?php echo esc_html__( 'Amount', 'vatomi' ); ?></th>
                <td>
                    <?php
                    if ( ! $data['amount'] ) {
                        echo '&#8212;';
                    } else {
                        echo '$' . esc_html( $data['amount'] );
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <th><?php echo esc_html__( 'Support Amount', 'vatomi' ); ?></th>
                <td>
                    <?php
                    if ( ! $data['support_amount'] ) {
                        echo '&#8212;';
                    } else {
                        echo '$' . esc_html( $data['support_amount'] );
                    }
                    ?>
                </td>
            </tr>
            </tbody>
        </table>
        <?php
    }

    /**
     * Remove admin table title actions.
     *
     * @param array $actions  actions list.
     *
     * @return array
     */
    public function post_row_actions( $actions ) {
        global $current_screen;
        if ( 'vatomi_license' === $current_screen->post_type ) {
            unset( $actions['view'] );
            unset( $actions['trash'] );
            unset( $actions['inline hide-if-no-js'] );
        }
        return $actions;
    }

    /**
     * Remove edit bulk action.
     *
     * @param array $actions  actions list.
     *
     * @return array
     */
    public function bulk_actions( $actions ) {
        unset( $actions['edit'] );
        return $actions;
    }

    /**
     * Change admin table columns.
     *
     * @param array $columns  columns list.
     *
     * @return array
     */
    public function manage_edit_columns( $columns ) {
        $columns = array(
            'cb'         => '<input type="checkbox" />',
            'title'      => esc_html__( 'Title', 'vatomi' ),
            'license'    => esc_html__( 'License', 'vatomi' ),
            'site'       => esc_html__( 'Site', 'vatomi' ),
            'item'       => esc_html__( 'Item', 'vatomi' ),
            'buyer'      => esc_html__( 'Buyer', 'vatomi' ),
            'dates'      => esc_html__( 'Dates', 'vatomi' ),
        );

        return $columns;
    }

    /**
     * Manage admin table columns.
     *
     * @param string $column  column name.
     * @param int    $post_id post id.
     */
    public function manage_columns( $column, $post_id ) {
        $data = false;

        switch ( $column ) {
            case 'title':
            case 'license':
            case 'site':
            case 'item':
            case 'buyer':
            case 'dates':
                $data = self::get( $post_id );

                if ( ! $data || empty( $data ) ) {
                    echo '&#8212;';
                    return;
                }
                break;
        }
        switch ( $column ) {
            case 'license':
                if ( ! $data['license'] && ! $data['code'] ) {
                    echo '&#8212;';
                } else {
                    echo '<strong>' . esc_html( $data['license'] ) . ':<br><a href="' . esc_url( get_edit_post_link( $post_id ) ) . '">' . esc_html( $data['code'] ) . '</a></strong>';
                }
                break;
            case 'site':
                if ( ! $data['site'] ) {
                    echo '&#8212;';
                } else {
                    echo '<a href="' . esc_url( $data['site'] ) . '">' . esc_html( $data['site'] ) . '</a>';
                }
                break;
            case 'item':
                if ( ! $data['item_name'] ) {
                    echo '&#8212;';
                } else {
                    echo esc_html( $data['item_name'] );
                }
                break;
            case 'buyer':
                if ( ! $data['user_id'] ) {
                    echo '&#8212;';
                } else {
                    if ( $data['username_envato'] ) {
                        $url = 'https://themeforest.net/user/' . esc_attr( $data['username_envato'] );
                        echo '<a href="' . esc_url( $url ) . '" target="_blank" title="' . esc_attr( $data['username_envato'] ) . '"><span class="vatomi-icon-envato"></span> <span>' . esc_html( $data['username_envato'] ) . '</span></a>';
                    } else {
                        echo '&#8212;';
                    }
                }
                break;
            case 'dates':
                // If no duration is found, output a default message.
                if ( ! $data['sold_at'] && ! $data['supported_until'] ) {
                    echo '&#8212;';
                } else {
                    printf( 'Purchased %s', '<abbr title="' . esc_attr( date_i18n( 'Y/m/j h:m:s a', strtotime( $data['sold_at'] ) ) ) . '">' . esc_html( $data['sold_at_human'] ) . '</abbr>' );
                    echo '<div class="vatomi-support-date' . ( ! $data['supported'] ? '-expired' : '' ) . '">';
                    printf( 'Supported %s', '<abbr title="' . esc_attr( date_i18n( 'Y/m/j h:m:s a', strtotime( $data['supported_until'] ) ) ) . '">' . esc_html( $data['supported_until_human'] ) . '</abbr>' );
                    echo '</div>';
                }
                break;
        }
    }

    /**
     * Activate site.
     *
     * @param string $site     site url for activation.
     * @param int    $item_id  item id.
     * @param string $license  license.
     * @param string $redirect redirect url after activation.
     */
    public function activate( $site, $item_id, $license, $redirect ) {
        if ( ! $site || ! $item_id ) {
            self::add_alert( __( 'Not enough data for activation.', 'vatomi' ), 'error' );
            return;
        }

        $user_id = get_current_user_id();

        if ( ! $user_id || ! current_user_can( 'edit_user', $user_id ) ) {
            self::add_alert( __( 'You don\'t have permissions to do this.', 'vatomi' ), 'error' );
            return;
        }

        // Validate license.
        $args = array(
            'posts_per_page'  => 1,
            'post_type'       => 'vatomi_license',
            'meta_query'      => array(
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
        );

        // If there is no license code specified, check if license already activated on $site.
        if ( ! $license ) {
            $args['meta_query'][] = array(
                'key'      => '_vatomi_license_site',
                'value'    => $site,
                'compare'  => '=',
            );

            // Check for valid license.
        } else {
            $args['meta_query'][] = array(
                'key'      => '_vatomi_license_code',
                'value'    => $license,
                'compare'  => '=',
            );
            $args['meta_query'][] = array(
                'relation' => 'OR',
                array(
                    'key'      => '_vatomi_license_site',
                    'value'    => $site,
                    'compare'  => '=',
                ),
                array(
                    'key'      => '_vatomi_license_site',
                    'compare'  => 'NOT EXISTS',
                ),
            );
        }

        $query = new WP_Query( $args );

        if ( $query->have_posts() ) {
            $license_id = $query->posts[0]->ID;
            $data = $this::get( $license_id, $user_id );

            if ( $data && isset( $data['code'] ) ) {
                // Add data about activated site.
                update_post_meta( $license_id, '_vatomi_license_site', $site );

                // translators: %1$s - site.
                // translators: %2$s - license.
                $alert = sprintf( esc_html__( 'Activated %1$s (%2$s).', 'vatomi' ), esc_url( $site ), esc_html( $license ) );

                self::add_alert( $alert );

                vatomi_log( $alert, $data, 'Licenses' );

                // Redirect user with success get variables.
                if ( $redirect ) {
                    $redirect = add_query_arg(
                        array(
                            'vatomi_action' => 'activate',
                            'vatomi_license_code' => $data['code'],
                            'vatomi_item_id' => $item_id,
                        ), $redirect
                    );

                    if ( wp_redirect( $redirect ) ) {
                        exit;
                    }
                }
            }

            wp_reset_postdata();
        } elseif ( $license ) {
            self::add_alert( __( 'Something went wrong while activation.', 'vatomi' ), 'error' );
        }
    }

    /**
     * Deactivate site.
     *
     * @param string $license  license.
     * @param int    $item_id  item id.
     * @param string $redirect redirect url after activation.
     */
    public function deactivate( $license, $item_id, $redirect ) {
        if ( ! $license || ! $item_id ) {
            self::add_alert( __( 'Not enough data for deactivation.', 'vatomi' ), 'error' );
            return;
        }

        $user_id = get_current_user_id();

        if ( ! $user_id || ! current_user_can( 'edit_user', $user_id ) ) {
            self::add_alert( __( 'You don\'t have permissions to do this.', 'vatomi' ), 'error' );
            return;
        }

        // Validate license.
        $args = array(
            'posts_per_page'  => 1,
            'post_type'       => 'vatomi_license',
            'meta_query'      => array(
                array(
                    'key'      => '_vatomi_license_user_id',
                    'value'    => $user_id,
                    'compare'  => '=',
                ),
                array(
                    'key'      => '_vatomi_license_code',
                    'value'    => $license,
                    'compare'  => '=',
                ),
                array(
                    'key'      => '_vatomi_license_item_id',
                    'value'    => $item_id,
                    'compare'  => '=',
                ),
            ),
        );

        $query = new WP_Query( $args );

        if ( $query->have_posts() ) {
            $license_id = $query->posts[0]->ID;
            $data = $this::get( $license_id, $user_id );

            if ( $data && isset( $data['code'] ) ) {
                // Remove site activation.
                delete_post_meta( $license_id, '_vatomi_license_site' );

                // translators: %1$s - site.
                // translators: %2$s - license.
                $alert = sprintf( esc_html__( 'Deactivated %1$s (%2$s).', 'vatomi' ), esc_url( $data['site'] ), esc_html( $data['code'] ) );

                self::add_alert( $alert );

                vatomi_log( $alert, $data, 'Licenses' );
            }

            wp_reset_postdata();
        } elseif ( ! $redirect || ! $item_id ) {
            self::add_alert( __( 'Something went wrong while deactivation.', 'vatomi' ), 'error' );
        }

        // Redirect user with success get variables.
        if ( $redirect && $item_id ) {
            $redirect = add_query_arg(
                array(
                    'vatomi_action' => 'deactivate',
                    'vatomi_item_id' => $item_id,
                ), $redirect
            );

            if ( wp_redirect( $redirect ) ) {
                exit;
            }
        }
    }

    /**
     * Add bulk licenses.
     *
     * @param int   $user_id    user id.
     * @param array $purchases  licenses array.
     *
     * @return bool
     */
    public static function add_bulk( $user_id = 0, $purchases ) {
        if ( $user_id && isset( $purchases ) && is_array( $purchases ) ) {
            foreach ( $purchases as $purchase ) {
                self::add( $user_id, $purchase );
            }
            return true;
        }
        return false;
    }

    /**
     * Add new license or update existing.
     *
     * @param int   $user_id   user id.
     * @param array $purchase  license data.
     *
     * @return int
     */
    public static function add( $user_id, $purchase ) {
        if ( ! current_user_can( 'edit_user', $user_id ) ) {
            return false;
        }

        // Available meta keys.
        $license_fields = array(
            'license',
            'code',
            'sold_at',
            'amount',
            'support_amount',
            'supported_until',
            'item',
        );
        $license_item_fields = array(
            'id',
            'name',
        );

        // Check if required keys exists in purchase data.
        if ( count( array_diff( $license_fields, array_keys( $purchase ) ) ) !== 0 ) {
            return 0;
        }
        if ( count( array_diff( $license_item_fields, array_keys( $purchase['item'] ) ) ) !== 0 ) {
            return 0;
        }

        // Check if already exists.
        $query = new WP_Query(
            array(
                'post_type' => 'vatomi_license',
                'meta_query' => array(
                    array(
                        'key' => '_vatomi_license_code',
                        'value' => $purchase['code'],
                        'compare' => '=',
                    ),
                    array(
                        'key' => '_vatomi_license_user_id',
                        'value' => $user_id,
                        'compare' => '=',
                    ),
                ),
            )
        );

        if ( $query->have_posts() ) {
            $license_id = $query->posts[0]->ID;
            wp_reset_postdata();
        } else {
            // Add license to db.
            $license_id = wp_insert_post(
                array(
                    'post_type'    => 'vatomi_license',
                    'post_status'  => 'publish',
                    'post_parent'  => 0,
                    'post_title'   => $purchase['code'],
                    'log_type'     => false,
                    'log_category' => false,
                )
            );
        }

        do_action( 'wp_pre_insert_vatomi_license' );

        // Set meta data.
        if ( $license_id && ! empty( $purchase ) ) {
            update_post_meta( $license_id, '_vatomi_license_user_id', $user_id );
            update_post_meta( $license_id, '_vatomi_license_full_data', $purchase );

            foreach ( $license_fields as $data ) {
                update_post_meta( $license_id, '_vatomi_license_' . $data, $purchase[ $data ] );
            }
            foreach ( $license_item_fields as $data ) {
                update_post_meta( $license_id, '_vatomi_license_item_' . $data, $purchase['item'][ $data ] );
            }
        }

        do_action( 'wp_post_insert_vatomi_license', $license_id );

        return $license_id;
    }

    /**
     * Get user licenses array.
     *
     * @param int|bool $user_id  user id.
     *
     * @return array|bool
     */
    public static function get_all( $user_id = false ) {
        $result = false;

        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        if ( ! current_user_can( 'edit_user', $user_id ) ) {
            return $result;
        }

        // Check if already exists.
        $query = new WP_Query(
            array(
                'post_type'   => 'vatomi_license',
                'meta_query'  => array(
                    array(
                        'key'      => '_vatomi_license_user_id',
                        'value'    => $user_id,
                        'compare'  => '=',
                    ),
                ),
            )
        );

        if ( $query->have_posts() ) {
            $result = array();

            while ( $query->have_posts() ) {
                $query->the_post();
                $result[] = self::get( get_the_ID(), $user_id );
            }

            wp_reset_postdata();
        }

        return $result;
    }

    /**
     * Get license data.
     *
     * @param int|bool $license_id  license id.
     * @param int|bool $user_id     user id.
     *
     * @return array|bool
     */
    public static function get( $license_id = false, $user_id = false ) {
        $result = false;

        if ( ! $license_id ) {
            return $result;
        }

        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        if ( ! current_user_can( 'edit_user', $user_id ) ) {
            return $result;
        }

        $user_id = get_post_meta( $license_id, '_vatomi_license_user_id', true );
        if ( $user_id ) {
            $sold_at = get_post_meta( $license_id, '_vatomi_license_sold_at', true );
            $supported_until = get_post_meta( $license_id, '_vatomi_license_supported_until', true );

            $timezone = timezone_open( '+1100' );
            $current_time = date_create( 'now', $timezone );
            $current_date = date_format( $current_time, 'c' );

            $result = array(
                'user_id' => $user_id,
                'username_envato' => get_user_meta( $user_id, 'vatomi_username', true ),
                'license' => get_post_meta( $license_id, '_vatomi_license_license', true ),
                'code' => get_post_meta( $license_id, '_vatomi_license_code', true ),
                'site' => get_post_meta( $license_id, '_vatomi_license_site', true ),
                'sold_at' => $sold_at,
                'sold_at_human' => date_i18n( esc_html__( 'F j, Y', 'vatomi' ), strtotime( $sold_at ) ),
                'supported_until' => $supported_until,
                'supported_until_human' => date_i18n( esc_html__( 'F j, Y', 'vatomi' ), strtotime( $supported_until ) ),
                'supported' => $current_date < $supported_until,
                'support_amount' => get_post_meta( $license_id, '_vatomi_license_support_amount', true ),
                'amount' => get_post_meta( $license_id, '_vatomi_license_amount', true ),
                'item_name' => get_post_meta( $license_id, '_vatomi_license_item_name', true ),
                'item_id' => get_post_meta( $license_id, '_vatomi_license_item_id', true ),
                'full_data' => get_post_meta( $license_id, '_vatomi_license_full_data', true ),
            );
        }

        return $result;
    }

    /**
     * Fetch user purchases once per day or force
     * and save it in db.
     *
     * @param int         $user_id  user id.
     * @param array|bool  $force    licenses array.
     * @param object|bool $api      licenses array.
     */
    public static function maybe_fetch( $user_id, $force = false, $api = false ) {
        // Fetch new user data only once per 24 hours.
        if ( ! $force ) {
            $transient = 'vatomi_user_data_fetched_' . md5( $user_id );
            if ( ! get_transient( $transient ) ) {
                set_transient( $transient, true, 60 * 60 * 24 );
            } else {
                return;
            }
        }

        if ( ! $api ) {
            $api = new Vatomi_Envato_Wordpress_API(
                array(
                    'user_id' => $user_id,
                )
            );
        }

        $user_purchases = $api->get_user_purchase_items();

        // Save purchases meta.
        if ( $user_purchases && is_array( $user_purchases ) && ! empty( $user_purchases ) ) {
            self::add_bulk( $user_id, $user_purchases );
        }
    }

    /**
     * Extend Query licenses search JOIN.
     *
     * @param object $join - query JOIN string.
     *
     * @return string.
     */
    public function action_extend_licenses_search_join( $join ) {
        global $pagenow, $wpdb;

        $post_type = isset( $_GET['post_type'] ) ? sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) : 'post';

        if ( is_admin() && is_search() && 'vatomi_license' === $post_type && 'edit.php' === $pagenow ) {
            $join .= ' LEFT JOIN ' . $wpdb->postmeta . ' ON ' . $wpdb->posts . '.ID = ' . $wpdb->postmeta . '.post_id ';
        }

        return $join;
    }

    /**
     * Extend Query licenses search WHERE.
     *
     * @param object $where - query WHERE string.
     *
     * @return string.
     */
    public function action_extend_licenses_search_where( $where ) {
        global $pagenow, $wpdb;

        $post_type = isset( $_GET['post_type'] ) ? sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) : 'post';

        if ( is_admin() && is_search() && 'vatomi_license' === $post_type && 'edit.php' === $pagenow ) {
            $where = preg_replace( '/\(\s*' . $wpdb->posts . ".post_title\s+LIKE\s*(\'[^\']+\')\s*\)/", '(' . $wpdb->posts . '.post_title LIKE $1) OR (' . $wpdb->postmeta . '.meta_value LIKE $1)', $where );
        }

        return $where;
    }

    /**
     * Extend Query licenses search DISTINCT.
     *
     * @param object $distinct - query DISTINCT string.
     *
     * @return string.
     */
    public function action_extend_licenses_search_distinct( $distinct ) {
        global $pagenow;

        $post_type = isset( $_GET['post_type'] ) ? sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) : 'post';

        if ( is_admin() && is_search() && 'vatomi_license' === $post_type && 'edit.php' === $pagenow ) {
            $distinct = 'DISTINCT';
        }

        return $distinct;
    }
}

new Vatomi_Licenses();
