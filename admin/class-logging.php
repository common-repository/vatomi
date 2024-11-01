<?php
/**
 * Class for logging events and errors
 * Based on https://github.com/pippinsplugins/wp-logging/
 *
 * @package vatomi
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Vatomi_Logging Class
 *
 * A general use class for logging events and errors.
 */
class Vatomi_Logging {
    /**
     * Set true to print logs
     *
     * @var bool
     */
    public static $enabled = false;

    /**
     * Class constructor.
     *
     * @since 1.0
     *
     * @access public
     * @return void
     */
    public function __construct() {
        $this::$enabled = vatomi_get_option( 'enable_logging', 'vatomi_logs' );

        // Create the log post type.
        add_action( 'init', array( $this, 'register_post_type' ) );

        // Create types taxonomy and default types.
        add_action( 'init', array( $this, 'register_taxonomy' ) );

        // Pruning.
        add_action( 'init', array( $this, 'maybe_prune_logs' ) );

        if ( ! $this::$enabled ) {
            return;
        }

        // Add taxonomy filtering.
        add_action( 'restrict_manage_posts', array( $this, 'add_taxonomy_filters' ) );
        add_filter( 'parse_query', array( $this, 'taxonomy_filter_post_type_request' ) );
    }

    /**
     * Try to prune logs every 10 minutes.
     */
    public function maybe_prune_logs() {
        $lastrun = get_transient( 'vatomi-logger-prune-last-run' );

        if ( $lastrun ) {
            return;
        }

        set_transient( 'vatomi-logger-prune-last-run', 'Last run: ' . time(), MINUTE_IN_SECONDS * 10 );

        $logs_to_prune = $this->get_logs_to_prune();

        if ( isset( $logs_to_prune ) && ! empty( $logs_to_prune ) ) {
            $this->prune_old_logs( $logs_to_prune );
        }
    }

    /**
     * Deletes the old logs that we don't want.
     *
     * @param array/obj $logs     required     The array of logs we want to prune.
     */
    private function prune_old_logs( $logs ) {
        $force = apply_filters( 'vatomi_logging_force_delete_log', true );

        foreach ( $logs as $l ) {
            $id = is_int( $l ) ? $l : $l->ID;
            wp_delete_post( $id, $force );
        }

    }

    /**
     * Returns an array of posts that are prune candidates.
     *
     * @return array     $old_logs     The array of posts that were returned from get_posts.
     */
    private function get_logs_to_prune() {
        $how_old = vatomi_get_option( 'prune_logs', 'vatomi_logs', '2wa' );

        if ( ! $how_old || 'false' === $how_old ) {
            return array();
        }

        // replace strings, example:
        // 2da --> 2 days ago.
        $how_old = str_replace( 'da', ' days ago', $how_old );
        $how_old = str_replace( 'wa', ' weeks ago', $how_old );
        $how_old = str_replace( 'ma', ' months ago', $how_old );

        $how_old = apply_filters( 'vatomi_logging_prune_when', $how_old );

        $args = apply_filters( 'vatomi_logging_prune_query_args', array(
            'post_type'      => 'vatomi_log',
            'order'          => 'ASC',
            // limit to first 1000 posts.
            // phpcs:ignore
            'posts_per_page' => 1000,
            'date_query'     => array(
                array(
                    'before' => (string) $how_old,
                ),
            ),
        ) );

        $old_logs = get_posts( $args );

        wp_reset_postdata();

        return $old_logs;
    }

    /**
     * Sets up the default log types and allows for new ones to be created.
     *
     * @return     array
     */
    private static function log_types() {
        $terms = array(
            'log',
            'warning',
            'error',
        );

        return apply_filters( 'vatomi_log_types', $terms );
    }


