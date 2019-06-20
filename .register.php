<?php

namespace NikolayS93\Exchange;

use NikolayS93\WPAdminPage as Admin;

/**
 * Register //example.com/exchange/ query
 */
add_filter('query_vars', __NAMESPACE__ . '\query_vars');
function query_vars($query_vars) {
    $query_vars[] = 'ex1с';
    return $query_vars;
}

add_action('init', __NAMESPACE__ . '\query_map', 1000);
function query_map() {
    add_rewrite_rule("exchange", "index.php?ex1с=exchange", 'top');
    // add_rewrite_rule("clean", "index.php?ex1с=clean");

    flush_rewrite_rules();
}

add_action('template_redirect', __NAMESPACE__ . '\template_redirect', -10);
function template_redirect() {
    $value = get_query_var('ex1с');
    if (empty($value)) return;

    if ( false !== strpos($value, '?') ) {
        list($value, $query) = explode('?', $value, 2);
        parse_str($query, $query);
        $_GET = array_merge($_GET, $query);
    }

    if ($value == 'exchange') {
        do_action( '1c4wp_exchange' );
    }
    // elseif ($value == 'clean') {
    //     // require_once PLUGIN_DIR . "/include/clean.php";
    //     exit;
    // }
}

add_action('wp_ajax_1c4wp_exchange', __NAMESPACE__ . '\ajax_1c4wp_exchange');
function ajax_1c4wp_exchange() {
    if( ! wp_verify_nonce( $_REQUEST['exchange_nonce'], DOMAIN ) ) {
        echo 'Ошибка! нарушены правила безопасности';
        wp_die();
    }

    do_action( '1c4wp_exchange' );
    wp_die();
}


/**
 * Register custom taxonomies
 */
add_action('init', function() {

    $developerLabels = array(
        'name'              => 'Производители',
        'singular_name'     => 'Производитель',
        'search_items'      => 'Search brands',
        'all_items'         => 'All brands',
        'view_item '        => 'View brands',
        'parent_item'       => 'Parent brands',
        'parent_item_colon' => 'Parent brands:',
        'edit_item'         => 'Edit brands',
        'update_item'       => 'Update brands',
        'add_new_item'      => 'Add New brands',
        'new_item_name'     => 'New brands Name',
        'menu_name'         => 'Производители',
    );

    register_taxonomy(
        apply_filters( 'developerTaxonomySlug', DEFAULT_DEVELOPER_TAX_SLUG ),
        array('product'),
        array(
            'label'                 => $developerLabels['name'],
            'labels'                => $developerLabels,
            'description'           => '', // описание таксономии
            'public'                => true,
            // 'publicly_queryable'    => null, // равен аргументу public
            // 'show_in_nav_menus'     => true, // равен аргументу public
            // 'show_ui'               => true, // равен аргументу public
            // 'show_tagcloud'         => true, // равен аргументу show_ui
        )
    );

    $warehouseLabels = array(
        'name'              => 'Склады',
        'singular_name'     => 'Склад',
        'search_items'      => 'Search склад',
        'all_items'         => 'All склад',
        'view_item '        => 'View склад',
        'parent_item'       => 'Parent склад',
        'parent_item_colon' => 'Parent склад:',
        'edit_item'         => 'Edit склад',
        'update_item'       => 'Update склад',
        'add_new_item'      => 'Add New склад',
        'new_item_name'     => 'New склад Name',
        'menu_name'         => 'Склады',
    );

    register_taxonomy(
        apply_filters( 'warehouseTaxonomySlug', DEFAULT_WAREHOUSE_TAX_SLUG ),
        array('product'),
        array(
            'label'       => $warehouseLabels['name'],
            'labels'      => $warehouseLabels,
            'description' => '', // описание таксономии
            'public'      => true,
            // 'publicly_queryable'    => null, // равен аргументу public
            // 'show_in_nav_menus'     => true, // равен аргументу public
            // 'show_ui'               => true, // равен аргументу public
            // 'show_tagcloud'         => true, // равен аргументу show_ui
        )
    );
});

