<?php

namespace NikolayS93\Exchange;

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
    add_rewrite_rule("clean", "index.php?ex1с=clean");

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
    elseif ($value == 'clean') {
        // require_once PLUGIN_DIR . "/include/clean.php";
        exit;
    }
}

/**
 * Register custom taxonomies
 */
add_action('init', function() {
    register_taxonomy('manufacturer', array('product'), array(
        'label'                 => 'Производители',
        'labels'                => array(
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
        ),
        'description'           => '', // описание таксономии
        'public'                => true,
        // 'publicly_queryable'    => null, // равен аргументу public
        // 'show_in_nav_menus'     => true, // равен аргументу public
        // 'show_ui'               => true, // равен аргументу public
        // 'show_tagcloud'         => true, // равен аргументу show_ui
    ) );

    register_taxonomy('warehouse', array('product'), array(
        'label'                 => 'Склады',
        'labels'                => array(
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
        ),
        'description'           => '', // описание таксономии
        'public'                => true,
        // 'publicly_queryable'    => null, // равен аргументу public
        // 'show_in_nav_menus'     => true, // равен аргументу public
        // 'show_ui'               => true, // равен аргументу public
        // 'show_tagcloud'         => true, // равен аргументу show_ui
    ) );
});

/**
 * Add last modified to products table
 */
add_filter( 'manage_edit-product_columns', __NAMESPACE__ . '\true_add_post_columns', 10, 1 );
function true_add_post_columns($my_columns) {
    $my_columns['modified'] = 'Last modified';
    return $my_columns;
}

add_action( 'manage_posts_custom_column', __NAMESPACE__ . '\true_fill_post_columns', 10, 1 );
function true_fill_post_columns( $column ) {
    global $post;
    switch ( $column ) {
        case 'modified':
            echo $post->post_modified;
            break;
    }
}

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
add_action( 'woocommerce_process_product_meta',
    __NAMESPACE__. '\woo_custom_general_fields_save' );

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