    /**
     * Registers the vatomi_log Post Type.
     *
     * @return     void
     */
    public function register_post_type() {
        $log_args = array(
            'labels'              => array( 'name' => __( 'Vatomi Logs', 'vatomi' ) ),
            'public'              => false,
            'exclude_from_search' => true,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_nav_menus'   => false,
            'show_in_menu'        => false,
            'query_var'           => false,
            'rewrite'             => false,
            'capability_type'     => 'post',
            'supports'            => array( 'title', 'editor' ),
            'can_export'          => true,
            'taxonomies'          => array( 'vatomi_log_type', 'vatomi_log_category' ),
        );
        register_post_type( 'vatomi_log', apply_filters( 'vatomi_logging_post_type_args', $log_args ) );
    }

    /**
     * Registers the Type Taxonomy. The Type taxonomy is used to determine the type of log entry.
     *
     * @return     void
     */
    public function register_taxonomy() {
        register_taxonomy(
            'vatomi_log_type', 'vatomi_log', array(
                'labels' => array(
                    'name'              => _x( 'Types', 'taxonomy general name', 'vatomi' ),
                    'singular_name'     => _x( 'Type', 'taxonomy singular name', 'vatomi' ),
                    'search_items'      => __( 'Search Types', 'vatomi' ),
                    'all_items'         => __( 'All Types', 'vatomi' ),
                    'parent_item'       => __( 'Parent Type', 'vatomi' ),
                    'parent_item_colon' => __( 'Parent Type:', 'vatomi' ),
                    'edit_item'         => __( 'Edit Type', 'vatomi' ),
                    'update_item'       => __( 'Update Type', 'vatomi' ),
                    'add_new_item'      => __( 'Add New Type', 'vatomi' ),
                    'new_item_name'     => __( 'New Type Name', 'vatomi' ),
                    'menu_name'         => __( 'Type', 'vatomi' ),
                ),
                'public' => false,
                'show_admin_column' => true,
            )
        );

        register_taxonomy(
            'vatomi_log_category', 'vatomi_log', array(
                'public' => false,
                'show_admin_column' => true,
                'hierarchical' => true,
            )
        );

        $types = self::log_types();

        foreach ( $types as $type ) {
            if ( ! term_exists( $type, 'vatomi_log_type' ) ) {
                wp_insert_term( $type, 'vatomi_log_type' );
            }
        }
    }

    /**
     * Add taxonomy filter selector.
     *
     * @return     void
     */
    public function add_taxonomy_filters() {
        global $typenow;

        // Must set this to the post type you want the filter(s) displayed on.
        if ( 'vatomi_log' === $typenow ) {
            $filters = get_object_taxonomies( $typenow );

            foreach ( $filters as $tax_slug ) {
                $tax_obj = get_taxonomy( $tax_slug );
                wp_dropdown_categories(
                    array(
                        // translators: %s - label.
                        'show_option_all' => sprintf( esc_attr__( 'Show All %s', 'vatomi' ), esc_html( $tax_obj->label ) ),
                        'taxonomy'        => $tax_slug,
                        'name'            => $tax_obj->name,
                        'orderby'         => 'name',
                        'selected'        => isset( $_GET[ $tax_slug ] ) ? sanitize_text_field( wp_unslash( $_GET[ $tax_slug ] ) ) : false,
                        'hierarchical'    => $tax_obj->hierarchical,
                        'show_count'      => false,
                        'hide_empty'      => true,
                        'hide_if_empty'   => true,
                    )
                );
            }
        }
    }

    /**
     * Add taxonomy filter query.
     *
     * @param  object $query  current posts query.
     *
     * @return     void
     */
    public function taxonomy_filter_post_type_request( $query ) {
        global $pagenow, $typenow;

        if ( 'edit.php' === $pagenow && 'vatomi_log' === $typenow ) {
            $filters = get_object_taxonomies( $typenow );
            foreach ( $filters as $tax_slug ) {
                $var = &$query->query_vars[ $tax_slug ];
                if ( isset( $var ) ) {
                    $term = get_term_by( 'id', $var, $tax_slug );
                    if ( is_object( $term ) ) {
                        $var = $term->slug;
                    }
                }
            }
        }
    }


