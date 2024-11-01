<?php
/**
 * Work with user settings page.
 *
 * @package vatomi
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Vatomi_User_Settings
 */
class Vatomi_User_Settings {
    /**
     * Vatomi_User_Settings constructor.
     */
    public function __construct() {
        // Add new column to users admin table.
        add_filter( 'manage_users_columns', array( $this, 'filter_envato_url_users_column' ) );
        add_filter( 'manage_users_custom_column', array( $this, 'filter_envato_url_users_column_row' ), 10, 3 );

        // Additional fields in user profile.
        add_action( 'show_user_profile', array( $this, 'action_envato_profile_fields' ) );
        add_action( 'edit_user_profile', array( $this, 'action_envato_profile_fields' ) );

        // Extend user search by additional fields.
        add_action( 'pre_user_search', array( $this, 'action_extend_user_search' ) );
    }

    /**
     * Vatomi Column in users table.
     *
     * @param array $columns columns list.
     *
     * @return mixed
     */
    public function filter_envato_url_users_column( $columns ) {
        $columns['vatomi'] = '<span class="vatomi-icon-envato"></span>';
        return $columns;
    }

    /**
     * Envato profile url in users table.
     *
     * @param mixed  $val          default value.
     * @param string $column_name  column name.
     * @param int    $user_id      user id.
     *
     * @return mixed
     */
    public function filter_envato_url_users_column_row( $val, $column_name, $user_id ) {
        switch ( $column_name ) {
            case 'vatomi':
                $envato_username = get_user_meta( $user_id, 'vatomi_username', true );

                if ( $envato_username ) {
                    $url = 'https://themeforest.net/user/' . $envato_username;
                    return '<a href="' . esc_url( $url ) . '" target="_blank" title="' . esc_attr( $envato_username ) . '"><span class="vatomi-icon-envato"></span> <span>' . esc_html( $envato_username ) . '</span></a>';
                }
                return '<span class="vatomi-icon-envato"></span>';
                break;
            default:
        }
        return $val;
    }

    /**
     * Additional profile fields.
     *
     * @param object $user user object.
     */
    public function action_envato_profile_fields( $user ) {
        // Visible for admins only.
        if ( ! current_user_can( 'edit_users' ) ) {
            return;
        }

        $username = get_user_meta( $user->ID, 'vatomi_username', true );

        if ( ! $username ) {
            return;
        }

        $account_details = get_user_meta( $user->ID, 'vatomi_account_details', true );
        $purchases = Vatomi_Licenses::get_all( $user->ID );

        ?>
        <h2><?php echo esc_html__( 'Envato Data', 'vatomi' ); ?></h2>

        <table class="form-table vatomi-envato-data">
            <?php
            $envato_profile_url = 'https://themeforest.net/user/' . $username;
            ?>
            <tr>
                <th><?php echo esc_html__( 'Username', 'vatomi' ); ?></th>
                <td>
                    <a href="<?php echo esc_url( $envato_profile_url ); ?>" target="_blank" title="<?php echo esc_attr( $username ); ?>"><?php echo esc_html( $username ); ?></a>
                </td>
            </tr>
            <?php
            if ( $account_details ) :
                ?>
                <tr>
                    <th><?php echo esc_html__( 'Full Name', 'vatomi' ); ?></th>
                    <td>
                        <?php
                        if ( isset( $account_details['firstname'] ) || isset( $account_details['surname'] ) ) {
                            echo esc_html( ( isset( $account_details['firstname'] ) ? $account_details['firstname'] . ' ' : '' ) . ( isset( $account_details['surname'] ) ? $account_details['surname'] : '' ) );
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <th><?php echo esc_html__( 'Country', 'vatomi' ); ?></th>
                    <td>
                        <?php
                        if ( isset( $account_details['country'] ) ) {
                            echo esc_html( $account_details['country'] );
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                </tr>
                <?php
            endif;

            if ( $purchases && is_array( $purchases ) && ! empty( $purchases ) ) :
                ?>
                <tr>
                    <th><?php echo esc_html__( 'Purchases', 'vatomi' ); ?></th>
                    <td>
                        <table class="widefat fixed">
                            <thead>
                                <tr>
                                    <td><?php echo esc_html__( 'Product', 'vatomi' ); ?></td>
                                    <td><?php echo esc_html__( 'Id', 'vatomi' ); ?></td>
                                    <td><?php echo esc_html__( 'Purchase Date', 'vatomi' ); ?></td>
                                    <td><?php echo esc_html__( 'Supported', 'vatomi' ); ?></td>
                                    <td><?php echo esc_html__( 'Purchase Code', 'vatomi' ); ?></td>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            foreach ( $purchases as $purchase ) :
                                ?>
                                <tr>
                                    <td><?php echo esc_html( $purchase['item_name'] ); ?></td>
                                    <td><?php echo esc_html( $purchase['item_id'] ); ?></td>
                                    <td><?php echo esc_html( $purchase['sold_at_human'] ); ?></td>
                                    <td><?php echo esc_html( $purchase['supported_until_human'] ); ?></td>
                                    <td><?php echo esc_html( $purchase['code'] ); ?></td>
                                </tr>
                                <?php
                            endforeach;
                            ?>
                            </tbody>
                        </table>
                    </td>
                </tr>
                <?php
            endif;
            ?>
            <tr>
                <th></th>
                <td>
                    <a href="#" class="vatomi_refresh_user_data vatomi-btn vatomi-btn-sm vatomi-btn-dark" data-reload-on-success="true" data-user-id="<?php echo esc_attr( $user->ID ); ?>"><?php echo esc_html__( 'Refresh Data', 'vatomi' ); ?></a>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Extend Query users search.
     *
     * @param object $wp_user_query - search query.
     */
    public function action_extend_user_search( $wp_user_query ) {
        if ( ! is_null( $wp_user_query->search_term ) ) {
            global $wpdb;

            // @codingStandardsIgnoreLine
            $wp_user_query->query_from .= " INNER JOIN {$wpdb->usermeta} ON {$wpdb->users}.ID = {$wpdb->usermeta}.user_id OR {$wpdb->usermeta}.vatomi_username = '_search_cache' {$wpdb->usermeta}.vatomi_account_details = '_search_cache' ";

            // @codingStandardsIgnoreLine
            $meta_where_username = $wpdb->prepare( "{$wpdb->usermeta}.vatomi_username LIKE '%s'", "%{$wp_user_query->search_term}%" );

            // @codingStandardsIgnoreLine
            $meta_where_details = $wpdb->prepare( "{$wpdb->usermeta}.vatomi_account_details LIKE '%s'", "%{$wp_user_query->search_term}%" );

            $wp_user_query->query_where = str_replace( 'WHERE 1=1 AND (', "WHERE 1=1 AND ({$meta_where_username} OR {$meta_where_details} OR", $wp_user_query->query_where );
        }
    }
}

new Vatomi_User_Settings();