/**
 * Add admin menu page
 */
add_action( 'plugins_loaded', __NAMESPACE__ . '\__init', 10 );
function __init() {

    /** @var Admin\Page */
    $Page = new Admin\Page( Plugin::get_option_name(), __('1C Exchange', DOMAIN), array(
        'parent'      => 'woocommerce',
        'menu'        => __('1C Exchange', DOMAIN),
        // 'validate'    => array($this, 'validate_options'),
        'permissions' => 'manage_options',
        'columns'     => 2,
    ) );

    $Page->set_assets( function() {
        $files = Parser::getFiles();
        usort($files, function($a, $b) {
            return filemtime($a) > filemtime($b);
        });

        $filenames = array_map(function($path) {
            return basename($path);
        }, $files);

        wp_enqueue_style( 'exchange-page', Plugin::get_plugin_url('/admin/assets/exchange-page.css') );
        wp_enqueue_script( 'Timer', Plugin::get_plugin_url('/admin/assets/Timer.js') );
        wp_enqueue_script( 'ExhangeProgress', Plugin::get_plugin_url('/admin/assets/ExhangeProgress.js') );
        wp_localize_script('ExhangeProgress', 'ml2e', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce( DOMAIN ),
            'debug_only' => Utils::is_debug(),
            'files' => $filenames,
        ) );

        wp_enqueue_script( 'exchange-page-js', Plugin::get_plugin_url('/admin/assets/admin.js') );

        /**
         * Upload Script
         */
        wp_enqueue_script( 'exchange-upload-ui', Plugin::get_plugin_url('/admin/assets/exchange-upload-ui.js') );
    } );

    $Page->set_content( function() {
        Plugin::get_admin_template('menu-page', false, $inc = true);
    } );

    $Page->add_section( new Admin\Section(
        'reportbox',
        __('Report', DOMAIN),
        function() {
            Plugin::get_admin_template('section', false, $inc = true);
        }
    ) );

    $Page->add_section( new Admin\Section(
        'postsinfo',
        __('Posts', DOMAIN),
        function() {
            Plugin::get_admin_template('posts', false, $inc = true);
        }
    ) );

    $Page->add_section( new Admin\Section(
        'termsinfo',
        __('Terms', DOMAIN),
        function() {
            Plugin::get_admin_template('terms', false, $inc = true);
        }
    ) );

    $Page->add_metabox( new Admin\Metabox(
        'statusbox',
        __('Status', DOMAIN),
        function() {
            Plugin::get_admin_template('statusbox', false, $inc = true);
        }
    ) );

    // $Page->add_metabox( new Admin\Metabox(
    //     'uploadbox',
    //     __('Upload New Files', DOMAIN),
    //     function() {
    //         Plugin::get_admin_template('uploadbox', false, $inc = true);
    //     }
    // ) );
}

/**
 * Add last modified to products table
 */
// add_filter( 'manage_edit-product_columns', __NAMESPACE__ . '\true_add_post_columns', 10, 1 );
// function true_add_post_columns($my_columns) {
//     $my_columns['modified'] = 'Last modified';
//     return $my_columns;
// }

// add_action( 'manage_posts_custom_column', __NAMESPACE__ . '\true_fill_post_columns', 10, 1 );
// function true_fill_post_columns( $column ) {
//     global $post;
//     switch ( $column ) {
//         case 'modified':
//             echo $post->post_modified;
//             break;
//     }
// }
add_filter( 'post_date_column_status', function($status, $post, $strDate, $mode) {

    if( $post->post_date < $post->post_modified && 'future' !== $post->post_status ) {

        if ( 'publish' === $post->post_status ) {
            $time   = get_post_modified_time( 'G', true, $post );
            $time_diff = time() - $time;

            if ( $time_diff > 0 && $time_diff < DAY_IN_SECONDS ) {
                $showTime = sprintf( __( '%s ago' ), human_time_diff( $time ) );
            } else {
                $showTime = mysql2date( __( 'Y/m/d' ), $time );
            }

            echo __( 'Last Modified' ) . '<br />';

            /** This filter is documented in wp-admin/includes/class-wp-posts-list-table.php */
            echo '<abbr title="' . $post->post_modified . '">' . apply_filters( 'post_date_column_time', $showTime, $post, 'date', $mode ) . '</abbr><br />';
        }
    }

    return $status;
}, 10, 4 );