    /**
     * Check if a log type is valid.
     *
     * @param string $type  log type.
     *
     * @return     bool
     */
    private static function valid_type( $type ) {
        return in_array( $type, self::log_types() );
    }


    /**
     * Create new log entry.
     *
     * This is just a simple and fast way to log something. Use self::insert_log()
     * if you need to store custom meta data.
     *
     * @param string $title    log title.
     * @param string $message  log message.
     * @param int    $parent   log parent post id.
     * @param null   $type     log type.
     * @param null   $category log category.
     *
     * @return      int The ID of the new log entry.
     */
    public static function add( $title = '', $message = '', $parent = 0, $type = null, $category = null ) {
        $log_data = array(
            'post_title'   => $title,
            'post_content' => $message,
            'post_parent'  => $parent,
            'log_type'     => $type,
            'log_category' => $category,
        );

        return self::insert_log( $log_data );
    }

    /**
     * Stores a log entry.
     *
     * @param array $log_data  post type data.
     * @param array $log_meta  post type meta.
     *
     * @return      int The ID of the newly created log item.
     */
    public static function insert_log( $log_data = array(), $log_meta = array() ) {
        if ( ! self::$enabled ) {
            return false;
        }

        $defaults = array(
            'post_type'    => 'vatomi_log',
            'post_status'  => 'publish',
            'post_parent'  => 0,
            'post_content' => '',
            'log_type'     => false,
            'log_category' => false,
        );

        $args = wp_parse_args( $log_data, $defaults );

        do_action( 'wp_pre_insert_vatomi_log' );

        // Store the log entry.
        $log_id = wp_insert_post( $args );

        // Set the log type, if any.
        if ( $log_data['log_type'] && self::valid_type( $log_data['log_type'] ) ) {
            wp_set_object_terms( $log_id, $log_data['log_type'], 'vatomi_log_type', false );
        }

        // Set the log category, if any.
        if ( $log_data['log_category'] ) {
            $cat_id = 0;
            $cat = get_term_by( 'name', $log_data['log_category'], 'vatomi_log_category' );
            if ( $cat ) {
                $cat_id = $cat->term_id;
            } else {
                $cat = wp_insert_term(
                    $log_data['log_category'],
                    'vatomi_log_category'
                );
                if ( ! is_wp_error( $cat ) && isset( $cat['term_id'] ) ) {
                    $cat_id = $cat['term_id'];
                }
            }
            if ( $cat_id ) {
                wp_set_object_terms( $log_id, array( $cat_id ), 'vatomi_log_category' );
            }
        }

        // Set log meta, if any.
        if ( $log_id && ! empty( $log_meta ) ) {
            foreach ( (array) $log_meta as $key => $meta ) {
                update_post_meta( $log_id, '_vatomi_log_' . sanitize_key( $key ), $meta );
            }
        }

        do_action( 'wp_post_insert_vatomi_log', $log_id );

        return $log_id;
    }

    /**
     * Update and existing log item.
     *
     * @param array $log_data  post type data.
     * @param array $log_meta  post type meta.
     */
    public static function update_log( $log_data = array(), $log_meta = array() ) {
        do_action( 'wp_pre_update_vatomi_log', $log_data );

        $defaults = array(
            'post_type'   => 'vatomi_log',
            'post_status' => 'publish',
            'post_parent' => 0,
        );

        $args = wp_parse_args( $log_data, $defaults );

        // Store the log entry.
        $log_id = wp_update_post( $args );

        if ( $log_id && ! empty( $log_meta ) ) {
            foreach ( (array) $log_meta as $key => $meta ) {
                if ( ! empty( $meta ) ) {
                    update_post_meta( $log_id, '_vatomi_log_' . sanitize_key( $key ), $meta );
                }
            }
        }

        do_action( 'wp_post_update_vatomi_log', $log_id );
    }

