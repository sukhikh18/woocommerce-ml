<?php

namespace NikolayS93\Exchange;

function register_post_types() {
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
}