/** @var @todo Change hook */
add_action( 'restrict_manage_posts', function($post_type) {
    if( 'product' == $post_type ) {
        ?>
        <style>
            body table.wp-list-table td.column-thumb img {
                max-width: 75px;
                max-height: 75px;
            }
        </style>
        <?php
    }
}, 10, 1 );


/**
 * WC Product additional properties
 */
// Show Fields
add_action( 'woocommerce_product_options_general_product_data',
    __NAMESPACE__ . '\woo_add_custom_general_fields' );

function woo_add_custom_general_fields() {
    $mime = explode('/', get_post_mime_type( get_the_ID() ));

    $XML = array(
        'type'        => 'text',
        'id'          => 'EXT_ID',
        'label'       => 'Внешний код',
        // 'desc_tip'    => 'true',
        // 'description' => 'Разрешить продажи от этого количества',
        'wrapper_class' => 'show_if_simple',
        );

    if( $mime[0] == 'XML' && isset($mime[1]) ) {
        $XML['value'] = $mime[1];
    }

    woocommerce_wp_text_input( $XML );
    woocommerce_wp_text_input( array(
        'type'        => 'text',
        'id'          => '_unit',
        'label'       => 'Единица измерения',
        'wrapper_class' => 'show_if_simple',
        ) );
}

// Save Fields
add_action( 'woocommerce_process_product_meta', __NAMESPACE__. '\woo_custom_general_fields_save' );
function woo_custom_general_fields_save( $post_id ) {
    global $wpdb;

    if( isset($_POST['XML_ID']) ) {
        $wpdb->update( $wpdb->posts,
            array( 'post_mime_type' => 'XML/' . $_POST['XML_ID'] ),
            array( 'ID' => $post_id ),
            array( '%s' ),
            array( '%d' )
        );
    }

    if( isset($_POST['_unit']) ) {
        update_post_meta( $post_id, '_unit', sanitize_text_field( $_POST['_unit'] ) );
    }
}

function strict_error_handler($errno, $errstr, $errfile, $errline, $errcontext)
{
    if (error_reporting() === 0) return false;

    switch ($errno) {
        case E_NOTICE:
        case E_USER_NOTICE:
        $type = "Notice";
        break;
        case E_WARNING:
        case E_USER_WARNING:
        $type = "Warning";
        break;
        case E_ERROR:
        case E_USER_ERROR:
        $type = "Fatal Error";
        break;
        default:
        $type = "Unknown Error";
        break;
    }

    $message = sprintf("%s in %s on line %d", $errstr, $errfile, $errline);
    Utils::error($message, "PHP $type");
}

function strict_exception_handler($exception)
{
    $message = sprintf("%s in %s on line %d", $exception->getMessage(), $exception->getFile(), $exception->getLine());
    Utils::error($message, "Exception");
}

function output_callback($buffer)
{
    global $ex_is_error;

    if ( !headers_sent() ) {
        $is_xml = @$_GET['mode'] == 'query';
        $content_type = !$is_xml || $ex_is_error ? 'text/plain' : 'text/xml';
        header("Content-Type: $content_type; charset=" . XML_CHARSET);
    }

    $buffer = (XML_CHARSET == 'UTF-8') ? "\xEF\xBB\xBF$buffer" : mb_convert_encoding($buffer, XML_CHARSET, 'UTF-8');

    return $buffer;
}

function transaction_shutdown_function() {
    $error = error_get_last();

    $is_commit = !isset($error['type']) || $error['type'] > E_PARSE;

    Utils::wpdb_stop($is_commit);
}