    /**
     * Easily retrieves log items for a particular object ID.
     *
     * @param int    $object_id  log parent post id.
     * @param string $type       log type.
     * @param bool   $paged      paged logs query.
     *
     * @return array|bool
     */
    public static function get_logs( $object_id = 0, $type = null, $paged = null ) {
        return self::get_connected_logs(
            array(
                'post_parent' => $object_id,
                'paged' => $paged,
                'log_type' => $type,
            )
        );
    }

    /**
     * Retrieve all connected logs.
     *
     * Used for retrieving logs related to particular items, such as a specific purchase.
     *
     * @param array $args  args for post query.
     *
     * @return  array|bool
     */
    public static function get_connected_logs( $args = array() ) {
        $defaults = array(
            'post_parent'    => 0,
            'post_type'      => 'vatomi_log',
            'posts_per_page' => 10,
            'post_status'    => 'publish',
            'paged'          => get_query_var( 'paged' ),
            'log_type'       => false,
        );

        $query_args = wp_parse_args( $args, $defaults );

        if ( $query_args['log_type'] && self::valid_type( $query_args['log_type'] ) ) {

            $query_args['tax_query'] = array(
                array(
                    'taxonomy' => 'vatomi_log_type',
                    'field'    => 'slug',
                    'terms'    => $query_args['log_type'],
                ),
            );

        }

        $logs = get_posts( $query_args );

        if ( $logs ) {
            return $logs;
        }

        return false;
    }

    /**
     * Retrieves number of log entries connected to particular object ID.
     *
     * @param int    $object_id  log parent id.
     * @param string $type       log type.
     * @param array  $meta_query log meta data.
     *
     * @return int
     */
    public static function get_log_count( $object_id = 0, $type = null, $meta_query = null ) {
        $query_args = array(
            'post_parent'    => $object_id,
            'post_type'      => 'vatomi_log',
            // phpcs:ignore
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        );

        if ( ! empty( $type ) && self::valid_type( $type ) ) {
            $query_args['tax_query'] = array(
                array(
                    'taxonomy' => 'vatomi_log_type',
                    'field'    => 'slug',
                    'terms'    => $type,
                ),
            );
        }

        if ( ! empty( $meta_query ) ) {
            $query_args['meta_query'] = $meta_query;
        }

        $logs = new WP_Query( $query_args );

        return (int) $logs->post_count;
    }
}

// Initiate the logging system.
$GLOBALS['vatomi_logs'] = new Vatomi_Logging();

/**
 * Record a log entry.
 *
 * This is just a simple wrapper function for the log class add() function.
 *
 * @param string $title    log title.
 * @param string $message  log message.
 * @param string $category log category.
 * @param string $type     log type.
 * @param int    $parent   log parent post id.
 *
 * @return mixed ID of the new log entry.
 */
function vatomi_log( $title = '', $message = '', $category = null, $type = 'log', $parent = 0 ) {
    global $vatomi_logs;

    if ( ! $vatomi_logs::$enabled ) {
        return false;
    }

    // Prepare message.
    if ( is_array( $message ) ) {
        $message = json_encode( $message );
    }
    $message = is_string( $message ) || is_numeric( $message ) ? ( '<pre class="vatomi-pre">' . htmlspecialchars( $message ) . '</pre>' ) : '';

    // Additional data.
    $message .= '<table class="vatomi-table">
        <tbody>
            <tr>
                <th>' . esc_html__( 'IP', 'vatomi' ) . '</th>
                <td>' . ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' ) . '</td>
            </tr>
            <tr>
                <th>' . esc_html__( 'User Agent', 'vatomi' ) . '</th>
                <td>' . ( isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '' ) . '</td>
            </tr>
            <tr>
                <th>' . esc_html__( 'Requested URL', 'vatomi' ) . '</th>
                <td>' . esc_url( home_url( add_query_arg( null, null ) ) ) . '</td>
            </tr>
        </tbody>
        </table>';

    $log = $vatomi_logs->add( $title, $message, $parent, $type, $category );
    return $log;
}
